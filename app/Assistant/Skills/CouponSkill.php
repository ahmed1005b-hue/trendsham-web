<?php

namespace App\Assistant\Skills;

use App\Assistant\AssistantContext;
use App\Assistant\AssistantReply;
use App\Assistant\Cards;
use App\Assistant\Chips;
use App\Coupons\CouponIssuer;
use App\Coupons\Models\CustomerCoupon;

/** كوبونات العميل من محفظته */
class CouponSkill
{
    public function __construct(private readonly CouponIssuer $issuer) {}

    public function list(AssistantContext $ctx): AssistantReply
    {
        if (! $ctx->isCustomer()) {
            return new AssistantReply(
                'كوبوناتك محفوظة بحسابك، سجّل دخول لحتى شوفلك ياهن 🎟️',
                'coupons',
                cards: [Cards::link('تسجيل الدخول', 'login', route('login'))],
            );
        }

        $this->issuer->syncFor($ctx->user);

        $coupons = CustomerCoupon::where('user_id', $ctx->user->id)->where('status', 'available')
            ->with('campaign.seller')->latest()->get()
            ->filter(fn ($c) => ! $c->isExpired() && $c->campaign?->isRunning())
            ->take(5);

        $wallet = Cards::link('محفظة الكوبونات', 'coupons', route('customer.coupons.index'));

        if ($coupons->isEmpty()) {
            return new AssistantReply('ما عندك كوبونات فعّالة هلق. أول ما يوصلك كوبون جديد رح يبيّن بمحفظتك.', 'coupons', cards: [$wallet], chips: [Chips::DEALS]);
        }

        $lines = $coupons->map(function (CustomerCoupon $c) {
            $line = "• {$c->campaign->title} — {$c->campaign->valueLabel()} ({$c->campaign->sourceLabel()})";
            if ($c->campaign->min_order_amount > 0) {
                $line .= ' — لطلب من '.number_format($c->campaign->min_order_amount / 100).' ل.س';
            }
            if ($exp = $c->effectiveExpiry()) {
                $line .= ' — ينتهي '.$exp->format('Y-m-d');
            }

            return $line;
        })->implode("\n");

        return new AssistantReply(
            "كوبوناتك الفعّالة 🎟️\n{$lines}\n\nأفضل كوبون بيتطبّق تلقائياً على طلبك وقت الدفع.",
            'coupons',
            cards: [$wallet],
        );
    }
}
