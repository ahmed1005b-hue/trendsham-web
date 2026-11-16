{{--
    نموذج عنوان (إضافة أو تعديل). المتغيرات: $address (أو null للإضافة)، $prefix لمعرّفات فريدة.
--}}
@php
    $errorsBag = $errors->getBag('addresses');
    // القيم القديمة تخص النموذج الذي أُرسل فقط
    $isOld = old('_address_form') === $prefix;
    $val = fn ($field, $default = null) => $isOld ? old($field, $default) : $default;
    $label = $val('label', $address?->label ?? 'home');
@endphp
<form action="{{ $address ? route('profile.addresses.update', $address) : route('profile.addresses.store') }}" method="POST"
      class="ts-address-form" data-ts-address-form>
    @csrf
    @if ($address) @method('PUT') @endif
    <input type="hidden" name="_address_form" value="{{ $prefix }}">

    <fieldset class="mb-3">
        <legend class="form-label fs-6 mb-2">نوع الموقع</legend>
        <div class="btn-group w-100" role="group">
            @foreach (\App\Models\UserAddress::LABELS as $key => $text)
                <input type="radio" class="btn-check" name="label" id="{{ $prefix }}-label-{{ $key }}" value="{{ $key }}" @checked($label === $key) required>
                <label class="btn btn-outline-primary" for="{{ $prefix }}-label-{{ $key }}">
                    <i class="bi {{ ['home' => 'bi-house', 'work' => 'bi-briefcase', 'other' => 'bi-geo-alt'][$key] }}" aria-hidden="true"></i> {{ __('addresses.labels.'.$key) }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="mb-3">
        <label for="{{ $prefix }}-city" class="form-label">المدينة</label>
        <input id="{{ $prefix }}-city" type="text" name="city" class="form-control @if($isOld && $errorsBag->has('city')) is-invalid @endif"
               value="{{ $val('city', $address?->city) }}" maxlength="120" required autocomplete="address-level2">
        @if ($isOld) @error('city', 'addresses') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>

    <div class="mb-3">
        <label for="{{ $prefix }}-details" class="form-label">العنوان التفصيلي</label>
        <textarea id="{{ $prefix }}-details" name="details" rows="2" maxlength="500" required autocomplete="street-address"
                  class="form-control @if($isOld && $errorsBag->has('details')) is-invalid @endif"
                  placeholder="الحي، الشارع، البناء، الطابق، علامة مميزة">{{ $val('details', $address?->details) }}</textarea>
        @if ($isOld) @error('details', 'addresses') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>

    <input type="hidden" name="latitude" value="{{ $val('latitude', $address?->latitude) }}" data-ts-lat>
    <input type="hidden" name="longitude" value="{{ $val('longitude', $address?->longitude) }}" data-ts-lng>
    <div class="mb-3">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-ts-locate data-geocode-url="{{ route('profile.addresses.reverse-geocode') }}">
            <i class="bi bi-crosshair" aria-hidden="true"></i> استخدم موقعي الحالي
        </button>
        <button type="button" class="btn btn-link btn-sm text-danger" data-ts-clear-location @if(! $val('latitude', $address?->latitude)) hidden @endif>إزالة الموقع</button>
        <div class="form-text" data-ts-locate-status aria-live="polite">
            @if ($val('latitude', $address?->latitude))
                تم تحديد الموقع على الخريطة.
            @else
                اختياري: يساعد البائع وشركة الشحن على الوصول إليك بدقة.
            @endif
        </div>
        @if ($isOld) @error('latitude', 'addresses') <div class="text-danger small">{{ $message }}</div> @enderror @endif
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="{{ $prefix }}-default"
               @checked($val('is_default', $address?->is_default))>
        <label class="form-check-label" for="{{ $prefix }}-default">اعتماد هذا العنوان لإرسال الشحنات إليه</label>
    </div>

    <button type="submit" class="btn btn-primary">{{ $address ? 'حفظ التعديل' : 'حفظ العنوان' }}</button>
</form>
