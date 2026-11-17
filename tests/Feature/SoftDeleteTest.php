<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\DailyDeal;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** الحذف المؤقت: المحذوف يختفي ويبقى سجله، ويسترجعه المدير من «المحذوفات». */
class SoftDeleteTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_seller_deleting_a_sold_product_keeps_its_history(): void
    {
        $t = $this->tenant('a');
        $review = Review::create(['product_id' => $t['product']->id, 'user_id' => $t['customer']->id,
            'order_item_id' => $t['orderItem']->id, 'rating' => 5, 'comment' => 'ممتاز']);
        Favorite::create(['user_id' => $t['customer']->id, 'product_id' => $t['product']->id]);

        $this->actingAs($t['seller'])->delete(route('seller.products.destroy', $t['product']))->assertRedirect();

        $this->assertSoftDeleted($t['product']);
        $this->get(route('products.show', $t['product']->slug))->assertNotFound();
        $this->assertModelExists($review);
        $this->assertSame($t['product']->id, $t['orderItem']->fresh()->product?->id, 'الطلب القديم يبقى يرى المنتج');
        $this->assertSame(0, $t['customer']->cartItems()->count(), 'السلة تُفرّغ منه');
        $this->assertSame(0, Favorite::where('product_id', $t['product']->id)->count());
        $this->assertSoftDeleted($t['question']);
    }

    public function test_deleted_values_are_free_to_reuse_and_come_back_on_restore(): void
    {
        $t = $this->tenant('a');
        $slug = $t['product']->slug;
        $t['product']->delete();

        $copy = $t['product']->replicate(['deleted_at']);
        $copy->slug = $slug;
        $copy->sku = 'NEW-SKU';
        $copy->save();
        $this->assertModelExists($copy);

        $copy->delete();
        $t['product']->fresh()->restore();
        $this->assertSame($slug, $t['product']->fresh()->slug);
        $this->assertSame(1, Product::where('slug', $slug)->count());
    }

    public function test_restored_coupon_gets_a_new_code_when_its_old_one_was_taken(): void
    {
        $old = Coupon::create(['code' => 'SALE', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        $old->delete();
        Coupon::create(['code' => 'SALE', 'type' => 'percentage', 'value' => 20, 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('admin.trash.restore', ['coupons', $old->id]))
            ->assertSessionHas('success');

        $this->assertSame('SALE-'.$old->id, $old->fresh()->code);
    }

    public function test_admin_sees_deleted_items_and_restores_a_product_with_its_category_and_questions(): void
    {
        $t = $this->tenant('a');
        $category = $t['product']->category;
        $t['product']->delete();
        $category->delete();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.trash.index', ['type' => 'products']))
            ->assertOk()->assertSee($t['product']->name)->assertSee($t['store']->store_name);

        $this->actingAs($admin)->post(route('admin.trash.restore', ['products', $t['product']->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($t['product']);
        $this->assertNotSoftDeleted($t['question']);
        $this->assertNotSoftDeleted($category);
        $this->get(route('products.show', $t['product']->fresh()->slug))->assertOk();
    }

    public function test_a_deal_cannot_come_back_before_its_product(): void
    {
        $t = $this->tenant('a');
        $deal = DailyDeal::create(['product_id' => $t['product']->id, 'discount_percentage' => 10,
            'starts_at' => now(), 'ends_at' => now()->addDay(), 'is_active' => true]);
        $t['product']->delete();
        $this->assertSoftDeleted($deal);

        $this->actingAs($this->admin())->post(route('admin.trash.restore', ['daily_deals', $deal->id]))
            ->assertSessionHas('error');
        $this->assertSoftDeleted($deal);
    }

    public function test_only_admins_open_the_trash(): void
    {
        $t = $this->tenant('a');

        $this->actingAs($t['seller'])->get(route('admin.trash.index'))->assertForbidden();
        $this->actingAs($t['seller'])->post(route('admin.trash.restore', ['products', $t['product']->id]))->assertForbidden();
    }

    public function test_seller_cannot_put_a_product_in_a_deleted_category(): void
    {
        $t = $this->tenant('a');
        $gone = Category::create(['name' => 'Gone', 'slug' => 'gone', 'is_active' => true]);
        $gone->delete();

        $this->actingAs($t['seller'])->post(route('seller.products.store'), [
            'name' => 'New', 'category_id' => $gone->id, 'price' => 100, 'stock_quantity' => 1, 'description' => 'x',
        ])->assertSessionHasErrors('category_id');
    }
}
