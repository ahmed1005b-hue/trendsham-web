<?php

namespace Tests\Feature\Returns;

use App\Accounting\LedgerType;
use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponRedemption;
use App\Coupons\Models\CustomerCoupon;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\InventoryMovement;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use App\Returns\ReturnSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

/** أثر تأكيد استلام المرتجع: مخزون ومحفظة ودفتر ونقاط للوحدات المرتجعة فقط ومرة واحدة، وتوافقه مع الإلغاء. */
class ReturnSettlementTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private const OPEN_RETURN_MESSAGE = 'لا يمكن إلغاء الطلب وعليه طلب إرجاع مفتوح.';

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->customer();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    /** طلب مسلَّم ببند واحد من $quantity وحدات بمجموع $subtotal ونسبة عمولة $commission */
    private function deliveredOrder(int $subtotal, int $quantity, int $commission = 10, ?Order $order = null, ?int $variantId = null): Order
    {
        $order ??= $this->order($this->customer, $subtotal);
        $line = $order->items()->first();
        SellerProfile::whereKey($line->vendor_id)->update(['commission_rate' => $commission]);
        $line->update(['quantity' => $quantity, 'unit_price' => intdiv($subtotal, $quantity), 'variant_id' => $variantId]);

        return $this->deliver($order);
    }

    private function lineOf(Order $order): OrderItem
    {
        return $order->items()->firstOrFail();
    }

    private function sellerOf(Order $order): SellerProfile
    {
        return SellerProfile::findOrFail($this->lineOf($order)->vendor_id);
    }

    private function approvedReturn(Order $order, int $quantity, string $reason = 'changed_mind'): ReturnRequest
    {
        $request = $this->service()->create($this->customer, $order, [$this->lineOf($order)->id => $quantity], $reason, null);

        return $this->service()->sellerApprove($this->sellerOf($order), $request);
    }

    private function receivedReturn(Order $order, int $quantity, string $reason = 'changed_mind'): ReturnRequest
    {
        return $this->service()->confirmReceived($this->sellerOf($order)->user, $this->approvedReturn($order, $quantity, $reason));
    }

    private function walletBalance(SellerProfile $seller): int
    {
        return (int) $seller->wallet()->value('balance');
    }

    private function walletDebits(SellerProfile $seller)
    {
        return WalletTransaction::where('wallet_id', $seller->wallet()->value('id'))->where('type', 'debit')->orderBy('id')->get();
    }

    private function ledgerSum(SellerProfile $seller, string $type, string $column): float
    {
        return round((float) LedgerEntry::where('seller_id', $seller->user_id)->where('entry_type', $type)->sum($column), 2);
    }

    private function returnMovements(int $productId)
    {
        return InventoryMovement::where('product_id', $productId)->where('reason', 'return')->orderBy('id')->get();
    }

    public function test_receipt_restocks_reverses_wallet_ledger_and_points_for_returned_units_only(): void
    {
        $order = $this->order($this->customer, 5_000_000);
        $line = $this->lineOf($order);
        $variant = ProductVariant::create(['product_id' => $line->product_id, 'color' => '#000', 'color_name' => 'أسود', 'size' => 'L', 'stock_quantity' => 10, 'is_active' => true]);
        $order = $this->deliveredOrder(5_000_000, 5, 10, $order, $variant->id);
        $seller = $this->sellerOf($order);
        $line = $this->lineOf($order);

        $this->assertSame(4_500_000, $this->walletBalance($seller));
        $this->assertSame(5, $this->balance($this->customer));

        $request = $this->receivedReturn($order, 2);
        $returnItem = $request->items()->firstOrFail();

        // المخزون: وحدتان فقط للمنتج وللّون/المقاس
        $this->assertSame(52, (int) $line->product->fresh()->stock_quantity);
        $this->assertSame(12, (int) $variant->fresh()->stock_quantity);
        $movements = $this->returnMovements($line->product_id);
        $this->assertCount(1, $movements);
        $this->assertSame([2, "إرجاع #{$request->number}"], [(int) $movements[0]->quantity_change, $movements[0]->note]);

        // المحفظة: نصيب الوحدتين من الأرباح (2,000,000 − 200,000)
        $this->assertSame(2_700_000, $this->walletBalance($seller));
        $debits = $this->walletDebits($seller);
        $this->assertCount(1, $debits);
        $this->assertSame(1_800_000, (int) $debits[0]->amount);
        $this->assertSame($line->id, (int) $debits[0]->order_item_id);
        $this->assertStringContainsString(ReturnSettlement::walletMarker($returnItem->id), $debits[0]->note);

        // الدفتر: قيد استرجاع وقيد إعادة عمولة بمصدر سطر الإرجاع
        $refund = LedgerEntry::where('entry_type', LedgerType::REFUND)->where('source_type', 'return_item')->where('source_id', $returnItem->id)->sole();
        $commission = LedgerEntry::where('entry_type', LedgerType::COMM_REVERSAL)->where('source_type', 'return_item')->where('source_id', $returnItem->id)->sole();
        $this->assertEqualsWithDelta(20_000.00, (float) $refund->debit, 0.001);
        $this->assertEqualsWithDelta(2_000.00, (float) $commission->credit, 0.001);
        $this->assertSame(0, LedgerEntry::where('source_type', 'order_item_refund')->count());

        // النقاط: round(5 × 2,000,000 / 5,000,000) = 2
        $clawback = Tx::where('once_key', 'reverse_earn_return:'.$returnItem->id)->sole();
        $this->assertSame([Tx::REVERSE_EARN, -2, $order->id], [$clawback->type, $clawback->points, $clawback->order_id]);
        $this->assertSame(3, $this->balance($this->customer));
        $this->assertLedgerConsistent($this->customer);

        $this->assertNotNull($returnItem->settled_at);
        $this->assertSame(2, $line->fresh()->reversedQuantity());
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_concurrent_or_repeated_confirmation_applies_once(): void
    {
        $order = $this->deliveredOrder(5_000_000, 5);
        $seller = $this->sellerOf($order);
        $line = $this->lineOf($order);

        $request = $this->receivedReturn($order, 2);
        $returnItem = $request->items()->firstOrFail();

        app(ReturnSettlement::class)->apply($request);
        app(ReturnSettlement::class)->apply($request->fresh());
        try {
            $this->service()->confirmReceived($seller->user, $request);
            $this->fail('second confirmation must be refused');
        } catch (\DomainException) {
        }
        // حارس المحفظة والدفتر داخل البند نفسه ينجو من إعادة المحاولة
        $line->fresh()->reverseSettlementQuantity($returnItem->fresh());
        app(LoyaltyLedger::class)->reverseEarnForReturn($returnItem->fresh());

        $this->assertCount(1, $this->returnMovements($line->product_id));
        $this->assertSame(52, (int) $line->product->fresh()->stock_quantity);
        $this->assertCount(1, $this->walletDebits($seller));
        $this->assertSame(2_700_000, $this->walletBalance($seller));
        $this->assertSame(1, LedgerEntry::where('entry_type', LedgerType::REFUND)->count());
        $this->assertSame(1, LedgerEntry::where('entry_type', LedgerType::COMM_REVERSAL)->count());
        $this->assertSame(1, Tx::where('type', Tx::REVERSE_EARN)->count());
        $this->assertSame(3, $this->balance($this->customer));
    }

    public function test_cancel_after_partial_return_reverses_only_the_rest(): void
    {
        // 3 وحدات بمجموع 10,000,000 وعمولة 7٪: الأرباح 9,300,000، النقاط 10
        $order = $this->deliveredOrder(10_000_000, 3, 7);
        $seller = $this->sellerOf($order);
        $line = $this->lineOf($order);
        $stockBefore = (int) $line->product->fresh()->stock_quantity;
        $this->assertSame(9_300_000, $this->walletBalance($seller));
        $this->assertSame(10, $this->balance($this->customer));

        $this->receivedReturn($order, 1);   // 3,333,333 / 233,333 → المحفظة 3,100,000؛ النقاط round(3.33) = 3
        $this->assertSame(6_200_000, $this->walletBalance($seller));
        $this->assertSame(7, $this->balance($this->customer));

        $order->fresh()->changeStatus('cancelled');
        // إعادة استدعاء العكس الكامل لا تخصم مرة ثانية
        $line->fresh()->reverseSettlement();

        $this->assertSame($stockBefore + 3, (int) $line->product->fresh()->stock_quantity);
        $this->assertSame([1, 2], $this->returnMovements($line->product_id)->pluck('quantity_change')->map(fn ($q) => (int) $q)->all());

        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame([3_100_000, 6_200_000], $this->walletDebits($seller)->pluck('amount')->map(fn ($a) => (int) $a)->all());

        $this->assertEqualsWithDelta(100_000.00, $this->ledgerSum($seller, LedgerType::REFUND, 'debit'), 0.001);
        $this->assertEqualsWithDelta(7_000.00, $this->ledgerSum($seller, LedgerType::COMM_REVERSAL, 'credit'), 0.001);
        $this->assertEqualsWithDelta(66_666.67, (float) LedgerEntry::where('entry_type', LedgerType::REFUND)->where('source_type', 'order_item_refund')->value('debit'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) SellerAccount::where('seller_id', $seller->user_id)->value('balance'), 0.001);

        $this->assertSame(-10, (int) Tx::where('type', Tx::REVERSE_EARN)->sum('points'));
        $this->assertSame(-7, (int) Tx::where('once_key', 'reverse_earn:'.$order->id)->value('points'));
        $this->assertSame(0, $this->balance($this->customer));
        $this->assertSame(0, $this->debt($this->customer));
        $this->assertLedgerConsistent($this->customer);
    }

    public function test_cancel_after_full_return_reverses_nothing_more(): void
    {
        $order = $this->deliveredOrder(10_000_000, 3, 7);
        $seller = $this->sellerOf($order);
        $line = $this->lineOf($order);
        $stockBefore = (int) $line->product->fresh()->stock_quantity;

        $this->receivedReturn($order, 1);
        $this->receivedReturn($order, 2);
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame(0, $this->balance($this->customer));

        $order->fresh()->changeStatus('cancelled');

        $this->assertSame($stockBefore + 3, (int) $line->product->fresh()->stock_quantity);
        $this->assertCount(2, $this->returnMovements($line->product_id));
        $this->assertCount(2, $this->walletDebits($seller));
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame(0, LedgerEntry::where('source_type', 'order_item_refund')->count());
        $this->assertEqualsWithDelta(100_000.00, $this->ledgerSum($seller, LedgerType::REFUND, 'debit'), 0.001);
        $this->assertSame(-10, (int) Tx::where('type', Tx::REVERSE_EARN)->sum('points'));
        $this->assertSame(0, Tx::where('once_key', 'reverse_earn:'.$order->id)->count());
    }

    public function test_cancel_blocked_while_return_open(): void
    {
        $order = $this->deliveredOrder(5_000_000, 5);
        $seller = $this->sellerOf($order);
        $line = $this->lineOf($order);

        $assertBlocked = function () use ($order) {
            try {
                $order->fresh()->changeStatus('cancelled');
                $this->fail('cancel must be blocked while a return is open');
            } catch (\DomainException $e) {
                $this->assertSame(self::OPEN_RETURN_MESSAGE, $e->getMessage());
            }
            $this->assertSame('delivered', $order->fresh()->status);
        };

        $requested = $this->service()->create($this->customer, $order, [$line->id => 1], 'changed_mind', null);
        $assertBlocked();

        // نفس الرسالة عبر لوحة المدير وتطبيقه
        $this->actingAs($this->admin)->from('/back')
            ->put(route('admin.orders.updateStatus', $order), ['status' => 'cancelled'])
            ->assertRedirect('/back')->assertSessionHas('error', self::OPEN_RETURN_MESSAGE);
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertStatus(422)->assertJson(['message' => self::OPEN_RETURN_MESSAGE]);

        $approved = $this->service()->sellerApprove($seller, $requested);
        $assertBlocked();
        $this->service()->withdraw($this->customer, $approved);

        $rejected = $this->service()->sellerReject($seller, $this->service()->create($this->customer, $order, [$line->id => 1], 'damaged', null), 'لا');
        $escalated = $this->service()->dispute($this->customer, $rejected, 'اعتراض');
        $assertBlocked();
        $this->service()->adminReject($this->admin, $escalated, 'مرفوض');

        $this->assertSame('delivered', $order->fresh()->changeStatus('cancelled'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $this->walletBalance($seller));
    }

    public function test_full_return_restores_coupon_like_cancel(): void
    {
        $order = $this->order($this->customer, 10_000_000);
        $line = $this->lineOf($order);
        $seller = SellerProfile::findOrFail($line->vendor_id);
        $campaign = CouponCampaign::create([
            'funder' => 'seller', 'seller_profile_id' => $seller->id, 'title' => 'شحن مجاني', 'type' => 'free_shipping', 'value' => 0,
            'scope' => 'all', 'audience' => 'collectable', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(14),
            'per_customer_limit' => 1, 'total_limit' => 100, 'status' => 'active',
        ]);
        $coupon = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $this->customer->id, 'status' => 'used', 'used_at' => now(), 'source' => 'collect']);
        $campaign->update(['redeemed_count' => 1]);
        $redemption = CouponRedemption::create([
            'customer_coupon_id' => $coupon->id, 'campaign_id' => $campaign->id, 'order_id' => $order->id, 'user_id' => $this->customer->id,
            'funder' => 'seller', 'seller_profile_id' => $seller->id, 'discount_amount' => 0, 'shipping_discount' => 15_000, 'status' => 'applied',
        ]);
        $order = $this->deliveredOrder(10_000_000, 2, 10, $order);
        $this->assertSame(9_000_000 - 15_000, $this->walletBalance($seller));

        // إرجاع جزئي: الكوبون لا يعود
        $this->receivedReturn($order, 1);
        $this->assertSame('used', $coupon->fresh()->status);
        $this->assertSame('applied', $redemption->fresh()->status);
        $this->assertSame(4_500_000 - 15_000, $this->walletBalance($seller));

        // اكتمال إرجاع كل الوحدات: الكوبون يعود وأجرة الشحن تُعاد للبائع، كما في الإلغاء
        $this->receivedReturn($order, 1);
        $this->assertSame('available', $coupon->fresh()->status);
        $this->assertSame('reversed', $redemption->fresh()->status);
        $this->assertSame(0, (int) $campaign->fresh()->redeemed_count);
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame(1, LedgerEntry::where('entry_type', LedgerType::COUPON_SHIPPING_REVERSAL)->count());

        // إلغاء لاحق لا يكرر شيئاً
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame(1, LedgerEntry::where('entry_type', LedgerType::COUPON_SHIPPING_REVERSAL)->count());
        $this->assertSame(0, (int) $campaign->fresh()->redeemed_count);
    }

    public function test_full_return_restores_redeemed_points_once(): void
    {
        // 10 نقاط مستبدلة (200,000) على طلب 5,000,000 → أساس النقاط 4,800,000 → 4 نقاط مكتسبة
        $order = $this->order($this->customer, 5_000_000, ['points_redeemed' => 10, 'points_discount' => 200_000]);
        $this->grant($this->customer, 10);
        app(LoyaltyLedger::class)->redeemForOrder($this->customer, $order, 10);
        $order = $this->deliveredOrder(5_000_000, 2, 10, $order);
        $this->assertSame(4, $this->balance($this->customer));

        // إرجاع جزئي: النقاط المستبدلة لا تعود
        $this->receivedReturn($order, 1);
        $this->assertSame(0, Tx::where('type', Tx::RESTORE)->count());
        $this->assertSame(2, $this->balance($this->customer));

        // اكتمال الإرجاع: تُسحب بقية المكتسبة وتعود العشر المستبدلة
        $this->receivedReturn($order, 1);
        $this->assertSame(10, (int) Tx::where('type', Tx::RESTORE)->where('order_id', $order->id)->sole()->points);
        $this->assertSame(10, $this->balance($this->customer));

        // إلغاء لاحق لا يعيدها مرة ثانية
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(1, Tx::where('type', Tx::RESTORE)->count());
        $this->assertSame(10, $this->balance($this->customer));
        $this->assertSame(-4, (int) Tx::where('type', Tx::REVERSE_EARN)->sum('points'));
        $this->assertLedgerConsistent($this->customer);
    }

    public function test_full_return_with_platform_coupon_and_points_claws_back_earned_and_refunds_paid_base(): void
    {
        // 10,000,000 − كوبون منصة 1,000,000 − 15 نقطة (300,000) = 8,700,000 مدفوعة → 8 نقاط
        $order = $this->order($this->customer, 10_000_000, ['discount_amount' => 1_000_000, 'points_redeemed' => 15, 'points_discount' => 300_000]);
        $campaign = CouponCampaign::create([
            'funder' => 'platform', 'title' => 'خصم', 'type' => 'fixed', 'value' => 1_000_000, 'scope' => 'all',
            'audience' => 'all_customers', 'starts_at' => now()->subDay(), 'per_customer_limit' => 1, 'status' => 'active',
        ]);
        $coupon = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $this->customer->id, 'status' => 'used', 'used_at' => now(), 'source' => 'test']);
        $campaign->update(['redeemed_count' => 1]);
        CouponRedemption::create([
            'customer_coupon_id' => $coupon->id, 'campaign_id' => $campaign->id, 'order_id' => $order->id, 'user_id' => $this->customer->id,
            'funder' => 'platform', 'discount_amount' => 1_000_000, 'shipping_discount' => 0, 'status' => 'applied',
        ]);
        $this->grant($this->customer, 15);
        app(LoyaltyLedger::class)->redeemForOrder($this->customer, $order, 15);
        $order = $this->deliveredOrder(10_000_000, 3, 10, $order);
        $earned = (int) Tx::where('type', Tx::EARN)->where('order_id', $order->id)->value('points');
        $this->assertSame(8, $earned);

        $first = $this->receivedReturn($order, 1, 'damaged');
        $second = $this->receivedReturn($order, 2, 'damaged');

        $this->assertSame(8_700_000, (int) ReturnItem::whereIn('return_request_id', [$first->id, $second->id])->sum('refund_amount'));
        $this->assertSame([-3, -5], Tx::where('type', Tx::REVERSE_EARN)->orderBy('id')->pluck('points')->map(fn ($p) => (int) $p)->all());
        $this->assertSame(-$earned, (int) Tx::where('type', Tx::REVERSE_EARN)->sum('points'));
        $this->assertSame('available', $coupon->fresh()->status);
        $this->assertSame(15, $this->balance($this->customer));
        $this->assertLedgerConsistent($this->customer);
    }

    public function test_receipt_on_cancelled_order_is_refused_and_rolled_back(): void
    {
        $order = $this->deliveredOrder(5_000_000, 5);
        $seller = $this->sellerOf($order);
        $request = $this->approvedReturn($order, 2);
        // سباق إنشاء/إلغاء: الطلب أُلغي (وعُكس كاملاً) وعليه مرتجع موافق عليه
        Order::whereKey($order->id)->update(['status' => 'cancelled']);

        try {
            $this->service()->confirmReceived($seller->user, $request);
            $this->fail('receipt on a cancelled order must be refused');
        } catch (\DomainException $e) {
            $this->assertSame('لا يمكن تأكيد استلام مرتجع لطلب ملغى.', $e->getMessage());
        }

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNull($request->items()->first()->settled_at);
        $this->assertCount(0, $this->returnMovements($this->lineOf($order)->product_id));
        $this->assertCount(0, $this->walletDebits($seller));
    }

    public function test_wallet_and_points_may_go_negative(): void
    {
        $order = $this->deliveredOrder(5_000_000, 5);
        $seller = $this->sellerOf($order);

        // البائع سحب أرباحه والزبون صرف نقاطه قبل الإرجاع
        $seller->walletOrCreate()->applyTransaction('debit', 4_500_000, 'withdrawal', ['note' => 'سحب']);
        app(LoyaltyLedger::class)->debit($this->customer->id, Tx::ADMIN_DEDUCT, 5, reason: 'صرفها');

        $this->receivedReturn($order, 2);

        $this->assertSame(-1_800_000, $this->walletBalance($seller));
        $this->assertSame(0, $this->balance($this->customer));
        $this->assertSame(2, $this->debt($this->customer));
        $this->assertLedgerConsistent($this->customer);
        $this->assertSame(1, ReturnItem::whereNotNull('settled_at')->count());
    }
}
