<?php

namespace App\Http\Controllers;

use App\Assistant\AssistantReply;
use App\Assistant\ChatAssistant;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * محادثة المساعد. نفس الكنترولر يخدم:
 *  - التطبيق عبر /api/v1/chat (توكن Sanctum أو X-Guest-Id للزائر)
 *  - الموقع عبر /assistant/chat (جلسة الموقع، ليتعرّف على العميل المسجّل)
 */
class ChatAssistantController extends Controller
{
    private const HISTORY_LIMIT = 100;

    public function __construct(protected ChatAssistant $assistant) {}

    /**
     * سجل المحادثة الحالية. لا ينشئ محادثة (الإنشاء عند أول رسالة فقط).
     * إن أُغلقت المحادثة مؤخراً نعرضها، حتى لا يضيع رد الموظف الأخير.
     */
    public function show(Request $request)
    {
        $owner = $this->owner($request);
        $conversation = $this->active($owner)
            ?? $this->scoped($owner)->where('status', 'closed')->where('updated_at', '>=', now()->subDay())->latest('id')->first();

        if (! $conversation) {
            return response()->json(['conversation_id' => null, 'status' => 'open', 'messages' => []]);
        }

        $messages = $conversation->messages()->reorder()->latest('id')->limit(self::HISTORY_LIMIT)->get()->reverse()->values();

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'messages' => $messages->map(fn ($m) => $this->present($m)),
        ]);
    }

    /** رسالة جديدة؛ reply = null حين تكون المحادثة عند موظف */
    public function send(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $owner = $this->owner($request);
        $conversation = $this->active($owner) ?? ChatConversation::create([
            'user_id' => $owner['user_id'],
            'guest_session_id' => $owner['user_id'] ? null : $owner['guest'],
            'status' => 'open',
        ]);

        $reply = $this->assistant->handle($conversation, $request->input('message'), $request->user('sanctum'));

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->refresh()->status,
            'message' => $this->assistant->lastUserMessage ? $this->present($this->assistant->lastUserMessage) : null,
            'reply' => $reply ? $this->present($reply) : null,
        ]);
    }

    private function present(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'meta' => (object) AssistantReply::publicMeta($m->tool_calls),
            'created_at' => $m->created_at,
        ];
    }

    /** @return array{user_id: ?int, guest: ?string} */
    private function owner(Request $request): array
    {
        $userId = $request->user('sanctum')?->id;
        if ($userId) {
            return ['user_id' => $userId, 'guest' => null];
        }

        $guestId = (string) ($request->header('X-Guest-Id') ?: $request->input('guest_id'));
        abort_if($guestId === '' || strlen($guestId) > 100, 422, 'مطلوب معرّف زائر (guest_id) للمستخدمين غير المسجلين.');

        return ['user_id' => null, 'guest' => $guestId];
    }

    private function scoped(array $owner): Builder
    {
        return $owner['user_id']
            ? ChatConversation::where('user_id', $owner['user_id'])
            : ChatConversation::where('guest_session_id', $owner['guest'])->whereNull('user_id');
    }

    /** المحادثة المحوّلة لموظف تبقى نفسها حتى تُغلق أو تنتهي مهلتها */
    private function active(array $owner): ?ChatConversation
    {
        return $this->scoped($owner)->whereIn('status', ['open', 'escalated'])->latest('id')->first();
    }
}
