<?php

namespace App\Assistant\Brains;

use App\Assistant\AssistantBrain;
use App\Assistant\AssistantContext;
use App\Assistant\AssistantReply;
use App\Assistant\Chips;
use App\Assistant\Nlp\IntentDetector;
use App\Assistant\Skills\CouponSkill;
use App\Assistant\Skills\FaqSkill;
use App\Assistant\Skills\OrderSkill;
use App\Assistant\Skills\ProductSkill;

/**
 * العقل المجاني: يفهم النية بالقواعد والمفردات المحكية، ثم يوجّه الرسالة للمهارة المناسبة.
 * لا اتصال خارجي ولا تكلفة.
 */
class RuleBasedBrain implements AssistantBrain
{
    public function __construct(
        private readonly IntentDetector $intents,
        private readonly ProductSkill $products,
        private readonly OrderSkill $orders,
        private readonly CouponSkill $coupons,
        private readonly FaqSkill $faq,
    ) {}

    public function reply(AssistantContext $ctx): AssistantReply
    {
        $intent = $this->intents->detect($ctx->message);

        // إجراء ينتظر التأكيد من الرد السابق مباشرة
        if ($ctx->pending && ($ctx->pending['action'] ?? null) === 'cancel_order') {
            if ($intent === 'confirm') {
                return $this->orders->confirmCancel($ctx, (int) $ctx->pending['order_id']);
            }
            if ($intent === 'deny') {
                return new AssistantReply('تمام، ما لغيت شي 👍 بتحتاج شي تاني؟', 'deny', chips: Chips::home());
            }
        }

        return match ($intent) {
            'handoff' => $this->handoff(),
            'cancel_order' => $this->orders->askCancel($ctx),
            // "بدي رجّع طلبي" سؤال عن السياسة؛ رقم طلب صريح = سؤال عن الطلب نفسه
            'order_status' => (OrderSkill::orderNumber($ctx->message) ? null : $this->faq->answer($ctx->message))
                ?? $this->orders->status($ctx),
            'coupons' => $this->coupons->list($ctx),
            // "الفرق بين الشحن العادي والسريع" و"عروض على الشحن" أسئلة سياسة لا منتجات
            'compare' => $this->policy($ctx) ?? $this->products->compare($ctx->message),
            'deals' => $this->policy($ctx) ?? $this->products->deals(),
            'help' => $this->help(),
            'greeting' => $this->greeting($ctx),
            'thanks', 'confirm' => new AssistantReply('العفو! أنا هون إذا احتجت شي 🙂', 'thanks', chips: Chips::home()),
            'deny' => new AssistantReply('تمام 👍 كيف فيني ساعدك؟', 'deny', chips: Chips::home()),
            'product_search' => $this->faq->answer($ctx->message)
                ?? ($this->faq->isPolicyQuestion($ctx->message) ? $this->faq->noAnswer() : $this->products->search($ctx->message)),
            default => $this->faq->answer($ctx->message)
                ?? ($this->faq->isPolicyQuestion($ctx->message) ? $this->faq->noAnswer() : $this->unknown($ctx)),
        };
    }

    private function policy(AssistantContext $ctx): ?AssistantReply
    {
        return $this->faq->answer($ctx->message)
            ?? ($this->faq->isPolicyQuestion($ctx->message) ? $this->faq->noAnswer() : null);
    }

    private function handoff(): AssistantReply
    {
        return new AssistantReply(
            'تم تحويل محادثتك لفريق خدمة العملاء 👤 رح يرد عليك موظف هون بأقرب وقت، وفيك تكتب تفاصيل مشكلتك هلق.',
            'handoff', chips: [Chips::BOT], handoff: true,
        );
    }

    private function greeting(AssistantContext $ctx): AssistantReply
    {
        $name = $ctx->user?->first_name ? ' '.trim($ctx->user->first_name) : '';

        return new AssistantReply(
            "أهلاً فيك{$name}! 👋 أنا مساعد تريندشام. فيني دورلك على منتج بالسعر واللون اللي بدك، تابعلك طلباتك، وفرجيك كوبوناتك وعروض اليوم.",
            'greeting', chips: Chips::home(),
        );
    }

    private function help(): AssistantReply
    {
        return new AssistantReply(
            "هي الأشياء اللي بقدر ساعدك فيها:\n"
            ."• البحث: \"بدي موبايل تحت 200 ألف\" أو \"قميص أسود مقاس XL\"\n"
            ."• المقارنة: \"قارن بين سامسونج A15 و ايفون 13\"\n"
            ."• الطلبات: \"وين طلبي\" أو \"بدي الغي طلبي\"\n"
            ."• الكوبونات والعروض: \"كوبوناتي\"، \"عروض اليوم\"\n"
            .'• وإذا ما فهمت عليك، بحوّلك لموظف.',
            'help', chips: Chips::home(),
        );
    }

    private function unknown(AssistantContext $ctx): AssistantReply
    {
        // ثاني مرة ما منفهم: نعرض موظف بدل ما نعلّق العميل
        if ($ctx->unknownStreak >= 1) {
            return new AssistantReply('يبدو إني مو قادر أفهم عليك منيح 😅 بتحب حوّلك لموظف خدمة العملاء؟', 'unknown', chips: [Chips::HUMAN, Chips::TRACK, Chips::DEALS]);
        }

        return new AssistantReply(
            'ما فهمت عليك تماماً. فيك تكتبلي اسم منتج (مثلاً "سماعات تحت 50 ألف")، أو رقم طلبك، أو تختار من هون:',
            'unknown', chips: [Chips::TRACK, Chips::COUPONS, Chips::DEALS],
        );
    }
}
