<?php

namespace App\Assistant\Skills;

use App\Assistant\AssistantContext;
use App\Assistant\AssistantReply;
use App\Assistant\Cards;
use App\Assistant\Chips;
use App\Models\Order;
use App\Services\CustomerOrderCanceller;

/** حالة الطلبات وإلغاؤها — للعميل المسجّل فقط، وعلى طلباته فقط */
class OrderSkill
{
    public function __construct(private readonly CustomerOrderCanceller $canceller) {}

    public function status(AssistantContext $ctx): AssistantReply
    {
        if (! $ctx->isCustomer()) {
            return $this->loginPrompt('order_status');
        }

        $number = self::orderNumber($ctx->message);
        $orders = Order::where('user_id', $ctx->user->id)
            ->when($number, fn ($q) => $q->where('order_number', $number))
            ->with('shipment')->latest()->limit(3)->get();

        if ($orders->isEmpty()) {
            return new AssistantReply(
                $number ? "ما لقيت طلب برقم {$number} بحسابك. تأكد من الرقم وابعتلي ياه مرة ثانية." : 'ما في طلبات بحسابك لسا. شو رأيك تشوف عروض اليوم؟',
                'order_status', chips: $number ? [Chips::TRACK, Chips::HUMAN] : [Chips::DEALS],
            );
        }

        $lines = $orders->map(function (Order $o) {
            $line = "• {$o->order_number}: {$o->statusLabel()} — ".number_format($o->total / 100).' ل.س — '.$o->created_at?->format('Y-m-d');
            if ($o->shipment?->tracking_number) {
                $line .= " — رقم التتبع: {$o->shipment->tracking_number}";
            }

            return $line;
        })->implode("\n");

        $chips = $orders->contains('status', 'pending') ? ['بدي الغي طلبي', Chips::HUMAN] : [Chips::HUMAN];

        return new AssistantReply(
            ($number ? "حالة الطلب {$number}:" : 'آخر طلباتك:')."\n{$lines}",
            'order_status',
            cards: $orders->map(fn ($o) => Cards::order($o))->all(),
            chips: $chips,
        );
    }

    /** يسأل أولاً؛ الإلغاء الفعلي في confirmCancel بعد "نعم" */
    public function askCancel(AssistantContext $ctx): AssistantReply
    {
        if (! $ctx->isCustomer()) {
            return $this->loginPrompt('cancel_order');
        }

        $number = self::orderNumber($ctx->message);
        $candidates = Order::where('user_id', $ctx->user->id)->where('status', 'pending')
            ->when($number, fn ($q) => $q->where('order_number', $number))
            ->latest()->limit(5)->get();

        if ($candidates->isEmpty()) {
            $why = $number
                ? "الطلب {$number} ما بينلغى من هون: يا إما مو موجود بحسابك، أو دخل مرحلة التجهيز."
                : 'ما عندك طلبات بتنلغى حالياً.';

            return new AssistantReply("{$why} الإلغاء متاح بس للطلبات اللي لسا \"قيد المراجعة\". إذا بدك مساعدة بطلب بلّش تجهيزه، احكي مع موظف.", 'cancel_order', chips: [Chips::TRACK, Chips::HUMAN]);
        }

        if ($candidates->count() > 1) {
            $list = $candidates->map(fn ($o) => "• {$o->order_number} — ".number_format($o->total / 100).' ل.س')->implode("\n");

            return new AssistantReply("عندك أكتر من طلب بينلغى. أي واحد بدك تلغي؟ ابعتلي رقمه:\n{$list}", 'cancel_order',
                chips: $candidates->map(fn ($o) => "الغي {$o->order_number}")->all());
        }

        $order = $candidates->first();

        return new AssistantReply(
            "متأكد إنك بدك تلغي الطلب {$order->order_number} بقيمة ".number_format($order->total / 100).' ل.س؟',
            'cancel_order',
            cards: [Cards::order($order)],
            chips: [Chips::YES, Chips::NO],
            pending: ['action' => 'cancel_order', 'order_id' => $order->id, 'expires_at' => now()->addMinutes(10)->timestamp],
        );
    }

    public function confirmCancel(AssistantContext $ctx, int $orderId): AssistantReply
    {
        // التأكيد صالح فقط لصاحب الطلب نفسه، والطلب ما زال قيد المراجعة
        $order = $ctx->isCustomer() ? Order::where('user_id', $ctx->user->id)->find($orderId) : null;

        if (! $order || ! $this->canceller->cancel($order)) {
            return new AssistantReply('ما قدرت ألغي الطلب: يبدو إنه دخل مرحلة التجهيز. فيك تحكي مع موظف إذا بتحب.', 'cancel_order', chips: [Chips::TRACK, Chips::HUMAN]);
        }

        return new AssistantReply("تم إلغاء الطلب {$order->order_number} ✅ ورجعت المنتجات للمخزون.", 'cancel_order', chips: [Chips::TRACK, Chips::DEALS]);
    }

    public static function orderNumber(string $message): ?string
    {
        return preg_match('/\b(TS-?[A-Z0-9]+(?:-[A-Z0-9]+)*)\b/i', $message, $m) && mb_strlen($m[1]) >= 8
            ? strtoupper($m[1])
            : null;
    }

    private function loginPrompt(string $intent): AssistantReply
    {
        return new AssistantReply(
            'لحتى شوف طلباتك لازم تسجّل دخول بحسابك أول 🙏 بعدها اسألني وبجاوبك فوراً.',
            $intent,
            cards: [Cards::link('تسجيل الدخول', 'login', route('login'))],
        );
    }
}
