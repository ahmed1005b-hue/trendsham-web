<?php

namespace App\Http\Controllers\Customer;

use App\Coupons\CouponIssuer;
use App\Coupons\CouponRedeemer;
use App\Coupons\CouponSelector;
use App\Coupons\GiftLine;
use App\Coupons\Models\CustomerCoupon;
use App\GiftCards\GiftCardService;
use App\Http\Controllers\Controller;
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
    public function show(Request $request)
    {
        $items = $request->user()->cartItems()->with('product.activeDailyDeal', 'product.vendor', 'variant')->get();
        if ($items->isEmpty()) {
            return redirect()->route('customer.cart.index')->with('error', 'سلتك فارغة، أضف منتجات أولاً قبل إتمام الشراء.');
        }

        $subtotal = (int) $items->sum(fn ($item) => $item->subtotal);
        $user = $request->user();
        app(CouponIssuer::class)->syncFor($user);

        // اختيارات الشحن: من الرابط عند تغييرها، وإلا ما حُفظ في الجلسة، وإلا محافظة العنوان الافتراضي
        $shipping = $this->shippingChoice($request);
        $quotes = ['home' => $shipping->quote($items, 'home'), 'center' => $shipping->quote($items, 'center')];
        $homeFee = $quotes['home']['fee'];

        // السلة كما تراها خدمة الكوبونات: كل سطر فيه product و subtotal
        $pick = app(CouponSelector::class)->best($user, $items->values(), $homeFee);
        $choice = $request->session()->get('checkout_coupon');
        $auto = $choice === null;
        $chosen = null;
        if ($choice === 'none') {
            $chosen = null;
        } elseif ($choice !== null) {
            $chosen = collect($pick['options'])->first(fn ($o) => $o['coupon']->id === (int) $choice && $o['result']->ok);
            if (! $chosen) { // الكوبون المختار لم يعد صالحاً لهذه السلة: نرجع للأفضل ونخبر العميل
                $request->session()->forget('checkout_coupon');
                $auto = true;
                $couponNotice = 'الكوبون الذي اخترته لم يعد صالحاً لهذه السلة، فاخترنا لك الأفضل المتاح.';
            }
        }
        if ($auto && $pick['chosen']) {
            $chosen = ['coupon' => $pick['chosen'], 'result' => $pick['result']];
        }

        $shippingDiscount = $chosen['result']->shippingDiscount ?? 0;
        $method = $shipping->method;

        return view('customer.checkout.show', [
            'items' => $items,
            'subtotal' => $subtotal,
            'deliveryMethod' => $method,
            'shippingByMethod' => array_map(fn ($q) => max(0, $q['fee'] - $shippingDiscount), $quotes),
            'baseShippingByMethod' => array_map(fn ($q) => $q['fee'], $quotes),
            'shippingFee' => max(0, $quotes[$method]['fee'] - $shippingDiscount),
            'baseShippingFee' => $quotes[$method]['fee'],
            'parcels' => $quotes[$method]['parcels'],
            'shippingChoice' => $shipping,
            'governorates' => Governorates::ALL,
            'centers' => ShippingCenter::groupedForSelect(),
            'shippingDebt' => (int) $user->shipping_debt,
            'codBlocked' => ShippingDebt::codBlocked($user),
            'discount' => $chosen['result']->discount ?? 0,
            'chosenCoupon' => $chosen,
            'autoCoupon' => $auto && $chosen,
            'couponOptions' => $pick['options'],
            'couponNotice' => $couponNotice ?? null,
            'loyalty' => app(LoyaltyCheckout::class)->summary($user, $subtotal, $chosen['result']->discount ?? 0),
            'giftBalance' => app(GiftCardService::class)->balance($user->id),
        ]);
    }

    /**
     * اختيارات الشحن في صفحة إتمام الطلب. تُغيَّر عبر رابط الصفحة (GET) وتُحفظ في الجلسة
     * حتى تبقى بعد أخطاء التحقق. أول زيارة: المحافظة من العنوان الافتراضي إن أمكن.
     */
    private function shippingChoice(Request $request): ShippingChoice
    {
        $keys = ['delivery_method', 'shipping_governorate', 'pickup_stores'];
        if ($request->hasAny($keys)) {
            $input = $request->validate(['delivery_method' => ['nullable', 'string'], ...ShippingChoice::rules()]);
            $request->session()->put('checkout_shipping', [
                'delivery_method' => $input['delivery_method'] ?? 'home',
                'shipping_governorate' => $input['shipping_governorate'] ?? null,
                'pickup_stores' => $input['pickup_stores'] ?? [],
            ]);
        }
        $saved = $request->session()->get('checkout_shipping');
        if ($saved !== null) {
            return ShippingChoice::fromInput($saved);
        }
        $user = $request->user();
        $address = $user->addresses()->orderByDesc('is_default')->orderBy('id')->first();

        return ShippingChoice::fromInput([], trim(($address?->city ?? '').' '.($user->city ?? '').' '.($user->address ?? '')));
    }

    /** اختيار كوبون من المحفظة للطلب الحالي (أو "بدون كوبون"). يُعاد التحقق عند تأكيد الطلب. */
    public function chooseCoupon(Request $request)
    {
        $choice = (string) $request->validate(['choice' => ['required', 'regex:/^(none|\d{1,18})$/']])['choice'];

        if ($choice !== 'none') {
            $owned = CustomerCoupon::whereKey((int) $choice)->where('user_id', $request->user()->id)->exists();
            if (! $owned) {
                return back()->with('error', 'هذا الكوبون غير موجود في محفظتك.');
            }
            $choice = (int) $choice;
        }

        $request->session()->put('checkout_coupon', $choice);

        return back()->with('success', $choice === 'none' ? 'سيُكمل الطلب بدون كوبون.' : 'تم اختيار الكوبون.');
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
            'use_points' => ['nullable', 'integer', 'min:0'],   // نقاط الولاء كخصم
            'use_gift_balance' => ['nullable', 'boolean'],      // رصيد بطاقات الهدايا
            ...ShippingChoice::rules(),
        ], ['pickup_center_id.required_if' => __('اختر مركز الشحن الذي ستستلم منه الطلب.')]);

        // البطاقة فقط حين يكون الدفع الإلكتروني مفعّلاً، وإلا بقي الطلب بلا طريقة دفع حقيقية
        if ($validated['payment_method'] === 'card' && ! app(PaymentService::class)->onlineEnabled()) {
            return back()->withInput()->withErrors(['payment_method' => __('الدفع بالبطاقة غير متاح حالياً. اختر الدفع عند الاستلام.')]);
        }

        if ($validated['payment_method'] === 'card'
            && Order::openCardOrdersCount($request->user()->id) >= Order::MAX_OPEN_CARD_ORDERS) {
            return back()->withInput()->withErrors(['payment_method' => __('لديك طلبات بالبطاقة لم تُدفع بعد. ادفعها أو ألغها أولاً.')]);
        }

        if ($validated['payment_method'] === 'cash_on_delivery' && ShippingDebt::codBlocked($request->user())) {
            return back()->withInput()->withErrors(['payment_method' => ShippingDebt::codBlockedMessage($request->user())]);
        }
        $deliveryMethod = ShippingQuote::method($validated['delivery_method'] ?? null);
        if ($deliveryMethod === 'center') {
            $validated['shipping_address'] = ShippingCenter::findOrFail($validated['pickup_center_id'])->pickupAddress();
        }
        $shipping = ShippingChoice::fromInput($validated, $deliveryMethod === 'home' ? $validated['shipping_address'] : null);

        $items = $request->user()->cartItems()->with(['product.activeDailyDeal', 'variant', 'storefrontVisit'])->get();
        if ($items->isEmpty()) {
            return redirect()->route('customer.cart.index')->with('error', 'سلتك فارغة، أضف منتجات أولاً قبل إتمام الشراء.');
        }

        foreach ($items as $item) {
            $stock = $item->variant ? $item->variant->stock_quantity : $item->product->stock_quantity;
            if (! $item->product->isLive() || $stock < $item->quantity) {
                return back()->with('error', "الكمية المتوفرة من \"{$item->product->name}\" لم تعد كافية، رجاءً حدّث السلة.");
            }
        }

        // السعر يُحسب الآن لا عند الإضافة للسلة: عرض انتهى أو سعر عدّله البائع يظهر للعميل قبل الدفع
        if (CartItem::repriceStale($items)) {
            return redirect()->route('customer.cart.index')->with('error', __('تغيّر سعر بعض المنتجات في سلتك (انتهى عرض أو عدّل البائع السعر). راجع السلة ثم أكّد الطلب.'));
        }

        try {
            $order = DB::transaction(function () use ($items, $validated, $request, $deliveryMethod, $shipping) {
                // نقفل سلة المشتري ونقرؤها من جديد قبل أي شيء: ضغطتان متزامنتان على «تأكيد الطلب»
                // لا تُنشئان طلبين — الثانية تنتظر الأولى، ثم تجد بنود السلة قد حُذفت فتتوقف.
                $lockedIds = $request->user()->cartItems()->lockForUpdate()->pluck('id');
                if ($items->pluck('id')->diff($lockedIds)->isNotEmpty()) {
                    throw new \DomainException('already_ordered');
                }

                $products = collect($items->pluck('product_id'))
                    ->map(fn ($id) => Product::with('activeDailyDeal')->whereKey($id)->lockForUpdate()->first());

                if ($products->contains(fn ($product, $i) => $product === null || ! $product->isLive())) {
                    throw new \DomainException('stock_conflict');
                }

                // نقفل اللون/المقاس ونقرأ كميته من جديد: مشتريان متزامنان لا يأخذان آخر قطعة معاً
                foreach ($items as $item) {
                    if ($item->variant_id) {
                        $item->setRelation('variant', ProductVariant::whereKey($item->variant_id)->lockForUpdate()->first()
                            ?? throw new \DomainException('stock_conflict'));
                    }
                }

                // كمية «عرض اليوم» تُحجز تحت القفل: لا تُباع أكثر من الكمية المحددة
                $dealClaims = app(DailyDealQuota::class)->claim($items);

                $products = $products->keyBy('id');
                $lines = $items->map(function ($item) use ($products) {
                    $product = $products[$item->product_id];
                    $stock = $item->variant ? $item->variant->stock_quantity : $product->stock_quantity;
                    if ($stock < $item->quantity) {
                        throw new \DomainException('stock_conflict');
                    }
                    if (! $item->hasCurrentPrice($product)) {
                        throw new \DomainException('price_changed');
                    }
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

                // كوبون المحفظة: يُقفل ويُعاد فحصه هنا على السلة الفعلية (الخادم هو المرجع)
                $redeemer = app(CouponRedeemer::class);
                $choice = $request->session()->get('checkout_coupon');
                $shippingQuote = $shipping->quote($items, $deliveryMethod);
                $shippingCost = $shippingQuote['fee'];
                [$coupon, $couponResult, $dropped] = $redeemer->resolve($request->user(), $choice, $lines, $shippingCost);
                if ($dropped && is_int($choice)) {
                    // كوبون اختاره العميل ولم يعد صالحاً: لا ننفّذ الطلب بسعر لم يره
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

                // نقاط الولاء بعد الكوبون (المنصة تتحمّل خصمها؛ لا تُوزَّع على البنود ولا تمس أرباح البائع)
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
                    $lineDiscount = $couponResult?->lineDiscounts[$key] ?? 0;
                    $product = $line->product;
                    $item = $line->item;
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
                        'discount_funder' => $lineDiscount > 0 ? $funder : null, // من يتحمّل الخصم عند حساب الأرباح
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
                $request->session()->forget(['checkout_coupon', 'checkout_shipping']);

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
            return back()->withErrors(['use_points' => $e->getMessage()])->withInput();
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'already_ordered') {
                $latest = Order::where('user_id', $request->user()->id)->latest('id')->first();

                return $latest
                    ? redirect()->route('customer.orders.show', $latest)->with('success', __('تم إرسال طلبك مسبقاً، ولن يُكرَّر.'))
                    : redirect()->route('customer.cart.index');
            }
            if ($e->getMessage() === 'stock_conflict') {
                return back()->with('error', 'الكمية المتوفرة من أحد المنتجات لم تعد كافية، رجاءً حدّث السلة.');
            }
            if ($e->getMessage() === 'price_changed') {
                CartItem::repriceStale($request->user()->cartItems()->with(['product.activeDailyDeal', 'variant'])->get());

                return redirect()->route('customer.cart.index')->with('error', __('تغيّر سعر بعض المنتجات في سلتك (انتهى عرض أو عدّل البائع السعر). راجع السلة ثم أكّد الطلب.'));
            }
            if ($e->getMessage() === DailyDealQuota::SOLD_OUT) {
                return back()->with('error', __('نفدت كمية «عرض اليوم» لأحد المنتجات. احذفه من السلة وأضفه من جديد بسعره الحالي.'));
            }
            if (str_starts_with($e->getMessage(), 'coupon:')) {
                $request->session()->forget('checkout_coupon');

                return back()->with('error', 'لم يُنفَّذ الطلب: الكوبون الذي اخترته لم يعد صالحاً ('.substr($e->getMessage(), 7).'). راجع المبلغ الجديد ثم أكّد الطلب.');
            }
            throw $e;
        }

        return $validated['payment_method'] === 'card' && (int) $order->total > 0
            ? redirect()->route('customer.checkout.pay', $order)
            : redirect()->route('customer.orders.show', $order)->with('success', 'تم إنشاء طلبك بنجاح.');
    }
}
