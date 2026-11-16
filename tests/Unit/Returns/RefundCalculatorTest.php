<?php

namespace Tests\Unit\Returns;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;
use App\Returns\RefundCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private ?User $customer = null;

    private function seller(): SellerProfile
    {
        $n = ++$this->seq;
        $user = User::create([
            'first_name' => "S$n", 'last_name' => 'T', 'phone' => '0977'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'email' => "rc-seller-$n@test.test", 'password' => 'password', 'role' => 'seller', 'status' => 'active',
        ]);

        return SellerProfile::create(['user_id' => $user->id, 'store_name' => "Store $n", 'status' => 'approved', 'identity_verified_at' => now()]);
    }

    private function order(array $attrs = []): Order
    {
        $this->customer ??= User::create([
            'first_name' => 'C', 'last_name' => 'T', 'phone' => '0966000001',
            'email' => 'rc-customer@test.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active',
        ]);

        return Order::create($attrs + [
            'order_number' => 'RC-'.Str::upper(Str::random(8)), 'user_id' => $this->customer->id,
            'status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 0, 'shipping_fee' => 0,
            'discount_amount' => 0, 'total' => 0, 'shipping_name' => 'C', 'shipping_phone' => '0966000001', 'shipping_address' => 'A',
        ]);
    }

    private function line(Order $order, SellerProfile $seller, int $qty, int $subtotal, array $attrs = []): OrderItem
    {
        $category = Category::firstOrCreate(['slug' => 'rc-cat'], ['name' => 'RC', 'is_active' => true]);
        $product = Product::create([
            'vendor_id' => $seller->id, 'category_id' => $category->id, 'name' => 'P', 'slug' => 'p-'.Str::random(8),
            'description' => 'd', 'sku' => 'SKU-'.Str::random(8), 'price' => intdiv($subtotal, $qty), 'stock_quantity' => 10,
            'status' => 'active', 'admin_approved_at' => now(),
        ]);

        return OrderItem::create($attrs + [
            'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $seller->id, 'product_name' => 'P',
            'unit_price' => intdiv($subtotal, $qty), 'quantity' => $qty, 'subtotal' => $subtotal,
            'commission_amount' => 0, 'vendor_earning' => $subtotal, 'commission_settled_at' => now(),
        ]);
    }

    private function request(Order $order, SellerProfile $seller, string $reason = 'damaged', string $status = 'requested'): ReturnRequest
    {
        return ReturnRequest::create([
            'number' => 'RR-'.Str::upper(Str::random(8)), 'order_id' => $order->id, 'user_id' => $order->user_id,
            'seller_profile_id' => $seller->id, 'status' => $status, 'reason' => $reason,
        ]);
    }

    /** @param array{refund: int, seller_reversal: int, commission_reversal: int} $amounts */
    private function store(ReturnRequest $request, OrderItem $item, int $qty, array $amounts): ReturnItem
    {
        return ReturnItem::create([
            'return_request_id' => $request->id, 'order_item_id' => $item->id, 'quantity' => $qty,
            'refund_amount' => $amounts['refund'], 'seller_reversal' => $amounts['seller_reversal'],
            'commission_reversal' => $amounts['commission_reversal'],
        ]);
    }

    public function test_single_line_full_return_refunds_line_paid_amount(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 5000, 'total' => 5000]);
        $item = $this->line($order, $seller, 2, 5000);

        $this->assertSame(
            ['refund' => 5000, 'seller_reversal' => 5000, 'commission_reversal' => 0],
            (new RefundCalculator)->forLine($item, 2)
        );
    }

    public function test_partial_quantity_is_proportional_and_remainder_goes_to_last_units(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 1000, 'total' => 1000]);
        $item = $this->line($order, $seller, 3, 1000, ['commission_amount' => 100, 'vendor_earning' => 900]);
        $calc = new RefundCalculator;

        $sum = ['refund' => 0, 'seller_reversal' => 0, 'commission_reversal' => 0];
        foreach ([333, 333, 334] as $refund) {
            $request = $this->request($order, $seller);
            $amounts = $calc->forLine($item, 1);
            $this->assertSame($refund, $amounts['refund']);
            $this->store($request, $item, 1, $amounts);
            foreach ($sum as $k => $v) {
                $sum[$k] += $amounts[$k];
            }
        }

        $this->assertSame(['refund' => 1000, 'seller_reversal' => 1000, 'commission_reversal' => 100], $sum);
    }

    public function test_platform_coupon_and_points_are_shared_by_line_value(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 3000, 'discount_amount' => 300, 'points_discount' => 100, 'total' => 2600]);
        $a = $this->line($order, $seller, 1, 1000);
        $b = $this->line($order, $seller, 1, 2000);
        $calc = new RefundCalculator;

        // 400 مشتركة بنسبة 1:2 => 133 و267 (الباقي للأكبر)
        $this->assertSame(1000 - 133, $calc->forLine($a, 1)['refund']);
        $this->assertSame(2000 - 267, $calc->forLine($b, 1)['refund']);
    }

    public function test_full_return_of_every_line_never_exceeds_products_paid(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 3001, 'discount_amount' => 301, 'points_discount' => 101, 'total' => 2599]);
        $a = $this->line($order, $seller, 2, 1001);
        $b = $this->line($order, $seller, 1, 2000);
        $calc = new RefundCalculator;

        $total = $calc->forLine($a, 2)['refund'] + $calc->forLine($b, 1)['refund'];
        $this->assertSame(3001 - 301 - 101, $total);
    }

    public function test_line_discounts_already_on_lines_are_not_allocated_twice(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 3000, 'discount_amount' => 300, 'total' => 2700]);
        $a = $this->line($order, $seller, 1, 1000, ['discount_amount' => 100, 'discount_funder' => 'platform']);
        $b = $this->line($order, $seller, 1, 2000, ['discount_amount' => 200, 'discount_funder' => 'platform']);
        $calc = new RefundCalculator;

        $this->assertSame(900, $calc->forLine($a, 1)['refund']);
        $this->assertSame(1800, $calc->forLine($b, 1)['refund']);
    }

    public function test_seller_reversal_uses_settlement_base_and_commission_by_quantity(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 4000, 'discount_amount' => 400, 'total' => 3600]);
        $item = $this->line($order, $seller, 4, 4000, [
            'discount_amount' => 400, 'discount_funder' => 'seller', 'commission_amount' => 360, 'vendor_earning' => 3240,
        ]);

        $this->assertSame(
            ['refund' => 900, 'seller_reversal' => 900, 'commission_reversal' => 90],
            (new RefundCalculator)->forLine($item, 1)
        );
        $full = (new RefundCalculator)->forLine($item, 4);
        $this->assertSame(3240, $full['seller_reversal'] - $full['commission_reversal']);
    }

    public function test_for_line_rejects_unsettled_line(): void
    {
        $seller = $this->seller();
        $order = $this->order(['subtotal' => 1000, 'total' => 1000]);
        $item = $this->line($order, $seller, 1, 1000, ['commission_settled_at' => null]);

        $this->expectException(\DomainException::class);
        (new RefundCalculator)->forLine($item, 1);
    }

    public function test_shipping_only_for_single_vendor_full_seller_fault_return(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $calc = new RefundCalculator;

        $multi = $this->order(['subtotal' => 2000, 'shipping_fee' => 500, 'total' => 2500]);
        $m1 = $this->line($multi, $seller, 1, 1000);
        $this->line($multi, $other, 1, 1000);
        $req = $this->request($multi, $seller);
        $this->store($req, $m1, 1, $calc->forLine($m1, 1));
        $this->assertSame(0, $calc->shippingFor($req));

        $single = $this->order(['subtotal' => 2000, 'shipping_fee' => 500, 'total' => 2500]);
        $s1 = $this->line($single, $seller, 2, 2000);
        $changed = $this->request($single, $seller, 'changed_mind');
        $this->store($changed, $s1, 2, $calc->forLine($s1, 2));
        $this->assertSame(0, $calc->shippingFor($changed));

        $damaged = $this->order(['subtotal' => 2000, 'shipping_fee' => 500, 'total' => 2500]);
        $d1 = $this->line($damaged, $seller, 2, 2000);
        $full = $this->request($damaged, $seller, 'damaged');
        $this->store($full, $d1, 2, $calc->forLine($d1, 2));
        $this->assertSame(500, $calc->shippingFor($full));

        $partial = $this->order(['subtotal' => 2000, 'shipping_fee' => 500, 'total' => 2500]);
        $p1 = $this->line($partial, $seller, 2, 2000);
        $one = $this->request($partial, $seller, 'damaged');
        $this->store($one, $p1, 1, $calc->forLine($p1, 1));
        $this->assertSame(0, $calc->shippingFor($one));
    }
}
