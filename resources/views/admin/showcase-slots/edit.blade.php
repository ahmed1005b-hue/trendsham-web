@extends('layouts.app')

@section('title', 'تعديل مساحة عرض - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - تعديل مساحة عرض</span>
        <a href="{{ route('admin.showcase-slots.index') }}" class="btn btn-outline-secondary btn-sm">عودة</a>
    </div>
</nav>

<div class="container mb-5">
    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="fw-bold mb-1">تعذر حفظ التعديلات:</div>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <h2 class="h5 mb-4">تعديل المساحة: {{ $showcaseSlot->name }}</h2>
            <form action="{{ route('admin.showcase-slots.update', $showcaseSlot) }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="f-key" class="form-label">المفتاح (Key) <span class="text-danger">*</span></label>
                        <input id="f-key" type="text" name="key" class="form-control" required maxlength="40" value="{{ old('key', $showcaseSlot->key) }}">
                        <div class="form-text">معرّف فريد للمساحة، يستخدم في التطبيق.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="f-name" class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input id="f-name" type="text" name="name" class="form-control" required maxlength="100" value="{{ old('name', $showcaseSlot->name) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-position" class="form-label">الموضع <span class="text-danger">*</span></label>
                        <input id="f-position" type="number" name="position" class="form-control" required min="0" value="{{ old('position', $showcaseSlot->position) }}">
                        <div class="form-text">ترتيب الظهور (الأقل يظهر أولاً).</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-price_per_day" class="form-label">سعر اليوم <span class="text-danger">*</span></label>
                        <input id="f-price_per_day" type="number" name="price_per_day" class="form-control" required min="0" step="0.01" value="{{ old('price_per_day', $showcaseSlot->price_per_day) }}">
                        <div class="form-text">بالليرة السورية.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-min_days" class="form-label">أقل عدد أيام <span class="text-danger">*</span></label>
                        <input id="f-min_days" type="number" name="min_days" class="form-control" required min="1" value="{{ old('min_days', $showcaseSlot->min_days) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-max_days" class="form-label">أقصى عدد أيام <span class="text-danger">*</span></label>
                        <input id="f-max_days" type="number" name="max_days" class="form-control" required min="1" value="{{ old('max_days', $showcaseSlot->max_days) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-capacity" class="form-label">السعة <span class="text-danger">*</span></label>
                        <input id="f-capacity" type="number" name="capacity" class="form-control" required min="1" value="{{ old('capacity', $showcaseSlot->capacity) }}">
                        <div class="form-text">كم عرض متزامن في نفس الفترة.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-min_products" class="form-label">أقل عدد منتجات <span class="text-danger">*</span></label>
                        <input id="f-min_products" type="number" name="min_products" class="form-control" required min="1" value="{{ old('min_products', $showcaseSlot->min_products) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-max_products" class="form-label">أقصى عدد منتجات <span class="text-danger">*</span></label>
                        <input id="f-max_products" type="number" name="max_products" class="form-control" required min="1" value="{{ old('max_products', $showcaseSlot->max_products) }}">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" {{ old('is_active', $showcaseSlot->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">نشط</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        <a href="{{ route('admin.showcase-slots.index') }}" class="btn btn-outline-secondary ms-2">إلغاء</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
