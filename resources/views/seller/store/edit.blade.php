@extends('layouts.app')

@section('title', 'إعدادات المتجر - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إعدادات المتجر</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">رجوع للوحة التحكم</a>
    </div>
</nav>

<div class="container mb-5" style="max-inline-size: 960px;">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">معلومات متجرك</h1>
            <p class="text-muted mb-4">أضف شعاراً وغلافاً وبيانات التواصل التي تظهر لزوار متجرك.</p>

            <form action="{{ route('seller.store.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <label for="store_banner" class="form-label">غلاف المتجر (1200×400)</label>
                        @if ($profile->store_banner)
                            <div class="mb-2">
                                <img src="{{ asset('storage/'.$profile->store_banner) }}" alt="غلاف المتجر الحالي" class="rounded border w-100" style="max-block-size: 240px; object-fit: cover;">
                            </div>
                        @endif
                        <input id="store_banner" type="file" name="store_banner" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('store_banner') is-invalid @enderror">
                        @error('store_banner') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">JPG أو PNG أو WEBP، بحد أقصى 4 ميغابايت. اتركه فارغاً للإبقاء على الغلاف الحالي.</div>
                    </div>
                    <div class="col-12">
                        <label for="store_logo" class="form-label">شعار المتجر (512×512)</label>
                        <div class="d-flex align-items-center gap-3">
                            @if ($profile->store_logo)
                                <img src="{{ asset('storage/'.$profile->store_logo) }}" alt="شعار المتجر الحالي" class="rounded-circle border" width="88" height="88" style="object-fit: cover;">
                            @endif
                            <input id="store_logo" type="file" name="store_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('store_logo') is-invalid @enderror">
                        </div>
                        @error('store_logo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="form-text">JPG أو PNG أو WEBP، بحد أقصى 4 ميغابايت. اتركه فارغاً للإبقاء على الشعار الحالي.</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="store_name" class="form-label">اسم المتجر <span class="text-danger">*</span></label>
                        <input id="store_name" type="text" name="store_name" maxlength="255" required value="{{ old('store_name', $profile->store_name) }}" class="form-control @error('store_name') is-invalid @enderror">
                        @error('store_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="store_contact_phone" class="form-label">هاتف التواصل</label>
                        <input id="store_contact_phone" type="text" name="store_contact_phone" maxlength="32" value="{{ old('store_contact_phone', $profile->store_contact_phone) }}" class="form-control @error('store_contact_phone') is-invalid @enderror">
                        @error('store_contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="store_description" class="form-label">نبذة عن المتجر</label>
                        <textarea id="store_description" name="store_description" rows="4" maxlength="2000" class="form-control @error('store_description') is-invalid @enderror">{{ old('store_description', $profile->store_description) }}</textarea>
                        @error('store_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="store_whatsapp" class="form-label">واتساب</label>
                        <input id="store_whatsapp" type="text" name="store_whatsapp" maxlength="2048" value="{{ old('store_whatsapp', $profile->store_whatsapp) }}" class="form-control @error('store_whatsapp') is-invalid @enderror" placeholder="رقم هاتف أو رابط واتساب">
                        @error('store_whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="store_instagram" class="form-label">رابط إنستغرام</label>
                        <input id="store_instagram" type="url" name="store_instagram" maxlength="2048" value="{{ old('store_instagram', $profile->store_instagram) }}" class="form-control @error('store_instagram') is-invalid @enderror" placeholder="https://instagram.com/…">
                        @error('store_instagram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="business_hours" class="form-label">أوقات العمل</label>
                        <textarea id="business_hours" name="business_hours" rows="3" maxlength="2000" class="form-control @error('business_hours') is-invalid @enderror" placeholder="مثال: يومياً من 9 صباحاً حتى 6 مساءً">{{ old('business_hours', $profile->business_hours) }}</textarea>
                        @error('business_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <h3 class="h6 mb-1"><i class="bi bi-truck" aria-hidden="true"></i> {{ __('الشحن مع شركة كرم') }}</h3>
                            <p class="small text-muted mb-2">{{ __('اختر مركز كرم الذي تسلّم فيه طرودك، أو اطلب أن يستلمها المندوب من متجرك.') }}</p>
                            <label for="dropoff_center_id" class="form-label small mb-1">{{ __('مركز التسليم') }}</label>
                            <select id="dropoff_center_id" name="dropoff_center_id" class="form-select mb-2 @error('dropoff_center_id') is-invalid @enderror">
                                <option value="">{{ __('اختر المركز الأقرب لك') }}</option>
                                @foreach (\App\Models\ShippingCenter::groupedForSelect() as $governorate => $governorateCenters)
                                    <optgroup label="{{ __($governorate) }}">
                                        @foreach ($governorateCenters as $center)
                                            <option value="{{ $center->id }}" translate="no" @selected((int) old('dropoff_center_id', $profile->dropoff_center_id) === $center->id)>{{ $center->name }} - {{ $center->address }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('dropoff_center_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <input type="hidden" name="courier_pickup" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="courier_pickup" value="1" id="courier_pickup" @checked(old('courier_pickup', $profile->courier_pickup))>
                                <label class="form-check-label small" for="courier_pickup">{{ __('أريد أن يستلم المندوب الطرود من متجري') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="shipping_policy" class="form-label">سياسة الشحن</label>
                        <textarea id="shipping_policy" name="shipping_policy" rows="3" maxlength="4000" class="form-control @error('shipping_policy') is-invalid @enderror">{{ old('shipping_policy', $profile->shipping_policy) }}</textarea>
                        @error('shipping_policy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="return_policy" class="form-label">سياسة الاستبدال والإرجاع</label>
                        <textarea id="return_policy" name="return_policy" rows="3" maxlength="4000" class="form-control @error('return_policy') is-invalid @enderror">{{ old('return_policy', $profile->return_policy) }}</textarea>
                        @error('return_policy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> حفظ بيانات المتجر</button>
                    <a href="{{ route('stores.show', $profile) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">معاينة المتجر</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
