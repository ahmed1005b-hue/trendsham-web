<?php

namespace App\Loyalty;

use App\Models\PlatformSetting;

/** إعدادات برنامج النقاط (يعدّلها المدير) بقيم افتراضية آمنة. المبالغ بالوحدة الصغرى ×100. */
class LoyaltySettings
{
    public const DEFAULTS = [
        'loyalty_enabled' => true,
        'loyalty_spend_per_point' => 1000000,   // 10,000 ل.س لكل نقطة
        'loyalty_point_value' => 20000,         // 200 ل.س للنقطة
        'loyalty_min_redeem' => 10,
        'loyalty_expiry_months' => 12,
        'loyalty_coupon_valid_days' => 30,
    ];

    private ?PlatformSetting $row = null;

    private function get(string $key): mixed
    {
        $this->row ??= PlatformSetting::current();
        $value = $this->row->getAttribute($key);

        return $value ?? self::DEFAULTS[$key];
    }

    public function enabled(): bool
    {
        return (bool) $this->get('loyalty_enabled');
    }

    public function spendPerPoint(): int
    {
        return max(1, (int) $this->get('loyalty_spend_per_point'));
    }

    public function pointValue(): int
    {
        return max(0, (int) $this->get('loyalty_point_value'));
    }

    public function minRedeem(): int
    {
        return max(1, (int) $this->get('loyalty_min_redeem'));
    }

    public function expiryMonths(): int
    {
        return max(1, (int) $this->get('loyalty_expiry_months'));
    }

    public function couponValidDays(): int
    {
        return max(1, (int) $this->get('loyalty_coupon_valid_days'));
    }

    public function fresh(): self
    {
        $this->row = null;

        return $this;
    }
}
