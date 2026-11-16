<?php

namespace Tests\Feature\Returns;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRefund;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

/** تسجيل المدير للاسترداد اليدوي بعد استلام المرتجع. */
class RecordRefundTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private User $admin;

    private Order $order;

    private OrderItem $line;

    private SellerProfile $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->customer();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->order = $this->order($this->customer, 5_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 5, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function received(int $quantity = 1): ReturnRequest
    {
        $request = $this->service()->create($this->customer, $this->order, [$this->line->id => $quantity], 'changed_mind', null);
        $request = $this->service()->sellerApprove($this->seller, $request);

        return $this->service()->confirmReceived($this->admin, $request);
    }

    private function assertIllegal(callable $fn): void
    {
        try {
            $fn();
            $this->fail('expected DomainException');
        } catch (\DomainException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_admin_records_refund_and_request_becomes_refunded(): void
    {
        $request = $this->received();

        $refund = $this->service()->recordRefund($this->admin, $request, $request->refund_amount, 'bank_transfer', 'TX-1', 'تم');

        $this->assertSame($request->refund_amount, $refund->amount);
        $this->assertSame($this->admin->id, $refund->recorded_by);
        $request->refresh();
        $this->assertSame('refunded', $request->status);
        $event = $request->events()->latest('id')->first();
        $this->assertSame(['admin', 'refunded'], [$event->actor_role, $event->type]);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_refunded')->exists());
    }

    public function test_amount_above_refund_amount_is_refused(): void
    {
        $request = $this->received();

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, $request->refund_amount + 1, 'cash', null, null));
        $this->assertSame(0, ReturnRefund::count());
        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_invalid_amount_and_method_are_refused(): void
    {
        $request = $this->received();

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, 0, 'cash', null, null));
        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, 100, 'bitcoin', null, null));
        $this->assertSame(0, ReturnRefund::count());
    }

    public function test_second_refund_is_refused_and_writes_no_row(): void
    {
        $request = $this->received();
        $this->service()->recordRefund($this->admin, $request, 100, 'cash', null, null);

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, 100, 'cash', null, null));
        $this->assertSame(1, ReturnRefund::count());
    }

    public function test_refund_only_from_received(): void
    {
        $request = $this->service()->create($this->customer, $this->order, [$this->line->id => 1], 'changed_mind', null);

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, 100, 'cash', null, null));
        $this->assertSame(0, ReturnRefund::count());
    }

    public function test_only_admin_can_record(): void
    {
        $request = $this->received();

        $this->expectException(AccessDeniedHttpException::class);
        $this->service()->recordRefund($this->customer, $request, 100, 'cash', null, null);
    }

    public function test_extra_shipping_needs_note_and_cap(): void
    {
        $request = $this->received();
        $fee = (int) $this->order->shipping_fee;
        $over = $request->refund_amount + $fee;

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, $over, 'cash', null, '  ', $fee));
        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, $over + 1, 'cash', null, 'شحن', $fee + 1));
        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, $over + 1, 'cash', null, 'شحن', $fee));
        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, 100, 'cash', null, 'شحن', -1));
        $this->assertSame(0, ReturnRefund::count());

        $refund = $this->service()->recordRefund($this->admin, $request, $over, 'cash', null, 'شحن', $fee);
        $this->assertSame($over, $refund->amount);
    }

    public function test_extra_shipping_refused_when_amount_does_not_exceed_refund_due(): void
    {
        $request = $this->received();

        foreach ([$request->refund_amount, $request->refund_amount - 1] as $amount) {
            try {
                $this->service()->recordRefund($this->admin, $request, $amount, 'cash', null, 'شحن', 100);
                $this->fail('expected DomainException');
            } catch (\DomainException $e) {
                $this->assertSame('الشحن الإضافي يستلزم مبلغاً أكبر من مبلغ الاسترداد المستحق.', $e->getMessage());
            }
        }
        $this->assertSame(0, ReturnRefund::count());
        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_extra_shipping_allowed_once_per_order(): void
    {
        $first = $this->received();
        $second = $this->received();
        $fee = (int) $this->order->shipping_fee;

        $this->service()->recordRefund($this->admin, $first, $first->refund_amount + $fee, 'cash', null, 'شحن', $fee);

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $second, $second->refund_amount + 1, 'cash', null, 'شحن', 1));
        $this->assertSame(1, ReturnRefund::count());

        $plain = $this->service()->recordRefund($this->admin, $second, $second->refund_amount, 'cash', null, null);
        $this->assertSame($second->refund_amount, $plain->amount);
    }

    public function test_extra_shipping_refused_when_automatic_shipping_refund_exists(): void
    {
        $request = $this->received();
        $request->update(['shipping_refund' => (int) $this->order->shipping_fee]);

        $this->assertIllegal(fn () => $this->service()->recordRefund($this->admin, $request, $request->refund_amount + 1, 'cash', null, 'شحن', 1));
        $this->assertSame(0, ReturnRefund::count());
    }
}
