<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** إيقاف المتجر: بديل آمن للحذف يحفظ المحفظة والطلبات والسجلات. */
class SellerSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sellerUser;

    private SellerProfile $vendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->sellerUser = User::factory()->create(['role' => 'seller']);
        $this->vendor = SellerProfile::create(['user_id' => $this->sellerUser->id, 'store_name' => 'متجر', 'status' => 'approved', 'commission_rate' => 10]);
        $this->vendor->walletOrCreate()->update(['balance' => 70000]);
        $this->product = Product::create([
            'vendor_id' => $this->vendor->id, 'category_id' => Category::create(['name' => 'c', 'slug' => 'c'])->id,
            'name' => 'منتج', 'slug' => 'p-'.Str::random(6), 'sku' => 'S-'.Str::random(6), 'price' => 10000,
            'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    private function suspend(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sellers.suspend', $this->vendor), ['reason' => 'مخالفة سياسة الإرجاع'])
            ->assertSessionHas('success');
    }

    public function test_admin_suspends_store_with_reason_and_seller_is_notified(): void
    {
        $this->suspend();

        $v = $this->vendor->fresh();
        $this->assertSame('suspended', $v->status);
        $this->assertSame('مخالفة سياسة الإرجاع', $v->suspension_reason);
        $this->assertNotNull($v->suspended_at);
        $this->assertSame(70000, $v->wallet()->first()->balance, 'المحفظة لا تُمس');
        $this->assertTrue(AppNotification::where('user_id', $this->sellerUser->id)->where('type', 'store_suspended')->exists());
    }

    public function test_suspension_requires_a_reason(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sellers.suspend', $this->vendor), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('approved', $this->vendor->fresh()->status);
    }

    public function test_suspended_store_and_products_are_hidden_from_visitors(): void
    {
        $this->suspend();
        $this->app['auth']->forgetGuards(); // زائر عادي، لا المدير الذي يرى كل شيء

        $this->get(route('products.show', $this->product))->assertNotFound();
        $this->get(route('stores.show', $this->vendor))->assertNotFound();
        $this->assertFalse(Product::publiclyVisible()->whereKey($this->product->id)->exists());
        $this->assertFalse($this->product->fresh()->isLive());
    }

    public function test_cart_with_suspended_store_product_cannot_be_checked_out(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 10000]);
        $this->suspend();

        $this->actingAs($customer)->post(route('customer.checkout.store'), [
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery',
        ]);

        $this->assertSame(0, $customer->orders()->count());
    }

    public function test_suspended_seller_can_log_in_and_sees_reason_but_cannot_add_products(): void
    {
        $this->suspend();

        $this->actingAs($this->sellerUser)->get(route('seller.dashboard'))
            ->assertOk()->assertSee('مخالفة سياسة الإرجاع');
        $this->actingAs($this->sellerUser)->get(route('seller.products.create'))->assertForbidden();
    }

    public function test_admin_reactivates_store_and_products_are_visible_again(): void
    {
        $this->suspend();

        $this->actingAs($this->admin)->post(route('admin.sellers.unsuspend', $this->vendor))->assertSessionHas('success');

        $v = $this->vendor->fresh();
        $this->assertSame('approved', $v->status);
        $this->assertNull($v->suspension_reason);
        $this->app['auth']->forgetGuards();
        $this->get(route('products.show', $this->product))->assertOk();
    }

    public function test_suspended_sellers_appear_in_admin_list(): void
    {
        $this->suspend();

        $this->actingAs($this->admin)->get(route('admin.sellers.index'))->assertOk()->assertSee('متجر')->assertSee('موقوف');
    }

    public function test_api_admin_can_suspend_and_unsuspend(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/sellers/{$this->vendor->id}/suspend", ['reason' => 'سبب'])->assertOk();
        $this->assertSame('suspended', $this->vendor->fresh()->status);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/sellers/{$this->vendor->id}/unsuspend")->assertOk();
        $this->assertSame('approved', $this->vendor->fresh()->status);
    }

    public function test_api_admin_lists_suspended_sellers_with_reason(): void
    {
        $this->suspend();

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sellers?status=suspended')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->vendor->id)
            ->assertJsonPath('data.0.status', 'suspended')
            ->assertJsonPath('data.0.suspension_reason', 'مخالفة سياسة الإرجاع');
    }

    public function test_api_admin_delete_is_refused_for_seller_with_balance(): void
    {
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/v1/admin/sellers/{$this->vendor->id}")->assertStatus(422);

        $this->assertNotNull($this->vendor->fresh());
        $this->assertSame(70000, $this->vendor->wallet()->first()->balance);
    }
}
