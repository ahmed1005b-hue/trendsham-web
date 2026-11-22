<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** بطاقة البائع: شعار المتجر بحلقة القصة (إن وُجدت)، واسمه، وزر زيارة المتجر. */
class SellerBoxTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_product_page_shows_the_store_with_its_story_ring_and_visit_button(): void
    {
        $t = $this->tenant('a');

        $this->get(route('products.show', $t['product']->slug))->assertOk()
            ->assertSee('ts-seller-box__avatar has-story', false)
            ->assertSee('data-ts-story="0"', false)
            ->assertSee('id="tsStoryModal"', false)
            ->assertSee(route('stores.show', $t['store']), false)
            ->assertSee('زيارة المتجر')
            ->assertSee($t['store']->store_name);
    }

    public function test_without_a_current_story_the_logo_just_links_to_the_store(): void
    {
        $t = $this->tenant('a');
        $t['story']->update(['expires_at' => now()->subMinute()]);

        $this->get(route('products.show', $t['product']->slug))->assertOk()
            ->assertDontSee('ts-seller-box__avatar has-story', false)
            ->assertDontSee('id="tsStoryModal"', false)
            ->assertSee('زيارة المتجر');
    }

    public function test_quick_view_data_carries_the_store_link_and_latest_story(): void
    {
        $t = $this->tenant('a');

        $this->getJson('/api/v1/products/'.$t['product']->slug)->assertOk()
            ->assertJsonPath('data.vendor.store_url', route('stores.show', $t['store']))
            ->assertJsonPath('data.vendor.story_id', $t['story']->id)
            ->assertJsonPath('data.vendor.is_verified', true);
    }
}
