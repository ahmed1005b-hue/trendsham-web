@extends('layouts.app')

@section('title', 'إتمام الطلب - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إتمام الطلب</span>
        <a href="{{ route('customer.cart.index') }}" class="btn btn-outline-secondary btn-sm">رجوع للسلة</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">بيانات الشحن والدفع</h2>
                    <form action="{{ route('customer.checkout.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="f-shipping_name" class="form-label">الاسم الكامل للاستلام</label>
                            <input id="f-shipping_name" type="text" name="shipping_name" class="form-control" value="{{ old('shipping_name', auth()->user()->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="f-shipping_phone" class="form-label">رقم الهاتف</label>
                            <input id="f-shipping_phone" type="tel" name="shipping_phone" class="form-control" value="{{ old('shipping_phone', auth()->user()->phone) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="f-shipping_address" class="form-label">عنوان التوصيل الكامل</label>
                            @php $tsAddresses = auth()->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(); @endphp
                            @include('customer.checkout._saved-addresses', ['addresses' => $tsAddresses])
                            <textarea id="f-shipping_address" name="shipping_address" class="form-control" rows="3" required>{{ old('shipping_address', $tsAddresses->firstWhere('is_default', true)?->shippingText() ?? auth()->user()->address) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label for="f-payment_method" class="form-label">طريقة الدفع</label>
                            <select id="f-payment_method" name="payment_method" class="form-select">
                                <option value="cash_on_delivery">الدفع عند الاستلام</option>
                                @if (app(\App\Services\PaymentService::class)->onlineEnabled())
                                    <option value="card" @selected(old('payment_method') === 'card')>بطاقة بنكية (دفع إلكتروني آمن)</option>
                                @endif
                                <option value="wallet">محفظة إلكترونية</option>
                            </select>
                        </div>
                        @if ($loyalty['enabled'] && $loyalty['balance'] > 0)
                            <div class="ts-pts-use mb-3" id="tsPointsBox">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="tsUsePoints" class="form-label fw-bold mb-0"><i class="bi bi-gem" aria-hidden="true"></i> استخدم نقاطي</label>
                                    <span class="small text-muted">{{ __('رصيدك :points', ['points' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($loyalty['balance'])]) }}</span>
                                </div>
                                @if ($loyalty['max_usable'] > 0)
                                    <div class="input-group">
                                        <input type="number" name="use_points" id="tsUsePoints" class="form-control @error('use_points') is-invalid @enderror"
                                               min="0" max="{{ $loyalty['max_usable'] }}" step="1" value="{{ old('use_points', 0) }}"
                                               data-value="{{ $loyalty['point_value'] }}" data-min="{{ $loyalty['min_redeem'] }}">
                                        <button type="button" class="btn btn-outline-success" id="tsUseAll">{{ __('استخدم :n', ['n' => number_format($loyalty['max_usable'])]) }}</button>
                                    </div>
                                    <div class="form-text">{{ __('النقطة = :value ل.س. الحد الأدنى :min، ولا تغطي الشحن.', ['value' => number_format($loyalty['point_value'] / 100), 'min' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($loyalty['min_redeem'])]) }}</div>
                                @else
                                    <div class="form-text">{{ __('تحتاج :min على الأقل، وقيمة سلتك تكفيها، لاستخدام النقاط.', ['min' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($loyalty['min_redeem'])]) }}</div>
                                @endif
                                @error('use_points')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                        @if ($giftBalance > 0)
                            <div class="form-check border rounded p-2 ps-5 mb-3">
                                <input class="form-check-input" type="checkbox" name="use_gift_balance" value="1" id="tsUseGift" @checked(old('use_gift_balance', true))>
                                <label class="form-check-label" for="tsUseGift">
                                    <i class="bi bi-gift" aria-hidden="true"></i> {{ __('ادفع من رصيد الهدايا (:amount)', ['amount' => number_format($giftBalance / 100).' ل.س']) }}
                                    <span class="d-block small text-muted">يُخصم من الإجمالي عند تأكيد الطلب، والباقي بطريقة الدفع التي اخترتها.</span>
                                </label>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary w-100">تأكيد الطلب</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0"><i class="bi bi-ticket-perforated" aria-hidden="true"></i> الكوبونات</h2>
                        <a href="{{ route('customer.coupons.index') }}" class="small">كوبوناتي</a>
                    </div>

                    @if ($couponNotice)
                        <div class="alert alert-warning py-2 small mb-2">{{ $couponNotice }}</div>
                    @endif
                    @if ($chosenCoupon)
                        <div class="alert alert-success py-2 small mb-2">
                            <i class="bi bi-stars" aria-hidden="true"></i>
                            {{ $autoCoupon ? __('طبّقنا لك أفضل كوبون') : __('الكوبون المختار') }}: {{ __('وفّرت') }}
                            <strong>{{ number_format($chosenCoupon['result']->saving() / 100) }} ل.س</strong>
                        </div>
                        @include('customer.coupons._card', ['coupon' => $chosenCoupon['coupon']])
                    @elseif (empty($couponOptions))
                        <p class="small text-muted mb-0">لا توجد كوبونات في محفظتك حالياً.</p>
                    @else
                        <p class="small text-muted mb-2">بدون كوبون لهذا الطلب.</p>
                    @endif

                    @if (! empty($couponOptions))
                        <button class="btn btn-sm btn-outline-secondary w-100 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#ts-coupon-options" aria-expanded="false">
                            {{ __('تغيير الكوبون (:n)', ['n' => count($couponOptions)]) }}
                        </button>
                        <div class="collapse mt-2" id="ts-coupon-options">
                            <div class="d-grid gap-2">
                                @foreach ($couponOptions as $opt)
                                    @php($isChosen = $chosenCoupon && $chosenCoupon['coupon']->id === $opt['coupon']->id)
                                    <form action="{{ route('customer.checkout.coupon') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="choice" value="{{ $opt['coupon']->id }}">
                                        <button class="btn p-0 border-0 w-100 text-start" @disabled(! $opt['result']->ok || $isChosen) aria-label="{{ __('اختيار :name', ['name' => $opt['coupon']->campaign->title]) }}">
                                            @include('customer.coupons._card', [
                                                'coupon' => $opt['coupon'],
                                                'muted' => ! $opt['result']->ok,
                                                'note' => $opt['result']->ok
                                                    ? ($isChosen ? '✓ '.__('مُطبّق') : __('وفّر :amount ل.س', ['amount' => number_format($opt['result']->saving() / 100)]))
                                                    : $opt['result']->reason,
                                            ])
                                        </button>
                                    </form>
                                @endforeach
                                @if ($chosenCoupon)
                                    <form action="{{ route('customer.checkout.coupon') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="choice" value="none">
                                        <button class="btn btn-sm btn-link text-muted w-100">إكمال الطلب بدون كوبون</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">ملخص الطلب</h2>
                    @foreach ($items as $item)
                        <div class="mb-2 pb-2 border-bottom">
                            <div class="d-flex justify-content-between small">
                                <span translate="no">{{ $item->product->name }} × {{ $item->quantity }}</span>
                                <span>{{ number_format($item->subtotal / 100) }} ل.س</span>
                            </div>
                            <div class="small text-muted">
                                {{ $item->product->formatted_effective_price }}
                                @if (($item->product->effective_price ?? $item->product->price) < $item->product->price)
                                    <del>{{ $item->product->formatted_price }}</del>
                                    <span class="text-success">عرض اليوم</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between small mb-1">
                        <span>المجموع الفرعي</span>
                        <span>{{ number_format($subtotal / 100) }} ل.س</span>
                    </div>
                    @if ($discount > 0)
                        <div class="d-flex justify-content-between small mb-1 text-success">
                            <span>خصم الكوبون <span class="text-muted">({{ $chosenCoupon['coupon']->campaign->sourceLabel() }})</span></span>
                            <span>- {{ number_format($discount / 100) }} ل.س</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between small mb-2">
                        <span>رسوم الشحن</span>
                        <span>
                            @if ($shippingFee < $baseShippingFee)
                                <del class="text-muted">{{ number_format($baseShippingFee / 100) }}</del>
                                <span class="text-success fw-bold">{{ $shippingFee === 0 ? __('مجاني') : number_format($shippingFee / 100).' ل.س' }}</span>
                            @else
                                {{ number_format($shippingFee / 100) }} ل.س
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1 text-success d-none" id="tsPointsLine">
                        <span>خصم النقاط <span class="text-muted">(من تريندشام)</span></span>
                        <span>- <span id="tsPointsDiscount">0</span> ل.س</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold h6">
                        <span>الإجمالي</span>
                        <span><span id="tsGrandTotal" data-base="{{ $subtotal + $shippingFee - $discount }}">{{ number_format(($subtotal + $shippingFee - $discount) / 100) }}</span> ل.س</span>
                    </div>
                    @if ($loyalty['enabled'])
                        <p class="small mb-2" style="color:#9A7425"><i class="bi bi-gem" aria-hidden="true"></i>
                            <span id="tsWillEarn" data-payable="{{ $subtotal - $discount }}" data-spend="{{ app(\App\Loyalty\LoyaltySettings::class)->spendPerPoint() }}">
                                {{ $loyalty['will_earn'] > 0 ? __('ستكسب :points عند استلام الطلب', ['points' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($loyalty['will_earn'])]) : '' }}
                            </span>
                        </p>
                    @endif
                    <p class="small text-muted mb-0">يتم تأكيد السعر النهائي عند إنشاء الطلب وفق العروض النشطة وقت الشراء.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($loyalty['enabled'] && $loyalty['max_usable'] > 0)
<script>
(() => {
    const input = document.getElementById('tsUsePoints');
    const all = document.getElementById('tsUseAll');
    const line = document.getElementById('tsPointsLine');
    const out = document.getElementById('tsPointsDiscount');
    const total = document.getElementById('tsGrandTotal');
    const earn = document.getElementById('tsWillEarn');
    const value = +input.dataset.value, max = +input.max;
    const T = {{ Js::from(['earn' => __('ستكسب :points عند استلام الطلب'), 'one' => __('نقطة واحدة'), 'two' => __('نقطتين'), 'few' => __(':n نقاط'), 'many' => __(':n نقطة')]) }};
    const label = (n) => n === 1 ? T.one : n === 2 ? T.two : (n <= 10 ? T.few : T.many).replace(':n', n);
    const fmt = (minor) => Math.round(minor / 100).toLocaleString('en-US');
    const sync = () => {
        let n = Math.max(0, Math.min(max, parseInt(input.value || '0', 10) || 0));
        const d = n * value;
        line.classList.toggle('d-none', n === 0);
        out.textContent = fmt(d);
        total.textContent = fmt(+total.dataset.base - d);
        if (earn) {
            const pts = Math.floor((+earn.dataset.payable - d) / +earn.dataset.spend);
            earn.textContent = pts > 0 ? T.earn.replace(':points', label(pts)) : '';
        }
    };
    input.addEventListener('input', sync);
    all.addEventListener('click', () => { input.value = max; sync(); });
    sync();
})();
</script>
@endif
@endsection
