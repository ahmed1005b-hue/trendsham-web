<?php

namespace App\Loyalty;

use App\Models\Order;
use App\Models\User;

/** حسابات النقاط الصافية (بلا كتابة). المبالغ بالوحدة الصغرى ×100. */
class LoyaltyCalculator
{
    /** حالات الطلب التي نقاطها "معلّقة" بانتظار التسليم */
    public const PENDING_STATUSES = ['pending', 'paid', 'processing', 'shipped'];

    public function __construct(private readonly LoyaltySettings $settings) {}

    public function settings(): LoyaltySettings
    {
        return $this->settings;
    }

    /** نقطة لكل spendPerPoint كاملة، بالتقريب للأسفل */
    public function pointsFor(int $paidMinor): int
    {
        return $paidMinor > 0 ? intdiv($paidMinor, $this->settings->spendPerPoint()) : 0;
    }

    /** قيمة الخصم لعدد من النقاط */
    public function valueOf(int $points): int
    {
        return max(0, $points) * $this->settings->pointValue();
    }

    /** المبلغ الذي تُحسب منه نقاط الطلب: ما دُفع فعلاً للمنتجات (دون الشحن وبعد كل الخصومات) */
    public function paidBase(Order $order): int
    {
        return max(0, (int) $order->subtotal - (int) $order->discount_amount - (int) $order->points_discount);
    }

    /** أقصى نقاط تُستخدم: لا تتجاوز الرصيد ولا قيمة المنتجات بعد الكوبون (الشحن لا يُدفع بالنقاط) */
    public function maxUsable(int $balance, int $payableMinor): int
    {
        $value = $this->settings->pointValue();
        if ($value <= 0 || $payableMinor <= 0) {
            return 0;
        }

        return max(0, min($balance, intdiv($payableMinor, $value)));
    }

    /** "ستكسب X نقاط" أو كم ينقص لأول نقطة */
    public function hint(int $amountMinor): array
    {
        $points = $this->pointsFor($amountMinor);

        return [
            'points' => $points,
            'missing' => $points > 0 ? 0 : $this->settings->spendPerPoint() - max(0, $amountMinor),
        ];
    }

    /** النقاط المتوقعة من طلبات لم تُسلَّم بعد */
    public function pending(User $user): int
    {
        return (int) Order::where('user_id', $user->id)->whereIn('status', self::PENDING_STATUSES)
            ->get(['subtotal', 'discount_amount', 'points_discount'])
            ->sum(fn (Order $o) => $this->pointsFor($this->paidBase($o)));
    }

    /** نص التحفيز للعرض */
    public function hintText(int $amountMinor): ?string
    {
        if (! $this->settings->enabled()) {
            return null;
        }
        $h = $this->hint($amountMinor);

        return $h['points'] > 0
            ? __('ستكسب :points عند استلام الطلب', ['points' => self::localPointsLabel($h['points'])])
            : __('أضف :amount ل.س لتكسب أول نقطة', ['amount' => number_format($h['missing'] / 100)]);
    }

    public static function pointsLabel(int $n): string
    {
        return match (true) {
            $n === 1 => 'نقطة واحدة',
            $n === 2 => 'نقطتين',
            $n >= 3 && $n <= 10 => "{$n} نقاط",
            default => "{$n} نقطة",
        };
    }

    /**
     * نفس pointsLabel لكن بلغة الواجهة الحالية، للعرض في الصفحات فقط.
     * (pointsLabel تبقى عربية لأنها تدخل في نصوص تُخزَّن مثل الإشعارات.)
     */
    public static function localPointsLabel(int $n): string
    {
        return match (true) {
            $n === 1 => __('نقطة واحدة'),
            $n === 2 => __('نقطتين'),
            $n >= 3 && $n <= 10 => __(':n نقاط', ['n' => $n]),
            default => __(':n نقطة', ['n' => $n]),
        };
    }
}
