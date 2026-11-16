@extends('layouts.app')

@section('title', 'تعديل عرض يومي - تريندشام')

@section('content')
<div class="container-fluid px-4">
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - تعديل العرض اليومي</span>
            <a href="{{ route('admin.daily-deals.index') }}" class="btn btn-outline-secondary btn-sm">الرجوع للقائمة</a>
        </div>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">تعديل عرض يومي</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.daily-deals.update', $deal) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="f-product_id" class="form-label">المنتج</label>
                            <select id="f-product_id" name="product_id" class="form-select" required>
                                <option value="{{ $deal->product_id }}" selected>{{ $deal->product->name }}</option>
                                @foreach ($products as $product)
                                    @if ($product->id != $deal->product_id)
                                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->vendor->store_name ?? 'غير محدد' }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="f-discount_percentage" class="form-label">نسبة الخصم (%)</label>
                                <input id="f-discount_percentage" type="number" name="discount_percentage" class="form-control" min="1" max="90" step="0.01" value="{{ old('discount_percentage', $deal->discount_percentage) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="f-special_price" class="form-label">السعر الخاص (اختياري)</label>
                                <input id="f-special_price" type="number" name="special_price" class="form-control" min="0" step="0.01" value="{{ old('special_price', $deal->special_price) }}" placeholder="اترك فارغًا لحساب تلقائي">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="f-starts_at" class="form-label">تاريخ البدء</label>
                                <input id="f-starts_at" type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $deal->starts_at->format('Y-m-d\H:i')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="f-ends_at" class="form-label">تاريخ النهاية</label>
                                <input id="f-ends_at" type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $deal->ends_at->format('Y-m-d\H:i')) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="f-max_quantity" class="form-label">الحد الأقصى للكمية (اختياري)</label>
                            <input id="f-max_quantity" type="number" name="max_quantity" class="form-control" min="0" value="{{ old('max_quantity', $deal->max_quantity) }}" placeholder="اترك فارغًا لعدم تحديد حد">
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $deal->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">نشط</label>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">تحديث العرض</button>
                            <a href="{{ route('admin.daily-deals.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection