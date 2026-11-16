<?php

namespace App\Assistant;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Assistant\Nlp\Normalizer;
use App\Models\User;
use Throwable;

/**
 * يحفظ رسائل المحادثة ويسأل "العقل" عن الرد.
 * بعد التحويل لموظف يسكت المساعد الآلي (تأكيد واحد فقط) ويترك الرد للموظف.
 */
class ChatAssistant
{
    public const HANDOFF_ACK = 'وصلت رسالتك لفريق الدعم، رح يرد عليك موظف هون بأقرب وقت 🙏';

    /** المحادثة المحوّلة بلا أي رد من موظف تعود للمساعد بعد هذه المدة */
    public const HANDOFF_TIMEOUT_HOURS = 24;

    /** آخر رسالة حفظناها للعميل (ليعرف الواجهة رقمها ولا تكررها) */
    public ?ChatMessage $lastUserMessage = null;

    public function __construct(private readonly BrainResolver $brains, private readonly Normalizer $normalizer) {}

    /** @return ChatMessage|null null = لا رد آلي (المحادثة عند موظف) */
    public function handle(ChatConversation $conversation, string $message, ?User $user = null): ?ChatMessage
    {
        $message = trim($message);
        $previous = $conversation->messages()->reorder()->latest('id')->limit(6)->get();

        $this->lastUserMessage = $conversation->messages()->create(['role' => 'user', 'content' => $message]);

        if ($conversation->status === 'escalated') {
            if ($this->wantsBot($message)) {
                $conversation->update(['status' => 'open']);

                return $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => 'رجعت معك 🙂 كيف فيني ساعدك؟',
                    'tool_calls' => ['intent' => 'back_to_bot', 'chips' => Chips::home()],
                ]);
            }
            if (! $this->handoffExpired($conversation)) {
                return $this->acknowledgeOnce($conversation);
            }
            $conversation->update(['status' => 'open']);   // ما رد أحد خلال المهلة: يكمل المساعد
        }

        // الإجراء المعلّق صالح فقط إذا كان آخر رسالة قبل هذه هي سؤال التأكيد نفسه
        $last = $previous->first();
        $pending = $last && $last->role === 'assistant' ? ($last->tool_calls['pending'] ?? null) : null;
        if ($pending && ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $pending = null;   // سؤال التأكيد قديم: "نعم" المتأخرة لا تنفّذ شيئاً
        }

        $streak = 0;
        foreach ($previous->where('role', 'assistant') as $m) {
            if (($m->tool_calls['intent'] ?? null) !== 'unknown') {
                break;
            }
            $streak++;
        }

        try {
            $reply = $this->brains->for($user)->reply(new AssistantContext($conversation, $message, $user, $pending, $streak));
        } catch (Throwable $e) {
            report($e);
            $reply = new AssistantReply('صار خطأ مؤقت عندي 😓 جرّب مرة ثانية، أو احكي مع موظف.', 'error', chips: [Chips::HUMAN]);
        }

        if ($reply->handoff) {
            $conversation->update(['status' => 'escalated', 'escalated_at' => now()]);
        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply->text,
            'tool_calls' => $reply->meta(),
        ]);
    }

    private function acknowledgeOnce(ChatConversation $conversation): ?ChatMessage
    {
        $alreadyAcked = $conversation->messages()->reorder()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $conversation->escalated_at ?? $conversation->updated_at)
            ->get()
            ->contains(fn ($m) => ($m->tool_calls['intent'] ?? null) === 'handoff_ack');

        if ($alreadyAcked) {
            return null;
        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => self::HANDOFF_ACK,
            'tool_calls' => ['intent' => 'handoff_ack', 'chips' => [Chips::BOT]],
        ]);
    }

    private function wantsBot(string $message): bool
    {
        return $this->normalizer->matches($this->normalizer->normalize($message), ['رجوع للمساعد', 'ارجع للمساعد', 'المساعد الالي'], fuzzy: false);
    }

    private function handoffExpired(ChatConversation $conversation): bool
    {
        $since = $conversation->escalated_at;
        if (! $since || $since->gt(now()->subHours(self::HANDOFF_TIMEOUT_HOURS))) {
            return false;
        }

        return ! $conversation->messages()->reorder()->where('role', 'assistant')
            ->where('created_at', '>=', $since)->get()
            ->contains(fn ($m) => (bool) ($m->tool_calls['agent'] ?? false));
    }
}
