<?php

namespace Tests\Feature;

use App\Support\RecentlyViewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** «شاهدته مؤخراً»: كوكي في الموقع، وقائمة بالأرقام للتطبيق. */
class RecentlyViewedTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_parse_keeps_order_drops_junk_and_duplicates(): void
    {
        $this->assertSame([5, 9, 2], RecentlyViewed::parseIds('5,9,x,-1,5,2'));
        $this->assertSame([], RecentlyViewed::parseIds(null));
        $this->assertCount(RecentlyViewed::MAX, RecentlyViewed::parseIds(implode(',', range(1, 40))));
    }

    public function test_opening_a_product_puts_it_first_in_the_cookie(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        $this->withCookie(RecentlyViewed::COOKIE, $a['product']->id.',77')
            ->get(route('products.show', $b['product']))
            ->assertOk()
            ->assertCookie(RecentlyViewed::COOKIE, $b['product']->id.','.$a['product']->id.',77');
    }

    public function test_home_shows_recent_products_and_hides_unavailable_ones(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $b['product']->update(['status' => 'inactive']);

        $this->withCookie(RecentlyViewed::COOKIE, $b['product']->id.','.$a['product']->id)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('شاهدته مؤخراً')
            ->assertSee($a['product']->name)
            ->assertDontSee($b['product']->name);

        $this->defaultCookies = []; // زائر جديد بلا سجل
        $this->get(route('home'))->assertOk()->assertDontSee('شاهدته مؤخراً');
    }

    public function test_clearing_forgets_the_cookie(): void
    {
        $this->withCookie(RecentlyViewed::COOKIE, '1,2')->post(route('recently-viewed.clear'))
            ->assertRedirect()
            ->assertCookieExpired(RecentlyViewed::COOKIE);
    }

    public function test_api_returns_products_by_ids_in_the_given_order(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        $this->getJson('/api/v1/products?ids='.$b['product']->id.',999999,'.$a['product']->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $b['product']->id)
            ->assertJsonPath('data.1.id', $a['product']->id);
    }
}
