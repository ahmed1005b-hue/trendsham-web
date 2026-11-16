<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyAccount;
use App\Loyalty\Models\LoyaltyTransaction;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Str;

/** أدوات مشتركة لاختبارات النقاط. المبالغ بالوحدة الصغرى (×100). */
trait LoyaltyTestHelpers
{
    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'customer', 'phone_verified_at' => now()]);
    }

    protected function vendor(int $commission = 10): SellerProfile
    {
        $user = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S'.Str::random(4), 'status' => 'approved', 'commission_rate' => $commission]);
    }

    protected function product(?SellerProfile $vendor = null, int $price = 1_000_000): Product
    {
        $vendor ??= $this->vendor();
        $category = Category::firstOrCreate(['slug' => 'c'], ['name' => 'c']);

        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'منتج', 'slug' => 'p-'.Str::random(6),
            'sku' => 'S-'.Str::random(8), 'price' => $price, 'stock_quantity' => 50, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    /** طلب ببند واحد بمبلغ المنتجات $subtotal (دون الشحن) */
    protected function order(User $user, int $subtotal, array $attrs = []): Order
    {
        $product = $this->product(price: $subtotal);
        $order = Order::create($attrs + [
            'order_number' => 'TS-'.Str::upper(Str::random(6)), 'user_id' => $user->id, 'status' => 'pending',
            'subtotal' => $subtotal, 'shipping_fee' => 500_000, 'discount_amount' => 0,
            'total' => $subtotal + 500_000 - ($attrs['discount_amount'] ?? 0) - ($attrs['points_discount'] ?? 0),
            'shipping_name' => 'x', 'shipping_phone' => '0999', 'shipping_address' => 'x',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $product->vendor_id,
            'product_name' => $product->name, 'unit_price' => $subtotal, 'quantity' => 1, 'subtotal' => $subtotal,
            'discount_amount' => $attrs['discount_amount'] ?? 0,
            'discount_funder' => ($attrs['discount_amount'] ?? 0) > 0 ? 'platform' : null,
        ]);

        return $order->fresh();
    }

    protected function deliver(Order $order): Order
    {
        $order->changeStatus('delivered');

        return $order->fresh();
    }

    /** منح رصيد مباشر (دفعة إدارية) */
    protected function grant(User $user, int $points): void
    {
        app(LoyaltyLedger::class)->credit($user->id, LoyaltyTransaction::ADMIN_ADD, $points, reason: 'اختبار');
    }

    protected function balance(User $user): int
    {
        return (int) (LoyaltyAccount::where('user_id', $user->id)->value('balance') ?? 0);
    }

    protected function debt(User $user): int
    {
        return (int) (LoyaltyAccount::where('user_id', $user->id)->value('debt') ?? 0);
    }

    /** الثابت: Σ points = balance − debt، و balance = Σ remaining للدفعات غير المنتهية */
    protected function assertLedgerConsistent(User $user): void
    {
        $acct = LoyaltyAccount::where('user_id', $user->id)->first();
        $balance = (int) ($acct->balance ?? 0);
        $debt = (int) ($acct->debt ?? 0);
        $sum = (int) LoyaltyTransaction::where('user_id', $user->id)->sum('points');
        $remaining = (int) LoyaltyTransaction::where('user_id', $user->id)
            ->whereIn('type', LoyaltyTransaction::LOT_TYPES)->where('expires_at', '>', now())->sum('remaining');

        $this->assertSame($balance - $debt, $sum, 'Σ points ≠ balance − debt');
        $this->assertSame($balance, $remaining, 'balance ≠ Σ remaining');
    }
}
