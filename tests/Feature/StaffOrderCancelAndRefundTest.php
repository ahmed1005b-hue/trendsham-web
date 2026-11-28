<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StaffActivity;
use App\Models\User;
use App\Services\CustomerOrderCanceller;
use App\Services\StaffOrderStatusChanger;
use App\Shipping\ParcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * فريق المنصة والطلبات: الحالة للأمام فقط، الإلغاء قبل الشحن وبسبب، والطلب المدفوع
 * لا يُلغى إلا من المدير مع تسجيل رد المال (الخيار ب).
 */
class StaffOrderCancelAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private User $sellerUser;

    private SellerProfile $vendor;

    private Product $product;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->employee = User::factory()->create([
            'role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'employee_permissions' => ['orders'],
        ]);
        $this->sellerUser = User::factory()->create(['role' => 'seller']);
        $this->vendor = SellerProfile::create(['user_id' => $this->sellerUser->id, 'store_name' => 'متجر الورد', 'status' => 'approved', 'commission_rate' => 10]);
        $this->product = Product::create([
            'vendor_id' => $this->vendor->id, 'category_id' => Category::create(['name' => 'قسم', 'slug' => 'c-'.Str::random(5)])->id,
            'name' => 'منتج', 'slug' => 'p-'.Str::random(6), 'sku' => 'S-'.Str::random(8), 'price' => 10000,
            'stock_quantity' => 8, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    /** طلب بقطعتين؛ «مدفوع بالبطاقة» أو «عند الاستلام». */
    private function order(bool $paidByCard, string $status = 'pending'): Order
    {
        $order = Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)), 'user_id' => $this->customer->id,
            'status' => $status, 'payment_status' => $paidByCard ? 'paid' : 'unpaid',
            'subtotal' => 20000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 20000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $this->product->id, 'vendor_id' => $this->vendor->id,
            'product_name' => 'منتج', 'unit_price' => 10000, 'quantity' => 2, 'subtotal' => 20000,
        ]);
        Payment::create([
            'order_id' => $order->id, 'method' => $paidByCard ? 'card' : 'cash_on_delivery',
            'status' => $paidByCard ? 'paid' : 'pending', 'amount' => 20000,
        ]);
        $this->product->decrement('stock_quantity', 2);

        return $order;
    }

    public function test_staff_must_give_a_reason_and_the_customer_and_seller_hear_it(): void
    {
        $order = $this->order(paidByCard: false);

        $this->actingAs($this->employee)->put(route('employee.orders.updateStatus', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($this->employee)->put(route('employee.orders.updateStatus', $order), ['status' => 'cancelled', 'reason' => 'نفدت الكمية'])
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('body', 'like', '%نفدت الكمية%')->exists());
        $this->assertTrue(AppNotification::where('user_id', $this->sellerUser->id)->where('type', 'seller_order_updated')
            ->where('body', 'like', '%نفدت الكمية%')->exists());
        $this->assertSame("ألغى الطلب {$order->order_number} — السبب: نفدت الكمية",
            StaffActivity::where('action', 'order.cancel')->sole()->description());
    }

    public function test_staff_move_orders_forward_only_and_cannot_pick_paid(): void
    {
        $order = $this->order(paidByCard: false, status: 'processing');
        $order->shipments()->update(['status' => 'processing']);

        $this->actingAs($this->admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'shipped'])->assertSessionHas('success');
        $this->actingAs($this->admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'processing'])->assertSessionHas('error');
        $this->actingAs($this->admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'paid'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertUnprocessable();

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_shipped_order_is_not_cancelled_by_staff(): void
    {
        $order = $this->order(paidByCard: false, status: 'shipped');
        $order->shipments()->update(['status' => 'shipped']);

        $this->actingAs($this->admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'cancelled', 'reason' => 'ضاع'])
            ->assertSessionHas('error', __(StaffOrderStatusChanger::SHIPPED_CANCEL_MESSAGE));
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(6, $this->product->fresh()->stock_quantity, 'البضاعة خرجت فلا تعود للمخزون');
    }

    public function test_a_paid_order_is_only_cancelled_by_the_admin_with_the_refund_recorded(): void
    {
        $order = $this->order(paidByCard: true);

        // الموظف والمدير لا يلغيانه إلغاءً عادياً
        $this->actingAs($this->employee)->put(route('employee.orders.updateStatus', $order), ['status' => 'cancelled', 'reason' => 'x'])
            ->assertSessionHas('error', __(StaffOrderStatusChanger::PAID_CANCEL_MESSAGE));
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'x'])
            ->assertUnprocessable();
        $this->assertSame('pending', $order->fresh()->status);

        // الموظف لا يصل لزر رد المال
        $this->actingAs($this->employee)->post(route('admin.orders.cancelRefund', $order), [
            'reason' => 'x', 'refund_method' => 'card',
        ])->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.orders.cancelRefund', $order), ['reason' => 'طلب الزبون'])
            ->assertSessionHasErrors('refund_method');

        $this->actingAs($this->admin)->post(route('admin.orders.cancelRefund', $order), [
            'reason' => 'طلب الزبون', 'refund_method' => 'bank_transfer', 'refund_note' => 'TRX-55',
        ])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refunded', $order->payments()->sole()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);

        $log = StaffActivity::where('action', 'order.refund_cancel')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame("ألغى الطلب المدفوع {$order->order_number} وردّ 200 ل.س للزبون (تحويل بنكي) — السبب: طلب الزبون", $log->description());
        $this->assertSame('TRX-55', $log->properties['note']);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('body', 'like', '%رُدّ لك 200 ل.س%')->exists());

        // يبقى في سجل البائع (وصله قبل الإلغاء)
        $this->actingAs($this->sellerUser)->get(route('seller.orders.show', $order))->assertOk();
    }

    public function test_the_order_page_offers_the_refund_form_to_the_admin_only(): void
    {
        $order = $this->order(paidByCard: true);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('إلغاء الطلب المدفوع وتسجيل رد المال')
            ->assertSee(route('admin.orders.cancelRefund', $order))
            ->assertDontSee('<option value="paid"', false);

        $this->actingAs($this->employee)->get(route('employee.orders.show', $order))
            ->assertOk()->assertDontSee(route('admin.orders.cancelRefund', $order))
            ->assertSee(__(StaffOrderStatusChanger::PAID_CANCEL_MESSAGE));
    }

    public function test_the_app_cancels_a_paid_order_with_the_refund_too(): void
    {
        $order = $this->order(paidByCard: true);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/orders/{$order->id}/cancel-refund", [
            'reason' => 'طلب الزبون', 'refund_method' => 'card',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.payment_status', 'refunded');
    }

    public function test_the_customer_cannot_cancel_a_paid_order_alone(): void
    {
        $order = $this->order(paidByCard: true);

        $this->actingAs($this->customer)->post(route('customer.orders.cancel', $order))->assertStatus(409);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(409)->assertJsonPath('message', __(CustomerOrderCanceller::PAID_NEEDS_SUPPORT));
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('data.can_be_cancelled', false);
        $this->actingAs($this->customer)->get(route('customer.orders.show', $order))
            ->assertOk()->assertSee(__(CustomerOrderCanceller::PAID_NEEDS_SUPPORT));

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_seller_cannot_cancel_the_last_parcel_of_a_paid_order(): void
    {
        $order = $this->order(paidByCard: true);

        $this->actingAs($this->sellerUser)->post(route('seller.orders.updateStatus', $order), ['status' => 'cancelled'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(6, $this->product->fresh()->stock_quantity);
    }

    public function test_the_admin_parcel_tool_reports_errors_in_the_app_and_keeps_the_tracking_number(): void
    {
        $order = $this->order(paidByCard: true);
        $parcel = $order->shipments()->first() ?? app(ParcelService::class)->ensureFor($order)->first();
        $parcel->update(['tracking_number' => 'KR-9']);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/shipments/{$parcel->id}", ['status' => 'cancelled'])
            ->assertUnprocessable();

        $this->assertSame('KR-9', $parcel->fresh()->tracking_number, 'رقم التتبع لا يُمسح حين لا يُرسل');
        $this->assertSame('pending', $order->fresh()->status);
    }
}
