<?php

namespace App\Loyalty;

use App\Models\Order;
use App\Models\User;

/**
 * استبدال النقاط عند الدفع — مشترك بين دفع الموقع والتطبيق.
 * يُستدعى داخل معاملة إنشاء الطلب بعد تطبيق الكوبون، وقبل إنشاء سجل الدفع.
 */
class LoyaltyCheckout
{
    public function __construct(
        private readonly LoyaltyLedger $ledger,
        private readonly LoyaltyCalculator $calc,
    ) {}

    /**
     * @throws LoyaltyException رصيد غير كافٍ، أقل من الحد الأدنى، حساب موقوف، أو البرنامج متوقف
     */
    public function apply(User $user, Order $order, ?int $requested): void
    {
        if (! $requested || $requested <= 0) {
            return;
        }

        $this->ledger->assertCanRedeem($user, $requested);

        // النقاط تغطي المنتجات بعد الكوبون فقط — لا الشحن، ولا يصبح الطلب سالباً
        $payable = (int) $order->subtotal - (int) $order->discount_amount;
        $points = $this->calc->maxUsable($requested, $payable);
        $min = $this->calc->settings()->minRedeem();
        if ($points < $min) {
            throw new LoyaltyException(__('قيمة سلتك تسمح باستخدام :points فقط، والحد الأدنى للاستبدال :min.', [
                'points' => LoyaltyCalculator::localPointsLabel($points), 'min' => LoyaltyCalculator::localPointsLabel($min),
            ]));
        }

        $discount = $this->calc->valueOf($points);
        $order->update([
            'points_redeemed' => $points,
            'points_discount' => $discount,
            'total' => (int) $order->total - $discount,
        ]);

        $this->ledger->redeemForOrder($user, $order, $points);
    }

    /** ما تعرضه صفحة الدفع: الرصيد، أقصى ما يُستخدم، وكم سيكسب */
    public function summary(User $user, int $subtotal, int $couponDiscount, int $usePoints = 0): array
    {
        $settings = $this->calc->settings();
        $balance = $this->ledger->account($user->id)->balance;
        $payable = max(0, $subtotal - $couponDiscount);
        $max = $this->calc->maxUsable($balance, $payable);
        $use = min(max(0, $usePoints), $max);

        return [
            'enabled' => $settings->enabled(),
            'balance' => $balance,
            'max_usable' => $settings->enabled() && $max >= $settings->minRedeem() ? $max : 0,
            'point_value' => $settings->pointValue(),
            'spend_per_point' => $settings->spendPerPoint(),
            'min_redeem' => $settings->minRedeem(),
            'will_earn' => $settings->enabled() ? $this->calc->pointsFor($payable - $this->calc->valueOf($use)) : 0,
        ];
    }
}
