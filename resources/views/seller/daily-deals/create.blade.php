@extends('layouts.app')

@section('title', 'عرض يومي جديد - تريندشام')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - عرض يومي جديد</span>
            <a href="{{ route('seller.daily-deals.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
        </div>
    </nav>

    <div class="container mb-5" style="max-width: 560px;">
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="alert alert-light border small">
            اختر منتجاً من منتجاتك المعتمدة، وحدد نسبة الخصم (1% - 90%) وفترة العرض.
            سيظهر العرض تلقائياً للعملاء في وقت بدايته.
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('seller.daily-deals.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="f-product_id" class="form-label">المنتج</label>
                        <select id="f-product_id" name="product_id" class="form-select" required>
                            <option value="">-- اختر منتجاً --</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ number_format($product->price, 2) }} ل.س)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="f-discount_percentage" class="form-label">نسبة الخصم %</label>
                            <input id="f-discount_percentage" type="number" name="discount_percentage" class="form-control" min="1" max="90"
                                step="0.01" value="{{ old('discount_percentage') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="f-special_price" class="form-label">سعر خاص (اختياري - يتجاوز النسبة)</label>
                            <input id="f-special_price" type="number" name="special_price" class="form-control" min="0" step="0.01"
                                value="{{ old('special_price') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="f-starts_at" class="form-label">يبدأ في</label>
                            <input id="f-starts_at" type="datetime-local" name="starts_at" class="form-control"
                                value="{{ old('starts_at') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="f-ends_at" class="form-label">ينتهي في</label>
                            <input id="f-ends_at" type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}"
                                required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="f-max_quantity" class="form-label">أقصى كمية للبيع بالعرض (0 = غير محدودة)</label>
                        <input id="f-max_quantity" type="number" name="max_quantity" class="form-control" min="0"
                            value="{{ old('max_quantity', 0) }}">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">إضافة العرض</button>
                </form>
            </div>
        </div>
    </div>
@endsection