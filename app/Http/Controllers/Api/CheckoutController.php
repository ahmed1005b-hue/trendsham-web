<?php

namespace App\Http\Controllers\Api;

use App\Coupons\CouponIssuer;
use App\Coupons\CouponPresenter;
use App\Coupons\CouponRedeemer;
use App\Coupons\CouponSelector;
use App\Coupons\GiftLine;
use App\GiftCards\GiftCardService;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Loyalty\LoyaltyCheckout;
use App\Loyalty\LoyaltyException;
use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCenter;
use App\Notifications\Push\SellerOrderNotifier;
use App\Services\DailyDealQuota;
use App\Services\PaymentService;
use App\Shipping\Governorates;
use App\Shipping\ParcelService;
use App\Shipping\ShippingChoice;
use App\Shipping\ShippingDebt;
use App\Shipping\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function summary(Request $request)
    {
        $items = $request->user()->cartItems()->with('product.activeDailyDeal', 'product.vendor')->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'السلة فارغة.'], 400);
        }

        $subtotal = (int) $items->sum(fn ($item) => $item->subtotal);
        $user = $request->user();
        app(CouponIssuer::class)->syncFor($user);

        // طريقة الاستلام (home افتراضياً): أجرة لكل طرد، والكوبون يُقيَّم على أجرة الطريقة المختارة
        // shipping_governorate: محافظة الزبون (أسعار المتاجر التي توصل بنفسها)، وإلا تُخمَّن من shipping_address.
        // pickup_stores[]: متاجر يستلم الزبون طردها من محلها.
        $request->validate(ShippingChoice::rules());
        $choice = ShippingChoice::fromInput($request->query(), $request->query('shipping_address') ?? $user->city);
        $method = $choice->method;
        $quotes = ['home' => $choice->quote($items, 'home'), 'center' => $choice->quote($items, 'center')];
        $baseShipping = $quotes[$method]['fee'];

        // customer_coupon_id: غائب = الأفضل، 0 = بلا كوبون، رقم = كوبون محدد من المحفظة
        $pick = app(CouponSelector::class)->best($user, $items->values(), $baseShipping);
        $chosen = null;
        if (! $request->has('customer_coupon_id')) {
            $chosen = $pick['chosen'] ? ['coupon' => $pick['chosen'], 'result' => $pick['result']] : null;
        } elseif ($id = $request->integer('customer_coupon_id')) {
            $chosen = collect($pick['options'])->first(fn ($o) => $o['coupon']->id === $id && $o['result']->ok);
            if (! $chosen) {
                // نخبر التطبيق لماذا سقط الكوبون المختار حتى يعيد العميل الاختيار ولا يعلق
                $dropped = app(CouponSelector::class)->evaluate($user, $id, $items->values(), $baseShipping);
                $droppedReason = $dropped['result']->reason ?? 'الكوبون غير موجود في محفظتك';
            }
        }
        $discount = $chosen['result']->discount ?? 0;
        $shippingDiscount = $chosen['result']->shippingDiscount ?? 0;
        $shipping = max(0, $baseShipping - $shippingDiscount);
        $debt = (int) $user->shipping_debt;

        return response()->json([
            'subtotal' => $subtotal / 100,
            'delivery_method' => $method,
            'shipping_fee' => $shipping / 100,
            'base_shipping_fee' => $baseShipping / 100,
            'shipping_by_method' => array_map(fn ($q) => max(0, $q['fee'] - $shippingDiscount) / 100, $quotes),
            'shipping_governorate' => $choice->governorate,
            'governorates' => Governorates::ALL,
            'parcels' => array_map(fn ($p) => [
                'vendor_id' => $p['vendor_id'], 'store_name' => $p['store_name'], 'weight_kg' => round($p['weight_grams'] / 1000, 2), 'heavy' => $p['heavy'], 'fee' => $p['fee'] / 100,
                'mode' => $p['mode'], 'mode_label' => ShippingQuote::modeLabel($p['mode']), 'eta_min_days' => $p['eta_min'], 'eta_max_days' => $p['eta_max'],
                'eta_label' => ShippingQuote::etaLabel($p), 'can_pickup' => $p['can_pickup'], 'pickup_address' => $p['pickup_address'],
            ], $quotes[$method]['parcels']),
            'shipping_debt' => $debt / 100,
            'cod_blocked' => ShippingDebt::codBlocked($user),
            'cod_blocked_message' => ShippingDebt::codBlocked($user) ? ShippingDebt::codBlockedMessage($user) : null,
            'discount' => (int) round($discount / 100),
            'total' => ($subtotal + $shipping - $discount + $debt) / 100,
            'coupon' => $chosen ? CouponPresenter::coupon($chosen['coupon'], $chosen['result']) : null,
            'coupon_auto' => ! $request->has('customer_coupon_id') && $chosen !== null,
            'coupon_dropped_reason' => $droppedReason ?? null,
            'coupon_options' => collect($pick['options'])->map(fn ($o) => CouponPresenter::coupon($o['coupon'], $o['result']))->values(),
            'loyalty' => $this->loyaltySummary($user, $subtotal, $discount, $request->integer('use_points')),
            'gift_balance' => app(GiftCardService::class)->balance($user->id) / 100,
            'currency' => 'SYP',
            'pricing_note' => 'نطبّق أفضل كوبون في محفظتك تلقائياً، ويُعاد التحقق منه عند تأكيد الطلب.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'delivery_method' => ['nullable', 'in:'.implode(',', ShippingQuote::METHODS)],
            'pickup_center_id' => ['nullable', 'required_if:delivery_method,center', Rule::exists('shipping_centers', 'id')->where('is_active', true)],
            'shipping_address' => ['nullable', 'required_unless:delivery_method,center', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cash_on_delivery,card'],
            // كوبون المحفظة: غائب = الأفضل تلقائياً، null/0 = بلا كوبون، رقم = كوبون اختاره العميل
            'customer_coupon_id' => ['nullable', 'integer'],
            'use_points' => ['nullable', 'integer', 'min:0'],   // نقاط الولاء كخصم
            'use_gift_balance' => ['nullable', 'boolean'],      // رصيد بطاقات الهدايا
            ...ShippingChoice::rules(),
        ]);
        $couponChoice = $request->has('customer_coupon_id') ? ($request->integer('customer_coupon_id') ?: 'none') : null;

        // البطاقة فقط حين يكون الدفع الإلكتروني مفعّلاً، وإلا بقي الطلب بلا طريقة دفع حقيقية
        if ($validated['payment_method'] === 'card' && ! app(PaymentService::class)->onlineEnabled()) {
            $message = __('الدفع بالبطاقة غير متاح حالياً. اختر الدفع عند الاستلام.');

            return response()->json(['message' => $message, 'errors' => ['payment_method' => [$message]]], 422);
        }

        if ($validated['payment_method'] === 'card'
            && Order::openCardOrdersCount($request->user()->id) >= Order::MAX_OPEN_CARD_ORDERS) {
            $message = __('لديك طلبات بالبطاقة لم تُدفع بعد. ادفعها أو ألغها أولاً.');

            return response()->json(['message' => $message, 'errors' => ['payment_method' => [$message]]], 422);
        }

        if ($validated['payment_method'] === 'cash_on_delivery' && ShippingDebt::codBlocked($request->user())) {
            return response()->json(['message' => ShippingDebt::codBlockedMessage($request->user()), 'errors' => [
                'payment_method' => [ShippingDebt::codBlockedMessage($request->user())],
            ]], 422);
        }
        $deliveryMethod = ShippingQuote::method($validated['delivery_method'] ?? null);
        if ($deliveryMethod === 'center') {
            $validated['shipping_address'] = ShippingCenter::findOrFail($validated['pickup_center_id'])->pickupAddress();
        }
        $shipping = ShippingChoice::fromInput($validated, $deliveryMethod === 'home' ? $validated['shipping_address'] : null);

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

        // السعر يُحسب الآن لا عند الإضافة للسلة: عرض انتهى أو سعر عدّله البائع يظهر للعميل قبل الدفع
        if (CartItem::repriceStale($items)) {
            return response()->json(['message' => __('تغيّر سعر بعض المنتجات في سلتك (انتهى عرض أو عدّل البائع السعر). راجع السلة ثم أكّد الطلب.'), 'code' => 'price_changed'], 422);
        }

        try {
            $order = DB::transaction(function () use ($items, $validated, $request, $couponChoice, $deliveryMethod, $shipping) {
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
                    if (! $item->hasCurrentPrice($product)) {
                        throw new \DomainException('price_changed');
                    }
                }

                // كمية «عرض اليوم» تُحجز تحت القفل: لا تُباع أكثر من الكمية المحددة
                $dealClaims = app(DailyDealQuota::class)->claim($items);

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
                $shippingQuote = $shipping->quote($items, $deliveryMethod);
                $shippingCost = $shippingQuote['fee'];
                [$coupon, $couponResult, $dropped] = $redeemer->resolve($request->user(), $couponChoice, $lines, $shippingCost);
                if ($dropped && is_int($couponChoice)) {
                    // كوبون اختاره العميل صراحةً ولم يعد صالحاً: نخبره بدل أن نكمل بسعر لم يتوقعه
                    throw new \DomainException('coupon:'.$dropped);
                }
                $discount = $couponResult?->discount ?? 0;
                $shippingFee = max(0, $shippingCost - ($couponResult?->shippingDiscount ?? 0));
                $funder = $coupon?->campaign->funder;
                $debt = app(ShippingDebt::class)->takeForNewOrder($request->user()); // أجرة طلب رفضه سابقاً

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'user_id' => $request->user()->id,
                    'customer_coupon_id' => $coupon?->id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'shipping_cost' => $shippingCost,
                    'shipping_debt_charged' => $debt,
                    'discount_amount' => $discount,
                    'total' => $subtotal + $shippingFee - $discount + $debt,
                    'shipping_name' => $validated['shipping_name'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'shipping_address' => $validated['shipping_address'],
                    'shipping_governorate' => $shipping->governorate,
                    'delivery_method' => $deliveryMethod,
                    'pickup_center_id' => $deliveryMethod === 'center' ? $validated['pickup_center_id'] : null,
                ]);

                if ($coupon) {
                    $redeemer->record($coupon, $couponResult, $order);
                }

                // نقاط الولاء بعد الكوبون (المنصة تتحمّل خصمها)
                app(LoyaltyCheckout::class)->apply($request->user(), $order, $validated['use_points'] ?? null);

                // رصيد بطاقات الهدايا يغطي ما تبقّى (مع الشحن). طلب مغطّى بالكامل يُعدّ مدفوعاً
                if ($request->boolean('use_gift_balance')) {
                    app(GiftCardService::class)->spendForOrder($request->user(), $order);
                }
                $fullyPaid = (int) $order->total === 0;
                if ($fullyPaid) {
                    $order->update(['payment_status' => 'paid']);
                }

                foreach ($lines as $key => $line) {
                    $product = $line->product;
                    $item = $line->item;
                    $lineDiscount = $couponResult?->lineDiscounts[$key] ?? 0;
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'variant_id' => $line->variant?->id,
                        'daily_deal_id' => $dealClaims[$item->id] ?? null,
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
                    'status' => $fullyPaid ? 'paid' : 'pending',
                    'amount' => $order->total,
                ]);

                // طرد لكل متجر بأجرته (خصم الشحن من الكوبون يُوزَّع على الطرود)
                app(ParcelService::class)->createForCheckout($order, $shippingQuote['parcels'], $shippingCost - $shippingFee);
                if ($validated['payment_method'] === 'cash_on_delivery' && ! $fullyPaid) {
                    app(ParcelService::class)->assignCashToCollect($order);
                }
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
            if ($e->getMessage() === 'price_changed') {
                CartItem::repriceStale($request->user()->cartItems()->with(['product.activeDailyDeal', 'variant'])->get());

                return response()->json(['message' => __('تغيّر سعر بعض المنتجات في سلتك (انتهى عرض أو عدّل البائع السعر). راجع السلة ثم أكّد الطلب.'), 'code' => 'price_changed'], 422);
            }
            if ($e->getMessage() === DailyDealQuota::SOLD_OUT) {
                return response()->json(['message' => __('نفدت كمية «عرض اليوم» لأحد المنتجات. احذفه من السلة وأضفه من جديد بسعره الحالي.'), 'code' => 'deal_sold_out'], 422);
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
