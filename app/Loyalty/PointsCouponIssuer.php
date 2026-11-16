<?php

namespace App\Loyalty;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * تحويل النقاط إلى كوبون في محفظة الكوبونات. يعيد استخدام نظام الكوبونات بالكامل
 * (الأهلية، الاختيار التلقائي، القفل، الإعادة عند الإلغاء). المنصة تموّل الخصم.
 */
class PointsCouponIssuer
{
    public const TRIGGER = 'loyalty_points';
    public const SOURCE = 'loyalty';

    public function __construct(
        private readonly LoyaltyLedger $ledger,
        private readonly LoyaltyCalculator $calc,
    ) {}

    /** @throws LoyaltyException */
    public function convert(User $user, int $points): CustomerCoupon
    {
        $this->ledger->assertCanRedeem($user, $points);
        $value = $this->calc->valueOf($points);
        $days = $this->calc->settings()->couponValidDays();

        return DB::transaction(function () use ($user, $points, $value, $days) {
            $campaign = $this->campaignFor($value, $days);

            $coupon = CustomerCoupon::create([
                'campaign_id' => $campaign->id, 'user_id' => $user->id, 'status' => 'available',
                'source' => self::SOURCE, 'expires_at' => now()->addDays($days),
            ]);
            $campaign->increment('issued_count');

            // الخصم من الرصيد بعد إنشاء الكوبون وفي نفس المعاملة: إن رُفض (ضغطة مزدوجة) يُلغى الكوبون
            $this->ledger->debit($user->id, Tx::REDEEM_COUPON, $points, customerCouponId: $coupon->id,
                onceKey: 'redeem_coupon:'.$coupon->id);

            return $coupon;
        });
    }

    /** المهمة اليومية: كوبونات نقاط انتهت دون استخدام → تنتهي، وتعود نقاطها لدفعاتها. */
    public function restoreExpired(): int
    {
        $restored = 0;
        CustomerCoupon::where('source', self::SOURCE)->where('status', 'available')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->pluck('id')->each(function (int $id) use (&$restored) {
                DB::transaction(function () use ($id, &$restored) {
                    $coupon = CustomerCoupon::whereKey($id)->lockForUpdate()->first();
                    if (! $coupon || $coupon->status !== 'available') {
                        return;
                    }
                    $coupon->update(['status' => 'expired']);
                    $debit = Tx::where('type', Tx::REDEEM_COUPON)->where('customer_coupon_id', $coupon->id)->first();
                    if ($debit) {
                        $restored += $this->ledger->restoreAllocations($debit);
                    }
                });
            });

        return $restored;
    }

    /** حملة نظامية مخفية لكل قيمة كوبون (تُنشأ مرة واحدة) */
    private function campaignFor(int $value, int $days): CouponCampaign
    {
        $campaign = CouponCampaign::firstOrCreate(
            ['trigger' => self::TRIGGER, 'value' => $value],
            [
                'funder' => 'platform', 'title' => 'خصم نقاط الولاء '.number_format($value / 100).' ل.س',
                'type' => 'fixed', 'scope' => 'all', 'audience' => 'specific', 'min_order_amount' => 0,
                'starts_at' => now()->subMinute(), 'valid_days' => $days,
                'per_customer_limit' => 1_000_000, 'status' => 'active',
            ]
        );
        if ($campaign->valid_days !== $days) {
            $campaign->update(['valid_days' => $days]);
        }

        return $campaign;
    }
}
