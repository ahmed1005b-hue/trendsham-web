<?php

namespace Tests\Unit\Loyalty;

use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltySettings;
use PHPUnit\Framework\TestCase;

class LoyaltyCalculatorTest extends TestCase
{
    private function calc(): LoyaltyCalculator
    {
        // الإعدادات الافتراضية بلا قاعدة بيانات
        $settings = new class extends LoyaltySettings
        {
            public function enabled(): bool { return true; }
            public function spendPerPoint(): int { return 1000000; }
            public function pointValue(): int { return 20000; }
            public function minRedeem(): int { return 10; }
        };

        return new LoyaltyCalculator($settings);
    }

    public function test_points_round_down_per_10000_lira(): void
    {
        $c = $this->calc();
        $this->assertSame(4, $c->pointsFor(4750000));    // 47,500 ل.س
        $this->assertSame(1, $c->pointsFor(1999900));    // 19,999 ل.س
        $this->assertSame(0, $c->pointsFor(999900));     // 9,999 ل.س
        $this->assertSame(0, $c->pointsFor(-5));
    }

    public function test_point_value_is_200_lira(): void
    {
        $this->assertSame(400000, $this->calc()->valueOf(20));   // 4,000 ل.س
    }

    public function test_max_usable_is_capped_by_balance_and_payable(): void
    {
        $c = $this->calc();
        $this->assertSame(30, $c->maxUsable(30, 5000000));   // سلة 50,000 تسمح بـ250
        $this->assertSame(25, $c->maxUsable(30, 500000));    // 5,000 ل.س تسمح بـ25 فقط
        $this->assertSame(0, $c->maxUsable(30, 0));
    }

    public function test_hint_shows_points_or_what_is_missing(): void
    {
        $c = $this->calc();
        $this->assertSame(['points' => 2, 'missing' => 0], $c->hint(2600000));      // 26,000 ل.س
        $this->assertSame(['points' => 0, 'missing' => 200000], $c->hint(800000));  // أضف 2,000 ل.س
    }
}
