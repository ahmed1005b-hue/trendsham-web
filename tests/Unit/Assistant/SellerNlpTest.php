<?php

namespace Tests\Unit\Assistant;

use App\Assistant\Nlp\Normalizer;
use App\Assistant\Nlp\Period;
use App\Assistant\Nlp\SellerIntentDetector;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SellerNlpTest extends TestCase
{
    #[DataProvider('intents')]
    public function test_seller_intents(string $message, string $expected): void
    {
        $this->assertSame($expected, (new SellerIntentDetector(new Normalizer))->detect($message));
    }

    public static function intents(): array
    {
        return [
            ['قديش مبيعاتي هالشهر', 'sales'],
            ['طلبات السحب تبعي', 'wallet'],
            ['مبيعات الطلبات المسلمة', 'sales'],
            ['مبيعاتي من المخزون', 'sales'],
            ['الكمية المباعة هالشهر', 'sales'],
            ['شكرا خلصت', 'thanks'],
            ['شو الطلبات اللي بانتظاري', 'pending_orders'],
            ['شو المنتجات اللي قربت تخلص', 'low_stock'],
            ['المخزون', 'low_stock'],
            ['عطيني نصائح لزيد مبيعاتي', 'tips'],
            ['كم رصيدي', 'wallet'],
            ['اكتبلي وصف لمنتج قميص', 'describe'],
        ];
    }

    #[DataProvider('notIntents')]
    public function test_everyday_phrases_do_not_trigger_the_wrong_intent(string $message, string $wrong): void
    {
        $this->assertNotSame($wrong, (new SellerIntentDetector(new Normalizer))->detect($message));
    }

    public static function notIntents(): array
    {
        return [
            ['نفذ الطلب', 'low_stock'],
            ['كيف بضيف كمية لمنتج', 'low_stock'],
            ['هاي الطلبية متأخرة', 'greeting'],
        ];
    }

    public function test_last_month_and_last_week_are_previous_ranges(): void
    {
        CarbonImmutable::setTestNow('2026-11-12 10:00:00');

        $m = Period::fromMessage('مبيعاتي الشهر الماضي');
        $this->assertSame('الشهر الماضي', $m->label);
        $this->assertSame('2026-10-01', $m->from->toDateString());
        $this->assertSame('2026-10-31', $m->to->toDateString());

        $w = Period::fromMessage('مبيعات الاسبوع اللي فات');
        $this->assertSame('الأسبوع الماضي', $w->label);
        $this->assertSame('2026-10-30', $w->from->toDateString());
        $this->assertSame('2026-11-05', $w->to->toDateString());

        // "الجمعة" يوم، لا أسبوع
        $this->assertSame('هالشهر', Period::fromMessage('مبيعات هالجمعة')->label);

        CarbonImmutable::setTestNow();
    }
}
