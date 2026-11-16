{{-- عناوين الشحن المحفوظة (حتى 3) — UserAddressService يفرض القواعد. --}}
@php
    $addresses = $user->addresses()->orderByDesc('is_default')->orderBy('id')->get();
    $max = \App\Models\UserAddress::MAX_PER_USER;
    $openForm = old('_address_form');
@endphp
<div class="card mb-4" id="addresses">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h2 class="h5 mb-0">عناويني</h2>
            <span class="small text-muted">{{ __(':count من :max', ['count' => $addresses->count(), 'max' => $max]) }}</span>
        </div>
        <p class="small text-muted mb-3">{{ __('احفظ حتى :max عناوين (البيت، العمل...) واختر العنوان المعتمد لإرسال الشحنات.', ['max' => $max]) }}</p>

        @error('address', 'addresses') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror

        @forelse ($addresses as $address)
            <div class="ts-address border rounded-3 p-3 mb-2 {{ $address->is_default ? 'is-default' : '' }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="badge bg-body-secondary text-body border"><i class="bi {{ ['home' => 'bi-house', 'work' => 'bi-briefcase', 'other' => 'bi-geo-alt'][$address->label] ?? 'bi-geo-alt' }}" aria-hidden="true"></i> {{ $address->labelText() }}</span>
                    @if ($address->is_default)
                        <span class="badge bg-success">معتمد للشحن</span>
                    @endif
                </div>
                <div class="fw-semibold">{{ $address->city }}</div>
                <div class="small text-muted" style="white-space: pre-line;">{{ $address->details }}</div>
                @if ($address->mapUrl())
                    <a href="{{ $address->mapUrl() }}" target="_blank" rel="noopener" class="small"><i class="bi bi-map" aria-hidden="true"></i> عرض على الخريطة</a>
                @endif

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                            data-bs-target="#edit-address-{{ $address->id }}" aria-expanded="{{ $openForm === 'edit-'.$address->id ? 'true' : 'false' }}"
                            aria-controls="edit-address-{{ $address->id }}">تعديل</button>
                    @unless ($address->is_default)
                        <form action="{{ route('profile.addresses.default', $address) }}" method="POST">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">اعتماده للشحن</button>
                        </form>
                    @endunless
                    <form action="{{ route('profile.addresses.destroy', $address) }}" method="POST" data-confirm="حذف هذا العنوان؟">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                    </form>
                </div>
                <div class="collapse mt-3 {{ $openForm === 'edit-'.$address->id ? 'show' : '' }}" id="edit-address-{{ $address->id }}">
                    @include('profile._address-form', ['address' => $address, 'prefix' => 'edit-'.$address->id])
                </div>
            </div>
        @empty
            <div class="text-muted small mb-3">لا توجد عناوين محفوظة بعد.</div>
        @endforelse

        @if ($addresses->count() < $max)
            <details class="mt-3" @if($openForm === 'new' || $addresses->isEmpty()) open @endif>
                <summary class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة عنوان</summary>
                <div class="mt-3">
                    @include('profile._address-form', ['address' => null, 'prefix' => 'new'])
                </div>
            </details>
        @endif
    </div>
</div>

<script>
(function () {
    // "استخدم موقعي الحالي": يتطلب اتصالاً آمناً (https أو localhost) وإذن الموقع من المتصفح
    // بعد الإحداثيات نقترح المدينة والحي/الشارع، ونملأ الحقول الفارغة فقط (لا نمسح ما كتبه المستخدم)
    function fillFromCoords(form, btn, status) {
        var city = form.querySelector('[name="city"]');
        var details = form.querySelector('[name="details"]');
        var url = btn.dataset.geocodeUrl + '?latitude=' + encodeURIComponent(form.querySelector('[data-ts-lat]').value)
            + '&longitude=' + encodeURIComponent(form.querySelector('[data-ts-lng]').value);
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { data: null }; })
            .then(function (json) {
                var d = json && json.data;
                var filled = false;
                if (d && d.city && !city.value.trim()) { city.value = d.city; filled = true; }
                if (d && d.details && !details.value.trim()) { details.value = d.details + @json(__('،')) + ' '; filled = true; }
                status.textContent = filled
                    ? @json(__('تم تحديد الموقع وتعبئة المنطقة. راجعها وأكمل رقم البناء والطابق.'))
                    : @json(__('تم تحديد الموقع على الخريطة.'));
                if (filled && details.value) {
                    details.focus();
                    details.setSelectionRange(details.value.length, details.value.length);
                }
            })
            .catch(function () { status.textContent = @json(__('تم تحديد الموقع على الخريطة.')); });
    }
    document.querySelectorAll('[data-ts-address-form]').forEach(function (form) {
        var btn = form.querySelector('[data-ts-locate]');
        var clear = form.querySelector('[data-ts-clear-location]');
        var lat = form.querySelector('[data-ts-lat]');
        var lng = form.querySelector('[data-ts-lng]');
        var status = form.querySelector('[data-ts-locate-status]');
        if (!btn) return;
        if (!window.isSecureContext || !navigator.geolocation) {
            btn.disabled = true;
            status.textContent = @json(__('تحديد الموقع غير متاح على هذا الاتصال؛ يكفي العنوان المكتوب.'));
            return;
        }
        btn.addEventListener('click', function () {
            btn.disabled = true;
            status.textContent = @json(__('جارٍ تحديد موقعك...'));
            navigator.geolocation.getCurrentPosition(function (pos) {
                lat.value = pos.coords.latitude.toFixed(7);
                lng.value = pos.coords.longitude.toFixed(7);
                status.textContent = @json(__('تم تحديد الموقع. جارٍ جلب اسم المنطقة...'));
                clear.hidden = false;
                fillFromCoords(form, btn, status).finally(function () { btn.disabled = false; });
            }, function () {
                status.textContent = @json(__('تعذّر تحديد الموقع. تأكد من السماح بالوصول إلى الموقع، أو اكتفِ بالعنوان المكتوب.'));
                btn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 15000 });
        });
        clear.addEventListener('click', function () {
            lat.value = ''; lng.value = '';
            clear.hidden = true;
            status.textContent = @json(__('اختياري: يساعد البائع وشركة الشحن على الوصول إليك بدقة.'));
        });
    });
})();
</script>
