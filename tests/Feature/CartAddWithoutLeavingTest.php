<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** الإضافة للسلة من نفس الصفحة (حركة طيران المنتج): رد JSON بعدد السلة الجديد. */
class CartAddWithoutLeavingTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_adding_from_the_page_returns_the_new_cart_count(): void
    {
        $t = $this->tenant('a');
        $before = (int) $t['customer']->cartItems()->sum('quantity');

        $this->actingAs($t['customer'])
            ->postJson(route('customer.cart.store'), ['product_id' => $t['product']->id, 'quantity' => 2])
            ->assertOk()
            ->assertJson(['cart_count' => $before + 2, 'cart_url' => route('customer.cart.index')])
            ->assertJsonPath('message', 'تمت الإضافة للسلة.');
    }

    public function test_a_product_with_options_explains_what_is_missing(): void
    {
        $t = $this->tenant('a');
        ProductVariant::create(['product_id' => $t['product']->id, 'color' => 'red', 'size' => 'M', 'stock_quantity' => 3, 'is_active' => true]);

        $this->actingAs($t['customer'])
            ->postJson(route('customer.cart.store'), ['product_id' => $t['product']->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'يرجى اختيار اللون/المقاس قبل الإضافة إلى السلة.');
    }

    public function test_the_normal_form_still_goes_to_the_cart_page(): void
    {
        $t = $this->tenant('a');

        $this->actingAs($t['customer'])
            ->post(route('customer.cart.store'), ['product_id' => $t['product']->id])
            ->assertRedirect(route('customer.cart.index'));
    }

    public function test_guests_are_sent_to_sign_in_instead_of_a_silent_failure(): void
    {
        $t = $this->tenant('a');

        $this->postJson(route('customer.cart.store'), ['product_id' => $t['product']->id])->assertUnauthorized();
    }

    public function test_pages_load_the_motion_files_and_the_opening_screen(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('id="ts-splash"', false)
            ->assertSee('js/ts-motion.js', false)
            ->assertSee('css/ts-motion.css', false);
    }
}
