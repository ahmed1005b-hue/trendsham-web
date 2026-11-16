<?php

namespace Tests\Feature\Returns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

class SellerAdminReturnWebTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private Order $order;

    private OrderItem $line;

    private SellerProfile $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->customer();
        $this->order = $this->order($this->customer, 3_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 3, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function requested(int $quantity = 1): ReturnRequest
    {
        return $this->service()->create($this->customer, $this->order, [$this->line->id => $quantity], 'damaged', 'وصل مكسوراً');
    }

    private function escalated(): ReturnRequest
    {
        $request = $this->requested();
        $this->service()->sellerReject($this->seller, $request, 'لا يوجد عيب');

        return $this->service()->dispute($this->customer, $request->fresh(), 'العيب واضح');
    }

    private function received(): ReturnRequest
    {
        $request = $this->requested();
        $this->service()->sellerApprove($this->seller, $request);

        return $this->service()->confirmReceived($this->seller->user, $request->fresh());
    }

    public function test_seller_index_lists_only_own_returns_and_filters_by_status(): void
    {
        $mine = $this->requested();
        $approved = $this->requested();
        $this->service()->sellerApprove($this->seller, $approved);

        $otherCustomer = $this->customer();
        $otherOrder = $this->deliver($this->order($otherCustomer, 1_000_000));
        $other = $this->service()->create($otherCustomer, $otherOrder, [$otherOrder->items()->first()->id => 1], 'damaged', null);

        $this->actingAs($this->seller->user);
        $this->get(route('seller.returns.index'))->assertOk()
            ->assertSee($mine->number)->assertSee($approved->number)->assertDontSee($other->number);

        $this->get(route('seller.returns.index', ['status' => 'approved']))->assertOk()
            ->assertSee($approved->number)->assertDontSee($mine->number);
    }

    public function test_seller_show_renders_lines_and_timeline(): void
    {
        $request = $this->requested(2);

        $this->actingAs($this->seller->user)->get('/seller/returns/'.$request->id)->assertOk()
            ->assertSee($request->number)
            ->assertSee($this->line->product_name)
            ->assertSee('المنتج تالف');
    }

    public function test_seller_approves_with_optional_note(): void
    {
        $request = $this->requested();

        $this->actingAs($this->seller->user)->post(route('seller.returns.approve', $request), ['note' => 'أرسله لنا'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_seller_reject_requires_a_note(): void
    {
        $request = $this->requested();
        $this->actingAs($this->seller->user);

        $this->post(route('seller.returns.reject', $request), ['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame('requested', $request->fresh()->status);

        $this->post(route('seller.returns.reject', $request), ['note' => 'المنتج سليم'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_seller_confirms_receipt_after_approval(): void
    {
        $request = $this->requested();
        $this->service()->sellerApprove($this->seller, $request);

        $this->actingAs($this->seller->user)->post(route('seller.returns.received', $request))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_seller_action_failure_flashes_the_service_message(): void
    {
        $request = $this->requested();
        $this->service()->sellerApprove($this->seller, $request);

        $this->actingAs($this->seller->user)->post(route('seller.returns.approve', $request))
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_seller_dashboard_badge_counts_only_own_requested_returns(): void
    {
        $this->requested();
        $approved = $this->requested();
        $this->service()->sellerApprove($this->seller, $approved);

        $html = $this->actingAs($this->seller->user)->get(route('seller.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/ts-action-count">1<span class="visually-hidden"> بانتظار ردك/u', $html);
    }

    public function test_customer_cannot_use_seller_return_routes(): void
    {
        $request = $this->requested();

        $this->actingAs($this->customer)->get('/seller/returns/'.$request->id)->assertForbidden();
        $this->post('/seller/returns/'.$request->id.'/approve')->assertForbidden();
        $this->assertSame('requested', $request->fresh()->status);
    }

    public function test_admin_tabs_filter_escalated_awaiting_refund_and_all(): void
    {
        $requested = $this->requested();
        $escalated = $this->escalated();
        $received = $this->received();

        $this->actingAs($this->admin);
        $this->get(route('admin.returns.index'))->assertOk()
            ->assertSee($escalated->number)->assertDontSee($received->number)->assertDontSee($requested->number);
        $this->get(route('admin.returns.index', ['tab' => 'awaiting_refund']))->assertOk()
            ->assertSee($received->number)->assertDontSee($escalated->number);
        $this->get(route('admin.returns.index', ['tab' => 'all']))->assertOk()
            ->assertSee($received->number)->assertSee($escalated->number)->assertSee($requested->number);
    }

    public function test_admin_show_displays_shipping_fee_and_refund_due(): void
    {
        $request = $this->received();

        $this->actingAs($this->admin)->get('/admin/returns/'.$request->id)->assertOk()
            ->assertSee($request->number)
            ->assertSee('5,000')
            ->assertSee('10,000');
    }

    public function test_admin_approves_and_rejects_escalated_returns_with_note(): void
    {
        $toApprove = $this->escalated();
        $toReject = $this->escalated();
        $this->actingAs($this->admin);

        $this->post(route('admin.returns.reject', $toReject), ['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame('escalated', $toReject->fresh()->status);

        $this->post(route('admin.returns.reject', $toReject), ['note' => 'قرار الإدارة'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('closed', $toReject->fresh()->status);

        $this->post(route('admin.returns.approve', $toApprove), ['note' => 'مقبول'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('approved', $toApprove->fresh()->status);
    }

    public function test_admin_confirms_receipt(): void
    {
        $request = $this->requested();
        $this->service()->sellerApprove($this->seller, $request);

        $this->actingAs($this->admin)->post(route('admin.returns.received', $request))->assertRedirect()->assertSessionHas('success');

        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_admin_records_refund_converting_decimal_amount_to_minor_units(): void
    {
        $request = $this->received();

        $this->actingAs($this->admin)->post(route('admin.returns.refund', $request), [
            'amount' => '10000', 'method' => 'bank_transfer', 'reference' => 'TRX-1', 'note' => 'تم',
        ])->assertRedirect()->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('refunded', $request->status);
        $this->assertSame(1_000_000, (int) $request->refund->amount);
        $this->assertSame('TRX-1', $request->refund->reference);
        $this->get(route('admin.returns.show', $request))->assertOk()
            ->assertSee('(حوالة بنكية', false)
            ->assertDontSee('(bank_transfer', false);
    }

    public function test_admin_records_refund_with_extra_shipping(): void
    {
        $request = $this->received();

        $this->actingAs($this->admin)->post(route('admin.returns.refund', $request), [
            'amount' => '12000', 'method' => 'cash', 'extra_shipping' => '2000', 'note' => 'تعويض الشحن',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1_200_000, (int) $request->fresh()->refund->amount);
    }

    public function test_refund_failures_flash_service_message_and_validate_method(): void
    {
        $request = $this->received();
        $this->actingAs($this->admin);

        $this->post(route('admin.returns.refund', $request), ['amount' => '10000', 'method' => 'paypal'])->assertSessionHasErrors('method');
        $this->post(route('admin.returns.refund', $request), ['amount' => '99999', 'method' => 'cash'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_non_admin_cannot_use_admin_return_routes(): void
    {
        $request = $this->escalated();

        foreach ([$this->customer, $this->seller->user] as $user) {
            $this->actingAs($user);
            $this->get('/admin/returns')->assertForbidden();
            $this->get('/admin/returns/'.$request->id)->assertForbidden();
            $this->post('/admin/returns/'.$request->id.'/approve')->assertForbidden();
            $this->post('/admin/returns/'.$request->id.'/refund', ['amount' => 1, 'method' => 'cash'])->assertForbidden();
        }
        $this->assertSame('escalated', $request->fresh()->status);
    }
}
