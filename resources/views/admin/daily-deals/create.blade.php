@extends('layouts.app')

@section('title', 'إضافة عرض يومي - تريندشام')

@section('content')
<div class="container-fluid px-4">
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إضافة عرض يومي</span>
            <a href="{{ route('admin.daily-deals.index') }}" class="btn btn-outline-secondary btn-sm">الرجوع للقائمة</a>
        </div>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">إضافة منتج جديد للعرض اليومي</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.daily-deals.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="f-product_id" class="form-label">المنتج</label>
                            <select id="f-product_id" name="product_id" class="form-select" required>
                                <option value="">اختر منتجًا...</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->vendor->store_name ?? 'غير محدد' }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="f-discount_percentage" class="form-label">نسبة الخصم (%)</label>
                                <input id="f-discount_percentage" type="number" name="discount_percentage" class="form-control" min="1" max="90" step="0.01" required placeholder="مثل: 20">
                            </div>
                            <div class="col-md-6">
                                <label for="f-special_price" class="form-label">السعر الخاص (اختياري)</label>
                                <input id="f-special_price" type="number" name="special_price" class="form-control" min="0" step="0.01" placeholder="اترك فارغًا لحساب تلقائي">
                                <div class="form-text">إذا تركت فارغًا، سيتم حساب السعر تلقائيًا بناءً على النسبة المئوية.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="f-starts_at" class="form-label">تاريخ البدء</label>
                                <input id="f-starts_at" type="datetime-local" name="starts_at" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="f-ends_at" class="form-label">تاريخ النهاية</label>
                                <input id="f-ends_at" type="datetime-local" name="ends_at" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="f-max_quantity" class="form-label">الحد الأقصى للكمية (اختياري)</label>
                            <input id="f-max_quantity" type="number" name="max_quantity" class="form-control" min="0" placeholder="اترك فارغًا لعدم تحديد حد">
                            <div class="form-text">اترك فارغًا إذا كان العرض غير محدود الكمية.</div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">إضافة العرض</button>
                            <a href="{{ route('admin.daily-deals.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection