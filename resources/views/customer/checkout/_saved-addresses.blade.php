{{-- اختيار عنوان محفوظ يملأ "عنوان التوصيل" (يبقى قابلاً للتعديل). المتغير: $addresses --}}
@if ($addresses->isNotEmpty())
    <fieldset class="mb-2">
        <legend class="form-label fs-6 mb-2">عناويني المحفوظة</legend>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($addresses as $address)
                <input type="radio" class="btn-check" name="saved_address" id="saved-address-{{ $address->id }}" autocomplete="off"
                       value="{{ $address->id }}" data-ts-saved-address data-text="{{ $address->shippingText() }}" @checked($address->is_default)>
                <label class="btn btn-outline-primary btn-sm text-start" for="saved-address-{{ $address->id }}">
                    <i class="bi {{ ['home' => 'bi-house', 'work' => 'bi-briefcase', 'other' => 'bi-geo-alt'][$address->label] ?? 'bi-geo-alt' }}" aria-hidden="true"></i>
                    {{ $address->labelText() }} — {{ $address->city }}
                    @if ($address->is_default) <span class="badge bg-success ms-1">معتمد</span> @endif
                </label>
            @endforeach
        </div>
    </fieldset>
    <script>
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-ts-saved-address]')) return;
        var box = document.getElementById('f-shipping_address');
        if (box) box.value = e.target.dataset.text;
    });
    </script>
@endif
<div class="form-text mb-2"><a href="{{ route('profile.edit') }}#addresses">إدارة عناويني</a></div>
