@extends('layouts.app')

@section('title', 'إضافة مساحة عرض - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إضافة مساحة عرض</span>
        <a href="{{ route('admin.showcase-slots.index') }}" class="btn btn-outline-secondary btn-sm">عودة</a>
    </div>
</nav>

<div class="container mb-5">
    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-4">بيانات المساحة الجديدة</h2>
            <form action="{{ route('admin.showcase-slots.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="f-key" class="form-label">المفتاح (Key) <span class="text-danger">*</span></label>
                        <input id="f-key" type="text" name="key" class="form-control" required maxlength="40" placeholder="مثال: home_top, home_mid, home_bottom">
                        <div class="form-text">معرّف فريد للمساحة، يستخدم في التطبيق.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="f-name" class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input id="f-name" type="text" name="name" class="form-control" required maxlength="100" placeholder="مثال: أعلى الرئيسية">
                    </div>
                    <div class="col-md-3">
                        <label for="f-position" class="form-label">الموضع <span class="text-danger">*</span></label>
                        <input id="f-position" type="number" name="position" class="form-control" required min="0" value="0">
                        <div class="form-text">ترتيب الظهور (الأقل يظهر أولاً).</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-price_per_day" class="form-label">سعر اليوم <span class="text-danger">*</span></label>
                        <input id="f-price_per_day" type="number" name="price_per_day" class="form-control" required min="0" step="0.01" placeholder="20000">
                        <div class="form-text">بالليرة السورية.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-min_days" class="form-label">أقل عدد أيام <span class="text-danger">*</span></label>
                        <input id="f-min_days" type="number" name="min_days" class="form-control" required min="1" value="1">
                    </div>
                    <div class="col-md-3">
                        <label for="f-max_days" class="form-label">أقصى عدد أيام <span class="text-danger">*</span></label>
                        <input id="f-max_days" type="number" name="max_days" class="form-control" required min="1" value="30">
                    </div>
                    <div class="col-md-3">
                        <label for="f-capacity" class="form-label">السعة <span class="text-danger">*</span></label>
                        <input id="f-capacity" type="number" name="capacity" class="form-control" required min="1" value="1">
                        <div class="form-text">كم عرض متزامن في نفس الفترة.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="f-min_products" class="form-label">أقل عدد منتجات <span class="text-danger">*</span></label>
                        <input id="f-min_products" type="number" name="min_products" class="form-control" required min="1" value="5">
                    </div>
                    <div class="col-md-3">
                        <label for="f-max_products" class="form-label">أقصى عدد منتجات <span class="text-danger">*</span></label>
                        <input id="f-max_products" type="number" name="max_products" class="form-control" required min="1" value="10">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                            <label class="form-check-label" for="is_active">نشط</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-primary">حفظ المساحة</button>
                        <a href="{{ route('admin.showcase-slots.index') }}" class="btn btn-outline-secondary ms-2">إلغاء</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection