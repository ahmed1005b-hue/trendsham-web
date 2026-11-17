<?php

namespace Tests\Feature;

use App\Console\Commands\RemindAbandonedCarts;
use App\Models\AppNotification;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** تذكير السلة المتروكة: مرة واحدة، بعد مهلة، وللزبائن فقط. */
class AbandonedCartReminderTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function touchCart(CartItem $item, int $hoursAgo): void
    {
        CartItem::whereKey($item->id)->update(['updated_at' => now()->subHours($hoursAgo)]);
    }

    private function reminders(int $userId)
    {
        return AppNotification::where('user_id', $userId)->where('type', RemindAbandonedCarts::TYPE);
    }

    public function test_idle_cart_gets_one_reminder_that_opens_the_cart(): void
    {
        $a = $this->tenant('a');
        $this->touchCart($a['cartItem'], 25);

        $this->artisan('cart:remind-abandoned')->assertSuccessful();
        $this->artisan('cart:remind-abandoned')->assertSuccessful();

        $this->assertSame(1, $this->reminders($a['customer']->id)->count());
        $n = $this->reminders($a['customer']->id)->first();
        $this->assertStringContainsString($a['product']->name, $n->body);
        $this->assertSame('orders', $n->group);
        $this->assertSame(['screen' => 'cart', 'id' => null], $n->target);
    }

    public function test_recently_touched_or_too_old_carts_are_skipped(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->touchCart($a['cartItem'], 2);
        $this->touchCart($b['cartItem'], 24 * 8);

        $this->artisan('cart:remind-abandoned')->assertSuccessful();

        $this->assertSame(0, $this->reminders($a['customer']->id)->count());
        $this->assertSame(0, $this->reminders($b['customer']->id)->count());
    }

    public function test_touching_the_cart_again_allows_a_new_reminder_after_the_cooldown(): void
    {
        $a = $this->tenant('a');
        $this->touchCart($a['cartItem'], 25);
        $this->artisan('cart:remind-abandoned');

        // أضاف منتجاً بعد التذكير بيوم، ثم تركها من جديد: ما زال ضمن مهلة الأيام الثلاثة
        $this->travel(2)->days();
        $this->touchCart($a['cartItem'], 25);
        $this->artisan('cart:remind-abandoned');
        $this->assertSame(1, $this->reminders($a['customer']->id)->count());

        $this->travel(2)->days();
        $this->touchCart($a['cartItem'], 25);
        $this->artisan('cart:remind-abandoned');
        $this->assertSame(2, $this->reminders($a['customer']->id)->count());
    }

    public function test_hidden_products_and_inactive_customers_are_not_reminded(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->touchCart($a['cartItem'], 25);
        $this->touchCart($b['cartItem'], 25);
        $a['product']->update(['status' => 'inactive']);
        $b['customer']->update(['status' => 'suspended']);

        $this->artisan('cart:remind-abandoned')->assertSuccessful();

        $this->assertSame(0, $this->reminders($a['customer']->id)->count());
        $this->assertSame(0, $this->reminders($b['customer']->id)->count());
    }
}
