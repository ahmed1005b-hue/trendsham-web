<?php

namespace Tests\Feature\Coupons;

use App\Coupons\CouponEligibility;
use App\Coupons\CouponIssuer;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** إصلاحات المراجعة المستقلة والمراجعة الأمنية للمرحلة 1. */
class CouponReviewFixesTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    private function cartFor(User $customer, int $price = 10_000_000)
    {
        $product = $this->product($this->vendor(), $price);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price]);

        return $product;
    }

    private function payload(): array
    {
        return ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true];
    }

    // #2 الموقع: كوبون اختاره العميل وصار غير صالح لحظة التأكيد → لا طلب بسعر لم يره
    public function test_web_order_is_not_placed_when_chosen_coupon_became_invalid(): void
    {
        $customer = $this->customer();
        $this->cartFor($customer);
        $coupon = $this->walletCoupon($this->campaign(), $customer);
        $coupon->campaign->update(['status' => 'paused']); // أوقفه المدير بعد أن اختاره العميل

        $this->actingAs($customer)->withSession(['checkout_coupon' => $coupon->id])
            ->post(route('customer.checkout.store'), $this->payload())
            ->assertSessionHas('error');

        $this->assertSame(0, $customer->orders()->count());
    }

    public function test_web_checkout_page_tells_customer_when_chosen_coupon_was_dropped(): void
    {
        $customer = $this->customer();
        $this->cartFor($customer);
        $coupon = $this->walletCoupon($this->campaign(['title' => 'كوبون موقوف']), $customer);
        $coupon->campaign->update(['status' => 'paused']);

        $this->actingAs($customer)->withSession(['checkout_coupon' => $coupon->id])
            ->get(route('customer.checkout.show'))
            ->assertOk()->assertSee('الكوبون الذي اخترته لم يعد صالحاً');
    }

    // #1 التطبيق: الملخص يخبر بسبب سقوط الكوبون المختار (حتى لا يعلق العميل)
    public function test_api_summary_explains_dropped_explicit_coupon(): void
    {
        $customer = $this->customer();
        $this->cartFor($customer);
        $coupon = $this->walletCoupon($this->campaign(), $customer);
        $coupon->update(['status' => 'used']);

        $this->actingAs($customer, 'sanctum')->getJson("/api/v1/checkout/summary?customer_coupon_id={$coupon->id}")
            ->assertOk()->assertJsonPath('coupon', null)
            ->assertJsonPath('coupon_dropped_reason', 'استُخدم في طلب سابق');
    }

    // #3 فتح الصفحة لا يقفل سجل الحملة إن كان لدى العميل نسخة متاحة أصلاً
    public function test_sync_does_not_lock_campaign_when_customer_already_has_copy(): void
    {
        $customer = $this->customer();
        $this->campaign();
        app(CouponIssuer::class)->syncFor($customer);

        $locks = [];
        DB::listen(function ($q) use (&$locks) {
            if (str_contains(strtolower($q->sql), 'for update')) {
                $locks[] = $q->sql;
            }
        });
        app(CouponIssuer::class)->syncFor($customer);

        $this->assertSame([], $locks);
    }

    // #4 النظام القديم بالرموز لم يعد ظاهراً
    public function test_old_code_coupons_are_not_shown_on_product_page(): void
    {
        $product = $this->product($this->vendor());
        Coupon::create(['code' => 'OLDCODE', 'type' => 'fixed', 'value' => 1000, 'min_order_amount' => 0, 'is_active' => true,
            'seller_profile_id' => $product->vendor_id]);

        $this->get(route('products.show', $product))->assertOk()->assertDontSee('OLDCODE');
    }

    public function test_old_admin_coupon_pages_redirect_to_wallet_campaigns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/coupons')->assertRedirect(route('admin.coupon-campaigns.index'));
    }

    // #6 أسعار كبيرة بالليرة: لا تعطّل الدفع بتجاوز حد الأرقام
    public function test_huge_amounts_do_not_overflow_line_allocation(): void
    {
        $vendor = $this->vendor();
        $c = $this->walletCoupon($this->campaign(['type' => 'fixed', 'value' => 4_000_000_000]), $this->customer());
        $lines = collect([$this->line($this->product($vendor, 5_000_000_000)), $this->line($this->product($vendor, 5_000_000_000))]);

        $r = app(CouponEligibility::class)->check($c->fresh('campaign.targets'), $lines, 15000);

        $this->assertSame(4_000_000_000, array_sum($r->lineDiscounts));
    }

    // #7 كوبون العملاء الجدد للطلب الأول فقط
    public function test_new_customer_coupon_is_not_usable_after_first_order(): void
    {
        $customer = $this->customer();
        $coupon = $this->walletCoupon($this->campaign(['audience' => 'new_customers']), $customer);
        Order::create(['order_number' => 'TS-'.Str::random(6), 'user_id' => $customer->id, 'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal' => 1, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 1, 'shipping_name' => 'a', 'shipping_phone' => '0', 'shipping_address' => 'b']);

        $r = app(CouponEligibility::class)->check($coupon->fresh('campaign.targets'), collect([$this->line($this->product($this->vendor()))]), 15000);

        $this->assertFalse($r->ok);
        $this->assertSame('للطلب الأول فقط', $r->reason);
    }

    // #8 (أمني) حساب البائع/المدير لا يُنشئ طلب شراء من التطبيق
    public function test_non_customer_cannot_checkout_via_api(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller, 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertForbidden();
        $this->actingAs($seller, 'sanctum')->getJson('/api/v1/checkout/summary')->assertForbidden();
    }
}
