@extends('layouts.app')

@section('title', 'إعدادات الشحن - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('إعدادات الشحن') }}</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-light btn-sm">لوحة المتجر</a>
    </div>
</nav>

<div class="container mb-5 ts-ship-settings">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- حالة التوصيل الخاص ودين المتجر --}}
    @if ($setting->mode === 'self')
        @if ($status['active'])
            <div class="alert alert-success d-flex gap-2 align-items-start">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div>{{ __('التوصيل الخاص يعمل الآن. طلبات المحافظات التي حددتها توصلها بنفسك، وباقي المحافظات تُشحن عبر شركة الشحن.') }}</div>
            </div>
        @else
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-pause-circle-fill" aria-hidden="true"></i>
                <div>{{ $status['reason'] }} {{ __('حتى ذلك الحين تُشحن كل طلباتك عبر شركة الشحن.') }}</div>
            </div>
        @endif
    @endif
    @if ($status['debt'] > 0)
        <div class="ts-debt-card mb-4">
            <div>
                <div class="small text-muted">{{ __('المبلغ الذي عليك للمنصة (عمولات طلبات حصّلت ثمنها بنفسك)') }}</div>
                <div class="ts-debt-card__amount">{{ number_format($status['debt'] / 100) }} ل.س</div>
                @if ($status['limit'] > 0)
                    <div class="small text-muted">{{ __('الحد الأعلى المسموح: :limit ل.س', ['limit' => number_format($status['limit'] / 100)]) }}</div>
                @endif
            </div>
            <a href="{{ route('seller.wallet.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('محفظتي') }}</a>
        </div>
    @endif

    <form action="{{ route('seller.shipping.update') }}" method="POST" class="row g-4">
        @csrf
        @method('PUT')

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">{{ __('من يوصل طلباتك؟') }}</h2>
                    <div class="ts-delivery">
                        <label class="ts-delivery__opt">
                            <input type="radio" name="mode" value="platform" class="ts-delivery__radio" @checked(old('mode', $setting->mode) === 'platform')>
                            <i class="bi bi-truck" aria-hidden="true"></i>
                            <span class="flex-grow-1"><strong class="d-block">{{ __('شركة الشحن (كرم)') }}</strong>
                                <small class="text-muted">{{ __('تسلّم الطرد لمركز الشحن، وهم يوصلونه لكل المحافظات.') }}</small></span>
                        </label>
                        <label class="ts-delivery__opt">
                            <input type="radio" name="mode" value="self" class="ts-delivery__radio" @checked(old('mode', $setting->mode) === 'self')>
                            <i class="bi bi-scooter" aria-hidden="true"></i>
                            <span class="flex-grow-1"><strong class="d-block">{{ __('أوصل بنفسي') }}</strong>
                                <small class="text-muted">{{ __('بموظف توصيل عندك أو شركة تتعامل معها، وبأسعارك أنت.') }}</small></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="tsSelfBox" @if (old('mode', $setting->mode) !== 'self') hidden @endif>
                <div class="card-body">
                    <h2 class="h5 mb-1">{{ __('المحافظات التي توصل إليها بنفسك') }}</h2>
                    <p class="small text-muted mb-3">{{ __('علّم المحافظة واكتب سعر التوصيل إليها. المحافظات التي لا تعلّمها تُشحن طلباتها عبر شركة الشحن.') }}</p>
                    @error('rates') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror

                    <div class="input-group input-group-sm mb-3 ts-fill-all">
                        <span class="input-group-text">{{ __('نفس السعر للمحافظات المعلّمة') }}</span>
                        <input type="number" min="0" step="1" class="form-control" id="tsFillFee" placeholder="5000">
                        <button type="button" class="btn btn-outline-secondary" id="tsFillBtn">{{ __('تطبيق') }}</button>
                    </div>

                    <div class="ts-rates">
                        @foreach ($governorates as $i => $governorate)
                            @php($enabled = old("rates.$governorate.enabled", $rates->has($governorate)))
                            <div class="ts-rate">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="rates[{{ $governorate }}][enabled]" value="1" id="tsRate{{ $i }}" data-ts-rate-check @checked($enabled)>
                                    <label class="form-check-label" for="tsRate{{ $i }}">{{ __($governorate) }}</label>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="number" min="0" step="1" class="form-control" name="rates[{{ $governorate }}][fee]" data-ts-rate-fee
                                           value="{{ old("rates.$governorate.fee", $rates->has($governorate) ? $rates[$governorate] / 100 : '') }}" aria-label="{{ __('سعر التوصيل إلى :place', ['place' => __($governorate)]) }}" @disabled(! $enabled)>
                                    <span class="input-group-text">ل.س</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <label class="form-label mt-3 mb-1" for="tsFreeOver">{{ __('شحن مجاني إذا اشترى الزبون منك بأكثر من') }}</label>
                    <div class="input-group mb-1">
                        <input type="number" min="0" step="1" class="form-control" name="free_over" id="tsFreeOver" value="{{ old('free_over', $setting->free_over ? $setting->free_over / 100 : '') }}" placeholder="100000">
                        <span class="input-group-text">ل.س</span>
                    </div>
                    <div class="form-text mb-3">{{ __('اتركه فارغاً إذا لا تريد شحناً مجانياً.') }}</div>

                    <label class="form-label mb-1" for="tsDeliveryDays">{{ __('كم يوماً يستغرق التوصيل بعد التجهيز؟') }}</label>
                    <select name="delivery_days" id="tsDeliveryDays" class="form-select">
                        @foreach ([0 => __('في نفس اليوم'), 1 => __('يوم واحد'), 2 => __('يومان'), 3 => __('3 أيام'), 4 => __('4 أيام'), 5 => __('5 أيام'), 7 => __('أسبوع')] as $days => $label)
                            <option value="{{ $days }}" @selected((int) old('delivery_days', $setting->delivery_days) === $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">{{ __('تجهيز الطلب') }}</h2>
                    <label class="form-label mb-1" for="tsPrepDays">{{ __('خلال كم يوم تجهّز الطلب وتسلّمه للتوصيل؟') }}</label>
                    <select name="prep_days" id="tsPrepDays" class="form-select mb-1">
                        @foreach ([0 => __('في نفس اليوم'), 1 => __('يوم واحد'), 2 => __('يومان'), 3 => __('3 أيام'), 5 => __('5 أيام')] as $days => $label)
                            <option value="{{ $days }}" @selected((int) old('prep_days', $setting->prep_days ?? 1) === $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('يظهر للزبون قبل الشراء: «يصل خلال ... أيام».') }}</div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">{{ __('الاستلام من محلك') }}</h2>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="allow_pickup" value="1" id="tsAllowPickup" @checked(old('allow_pickup', $setting->allow_pickup))>
                        <label class="form-check-label" for="tsAllowPickup">{{ __('أسمح للزبون أن يأتي ويستلم طلبه من محلي بدون أجرة شحن') }}</label>
                    </div>
                    <div id="tsPickupBox" @unless (old('allow_pickup', $setting->allow_pickup)) hidden @endunless>
                        <label class="form-label mb-1" for="tsPickupAddress">{{ __('عنوان المحل') }}</label>
                        <textarea name="pickup_address" id="tsPickupAddress" rows="2" class="form-control @error('pickup_address') is-invalid @enderror" maxlength="500"
                                  placeholder="{{ __('مثال: دمشق - الحمرا - بناء 12 - بجانب الصيدلية') }}">{{ old('pickup_address', $setting->pickup_address) }}</textarea>
                        @error('pickup_address') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="card mb-4 ts-ship-money">
                <div class="card-body">
                    <h2 class="h6 mb-2"><i class="bi bi-cash-coin" aria-hidden="true"></i> {{ __('كيف يُحسب المال عندما توصل بنفسك؟') }}</h2>
                    <ul class="small mb-0 ps-3">
                        <li>{{ __('أجرة التوصيل كلها لك.') }}</li>
                        <li>{{ __('إذا دفع الزبون نقداً عند الاستلام، فالمال في يدك، ويبقى عليك عمولة المنصة فقط.') }}</li>
                        <li>{{ __('العمولة تُخصم من رصيد محفظتك، وإن لم يكفِ تصبح ديناً يُخصم من مبيعاتك القادمة.') }}</li>
                        <li>{{ __('إذا وصل الدين إلى الحد الأعلى يتوقف التوصيل الخاص حتى تسدّده.') }}</li>
                        @if ($status['min_orders'] > 0)
                            <li>{{ __('يعمل التوصيل الخاص بعد :n طلبات ناجحة عبر شركة الشحن، ولمتجر موثّق.', ['n' => $status['min_orders']]) }}</li>
                        @endif
                    </ul>
                </div>
            </div>

            <button class="btn btn-primary w-100">{{ __('حفظ إعدادات الشحن') }}</button>
        </div>
    </form>
</div>

<script>
(() => {
    const selfBox = document.getElementById('tsSelfBox');
    document.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', () => { selfBox.hidden = r.value !== 'self' || !r.checked; }));
    document.querySelectorAll('[data-ts-rate-check]').forEach(box => box.addEventListener('change', () => {
        const fee = box.closest('.ts-rate').querySelector('[data-ts-rate-fee]');
        fee.disabled = !box.checked;
        if (box.checked) fee.focus();
    }));
    document.getElementById('tsFillBtn').addEventListener('click', () => {
        const value = document.getElementById('tsFillFee').value;
        if (value === '') return;
        document.querySelectorAll('[data-ts-rate-fee]:not(:disabled)').forEach(f => { f.value = value; });
    });
    const pickup = document.getElementById('tsAllowPickup');
    pickup.addEventListener('change', () => { document.getElementById('tsPickupBox').hidden = !pickup.checked; });
})();
</script>
@endsection
