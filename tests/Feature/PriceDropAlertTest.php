<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Favorite;
use App\Services\PriceDropNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** تنبيه انخفاض السعر لمن أضاف المنتج إلى مفضلته. */
class PriceDropAlertTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function alerts(int $userId)
    {
        return AppNotification::where('user_id', $userId)->where('type', PriceDropNotifier::TYPE);
    }

    public function test_customer_who_favorited_the_product_is_alerted_once(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        Favorite::create(['user_id' => $a['customer']->id, 'product_id' => $a['product']->id]);

        $a['product']->update(['price' => 8000]); // 100 → 80 ل.س
        $a['product']->update(['price' => 7000]); // انخفاض ثانٍ خلال المهلة: لا تنبيه جديد

        $this->assertSame(1, $this->alerts($a['customer']->id)->count());
        $n = $this->alerts($a['customer']->id)->first();
        $this->assertStringContainsString('100 ل.س', $n->body);
        $this->assertStringContainsString('80 ل.س', $n->body);
        $this->assertSame('favorites', $n->group);
        $this->assertSame('product', $n->target['screen']);
        $this->assertSame(0, $this->alerts($b['customer']->id)->count());
    }

    public function test_alerts_again_after_the_cooldown(): void
    {
        $a = $this->tenant('a');
        Favorite::create(['user_id' => $a['customer']->id, 'product_id' => $a['product']->id]);

        $a['product']->update(['price' => 8000]);
        $this->travel(PriceDropNotifier::COOLDOWN_DAYS + 1)->days();
        $a['product']->update(['price' => 6000]);

        $this->assertSame(2, $this->alerts($a['customer']->id)->count());
    }

    public function test_price_rise_tiny_drop_or_hidden_product_send_nothing(): void
    {
        $a = $this->tenant('a');
        Favorite::create(['user_id' => $a['customer']->id, 'product_id' => $a['product']->id]);

        $a['product']->update(['price' => 12000]); // ارتفاع
        $a['product']->update(['price' => 11800]); // أقل من 5%
        $a['product']->update(['status' => 'inactive']);
        $a['product']->update(['price' => 5000]); // مخفي

        $this->assertSame(0, $this->alerts($a['customer']->id)->count());
    }
}
