<?php

namespace App\Http\Controllers\Api;

use App\Coupons\CouponIssuer;
use App\Coupons\CouponPresenter;
use App\Coupons\CouponRedeemer;
use App\Coupons\CouponSelector;
use App\Coupons\GiftLine;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Loyalty\LoyaltyCheckout;
use App\Loyalty\LoyaltyException;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Notifications\Push\SellerOrderNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public const SHIPPING_FEE = 15000;

    public function summary(Request $request)
    {
        $items = $request->user()->cartItems()->with('product.activeDailyDeal')->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'السلة فارغة.'], 400);
        }

        $subtotal = (int) $items->sum(fn ($item) => $item->subtotal);
        $user = $request->user();
        app(CouponIssuer::class)->syncFor($user);

        // customer_coupon_id: غائب = الأفضل، 0 = بلا كوبون، رقم = كوبون محدد من المحفظة
        $pick = app(CouponSelector::class)->best($user, $items->values(), self::SHIPPING_FEE);
        $chosen = null;
        if (! $request->has('customer_coupon_id')) {
            $chosen = $pick['chosen'] ? ['coupon' => $pick['chosen'], 'result' => $pick['result']] : null;
        } elseif ($id = $request->integer('customer_coupon_id')) {
            $chosen = collect($pick['options'])->first(fn ($o) => $o['coupon']->id === $id && $o['result']->ok);
            if (! $chosen) {
                // نخبر التطبيق لماذا سقط الكوبون المختار حتى يعيد العميل الاختيار ولا يعلق
                $dropped = app(CouponSelector::class)->evaluate($user, $id, $items->values(), self::SHIPPING_FEE);
                $droppedReason = $dropped['result']->reason ?? 'الكوبون غير موجود في محفظتك';
            }
        }
        $discount = $chosen['result']->discount ?? 0;
        $shipping = self::SHIPPING_FEE - ($chosen['result']->shippingDiscount ?? 0);

        return response()->json([
            'subtotal' => $subtotal / 100,
            'shipping_fee' => $shipping / 100,
            'base_shipping_fee' => self::SHIPPING_FEE / 100,
            'discount' => (int) round($discount / 100),
            'total' => ($subtotal + $shipping - $discount) / 100,
            'coupon' => $chosen ? CouponPresenter::coupon($chosen['coupon'], $chosen['result']) : null,
            'coupon_auto' => ! $request->has('customer_coupon_id') && $chosen !== null,
            'coupon_dropped_reason' => $droppedReason ?? null,
            'coupon_options' => collect($pick['options'])->map(fn ($o) => CouponPresenter::coupon($o['coupon'], $o['result']))->values(),
            'loyalty' => $this->loyaltySummary($user, $subtotal, $discount, $request->integer('use_points')),
            'currency' => 'SYP',
            'pricing_note' => 'نطبّق أفضل كوبون في محفظتك تلقائياً، ويُعاد التحقق منه عند تأكيد الطلب.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cash_on_delivery,card,wallet'],
            // كوبون المحفظة: غائب = الأفضل تلقائياً، null/0 = بلا كوبون، رقم = كوبون اختاره العميل
            'customer_coupon_id' => ['nullable', 'integer'],
            'use_points' => ['nullable', 'integer', 'min:0'],   // نقاط الولاء كخصم
        ]);
        $couponChoice = $request->has('customer_coupon_id') ? ($request->integer('customer_coupon_id') ?: 'none') : null;

        $items = $request->user()->cartItems()->with(['product.activeDailyDeal', 'variant', 'storefrontVisit'])->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'السلة فارغة.'], 400);
        }

        foreach ($items as $item) {
            $stock = $item->variant ? $item->variant->stock_quantity : $item->product->stock_quantity;
            if (! $item->product->isLive() || $stock < $item->quantity) {
                return response()->json([
                    'message' => __('الكمية المتوفرة من ":product" لم تعد كافية.', ['product' => $item->product->name]),
                ], 422);
            }
        }

        try {
            $order = DB::transaction(function () use ($items, $validated, $request, $couponChoice) {
                // نقفل سلة المشتري ونقرؤها من جديد قبل أي شيء: ضغطتان متزامنتان على «تأكيد الطلب»
                // لا تُنشئان طلبين — الثانية تنتظر الأولى، ثم تجد بنود السلة قد حُذفت فتتوقف.
                $lockedIds = $request->user()->cartItems()->lockForUpdate()->pluck('id');
                if ($items->pluck('id')->diff($lockedIds)->isNotEmpty()) {
                    throw new \DomainException('already_ordered');
                }

                $products = collect($items->pluck('product_id'))
                    ->map(fn ($id) => Product::with('activeDailyDeal')->whereKey($id)->lockForUpdate()->first());

                // نقفل اللون/المقاس ونقرأ كميته من جديد: مشتريان متزامنان لا يأخذان آخر قطعة معاً
                foreach ($items as $item) {
                    if ($item->variant_id) {
                        $item->setRelation('variant', ProductVariant::whereKey($item->variant_id)->lockForUpdate()->first()
                            ?? throw new \DomainException('stock_conflict'));
                    }
                }

                foreach ($items as $index => $item) {
                    $product = $products[$index] ?? null;
                    $stock = $item->variant ? $item->variant->stock_quantity : ($product?->stock_quantity ?? 0);
                    if ($product === null || ! $product->isLive() || $stock < $item->quantity) {
                        throw new \DomainException('stock_conflict');
                    }
                }

                $products = $products->keyBy('id');
                $lines = $items->map(function ($item) use ($products) {
                    $product = $products[$item->product_id];
                    $lineSubtotal = $item->subtotal;

                    return (object) [
                        'item' => $item,
                        'product' => $product,
                        'variant' => $item->variant,
                        'vendor_id' => $product->vendor_id,
                        'product_id' => $product->id,
                        'subtotal' => $lineSubtotal,
                    ];
                });

                $subtotal = (int) $lines->sum('subtotal');

                $redeemer = app(CouponRedeemer::class);
                [$coupon, $couponResult, $dropped] = $redeemer->resolve($request->user(), $couponChoice, $lines, self::SHIPPING_FEE);
                if ($dropped && is_int($couponChoice)) {
                    // كوبون اختاره العميل صراحةً ولم يعد صالحاً: نخبره بدل أن نكمل بسعر لم يتوقعه
                    throw new \DomainException('coupon:'.$dropped);
                }
                $discount = $couponResult?->discount ?? 0;
                $shippingFee = self::SHIPPING_FEE - ($couponResult?->shippingDiscount ?? 0);
                $funder = $coupon?->campaign->funder;

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'user_id' => $request->user()->id,
                    'customer_coupon_id' => $coupon?->id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'discount_amount' => $discount,
                    'total' => $subtotal + $shippingFee - $discount,
                    'shipping_name' => $validated['shipping_name'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'shipping_address' => $validated['shipping_address'],
                ]);

                if ($coupon) {
                    $redeemer->record($coupon, $couponResult, $order);
                }

                // نقاط الولاء بعد الكوبون (المنصة تتحمّل خصمها)
                app(LoyaltyCheckout::class)->apply($request->user(), $order, $validated['use_points'] ?? null);

                foreach ($lines as $key => $line) {
                    $product = $line->product;
                    $item = $line->item;
                    $lineDiscount = $couponResult?->lineDiscounts[$key] ?? 0;
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'variant_id' => $line->variant?->id,
                        'vendor_id' => $product->vendor_id,
                        'storefront_visit_id' => $item->storefront_visit_id
                            && $item->storefrontVisit?->seller_profile_id === $product->vendor_id
                                ? $item->storefront_visit_id
                                : null,
                        'product_name' => $product->name,
                        'variant_label' => $line->variant?->label,
                        'unit_price' => $item->unit_price ?? $product->effective_price,
                        'quantity' => $item->quantity,
                        'subtotal' => $line->subtotal,
                        'discount_amount' => $lineDiscount,
                        'discount_funder' => $lineDiscount > 0 ? $funder : null,
                    ]);

                    if ($line->variant) {
                        $line->variant->decrement('stock_quantity', $item->quantity);
                    }
                    $product->adjustStock(-$item->quantity, 'sale', "طلب رقم {$order->order_number}");
                }

                // كوبون هدية: بند مجاني من متجر البائع يُخصم من مخزونه
                if ($coupon && $couponResult?->giftProductId) {
                    app(GiftLine::class)->add($order, $coupon->campaign);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method' => $validated['payment_method'],
                    'status' => 'pending',
                    'amount' => $order->total,
                ]);

                Shipment::create(['order_id' => $order->id, 'status' => 'pending']);
                $request->user()->cartItems()->delete();

                AppNotification::send(
                    $request->user()->id,
                    'order_created',
                    "تم استلام طلبك #{$order->order_number}",
                    'سنقوم بمراجعته وتجهيزه قريباً.',
                    route('customer.orders.show', $order)
                );
                app(SellerOrderNotifier::class)->notify($order);

                return $order;
            });
        } catch (LoyaltyException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['use_points' => [$e->getMessage()]]], 422);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'already_ordered') {
                $latest = Order::where('user_id', $request->user()->id)->latest('id')->first();

                return response()->json([
                    'message' => __('تم إرسال طلبك مسبقاً، ولن يُكرَّر.'),
                    'code' => 'already_ordered',
                    'order_id' => $latest?->id,
                ], 409);
            }
            if ($e->getMessage() === 'stock_conflict') {
                return response()->json(['message' => 'الكمية المتوفرة من أحد المنتجات لم تعد كافية، رجاءً حدّث السلة.'], 422);
            }
            if (str_starts_with($e->getMessage(), 'coupon:')) {
                return response()->json(['message' => __('لا يمكن استخدام هذا الكوبون: :reason', ['reason' => substr($e->getMessage(), 7)]), 'code' => 'coupon_invalid'], 422);
            }
            throw $e;
        }

        return new OrderResource($order->load('items', 'payment', 'shipment'));
    }

    /** ملخص النقاط لصفحة الدفع في التطبيق — قيمة النقطة بالليرة */
    private function loyaltySummary($user, int $subtotal, int $couponDiscount, int $usePoints): array
    {
        $l = app(LoyaltyCheckout::class)->summary($user, $subtotal, $couponDiscount, $usePoints);
        $l['point_value'] = $l['point_value'] / 100;
        $l['spend_per_point'] = $l['spend_per_point'] / 100;

        return $l;
    }
}
