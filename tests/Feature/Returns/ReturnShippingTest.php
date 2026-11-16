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

/**
 * أجرة التوصيل التلقائية (القسم 5 و10.3): تُرد فقط حين تُرجع كل وحدات طلب المتجر الواحد بخطأ البائع،
 * وتُحمَّل عند الاستلام على المرتجع الذي يُكمل ذلك، ومرة واحدة لكل طلب.
 */
class ReturnShippingTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private const FEE = 500_000;

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
        $this->order = $this->order($this->customer, 3_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 3, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function create(int $quantity, string $reason): ReturnRequest
    {
        return $this->service()->create($this->customer, $this->order, [$this->line->id => $quantity], $reason, null);
    }

    private function receive(ReturnRequest $request): ReturnRequest
    {
        $this->service()->confirmReceived($this->seller->user, $this->service()->sellerApprove($this->seller, $request));

        return $request->fresh();
    }

    public function test_mixed_reasons_never_refund_shipping(): void
    {
        $this->receive($this->create(1, 'changed_mind'));
        $completing = $this->receive($this->create(2, 'damaged'));

        $this->assertSame(0, $completing->shipping_refund);
        $this->assertSame(2_000_000, $completing->refund_amount);
    }

    public function test_shipping_goes_to_the_request_whose_receipt_completes_the_order(): void
    {
        $first = $this->create(1, 'damaged');
        $second = $this->receive($this->create(2, 'not_as_described'));

        // الأول ما زال مفتوحاً فلا يُحتسب
        $this->assertSame(0, $second->shipping_refund);

        $first = $this->receive($first);
        $this->assertSame(self::FEE, $first->shipping_refund);
        $this->assertSame(1_000_000 + self::FEE, $first->refund_amount);
        $this->assertSame(0, $second->fresh()->shipping_refund);
    }

    public function test_withdrawn_sibling_does_not_cover_its_units(): void
    {
        $sibling = $this->create(1, 'damaged');
        $request = $this->create(2, 'damaged');
        $this->service()->withdraw($this->customer, $sibling);

        $this->assertSame(0, $this->receive($request)->shipping_refund);
    }

    public function test_admin_extra_shipping_blocks_a_later_automatic_shipping(): void
    {
        $first = $this->receive($this->create(1, 'damaged'));
        $this->service()->recordRefund($this->admin, $first, $first->refund_amount + self::FEE, 'cash', null, 'شحن', self::FEE);

        $completing = $this->receive($this->create(2, 'damaged'));

        $this->assertSame(0, $completing->shipping_refund);
        $this->assertSame(2_000_000, $completing->refund_amount);
    }

    public function test_shipping_on_a_closed_request_does_not_count_as_refunded(): void
    {
        // بيانات قديمة: مرتجع مغلق يحمل shipping_refund لم يُصرف قط
        $closed = $this->create(1, 'damaged');
        $closed->update(['shipping_refund' => self::FEE]);
        $this->service()->withdraw($this->customer, $closed);

        $request = $this->receive($this->create(1, 'changed_mind'));
        $refund = $this->service()->recordRefund($this->admin, $request, $request->refund_amount + self::FEE, 'cash', null, 'شحن', self::FEE);

        $this->assertSame($request->refund_amount + self::FEE, $refund->amount);
    }
}
