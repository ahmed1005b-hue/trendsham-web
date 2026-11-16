<?php

namespace App\Returns;

use App\Models\PlatformSetting;

/** مُهَل المرتجعات (يعدّلها المدير) بقيم افتراضية آمنة. */
class ReturnSettings
{
    public const DEFAULTS = [
        'returns_window_days' => 7,
        'returns_seller_response_days' => 3,
        'returns_dispute_days' => 7,
        'returns_ship_back_days' => 14,
    ];

    private ?PlatformSetting $row = null;

    private function get(string $key): int
    {
        $this->row ??= PlatformSetting::current();

        return max(1, (int) ($this->row->getAttribute($key) ?? self::DEFAULTS[$key]));
    }

    public function windowDays(): int
    {
        return $this->get('returns_window_days');
    }

    public function sellerResponseDays(): int
    {
        return $this->get('returns_seller_response_days');
    }

    public function disputeDays(): int
    {
        return $this->get('returns_dispute_days');
    }

    public function shipBackDays(): int
    {
        return $this->get('returns_ship_back_days');
    }

    public function fresh(): self
    {
        $this->row = null;

        return $this;
    }
}
