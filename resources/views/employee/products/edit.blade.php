@extends('layouts.app')

@section('title', 'تعديل المنتج')

@section('content')
<div class="container py-4" style="max-width:700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4">تعديل المنتج</h1>
        <a href="{{ route('employee.products.index') }}" class="btn btn-outline-secondary btn-sm">العودة للمنتجات</a>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form class="card card-body" method="POST" action="{{ route('employee.products.update', $product) }}">
        @csrf @method('PUT')
        <label for="f-name" class="form-label">اسم المنتج</label>
        <input id="f-name" class="form-control mb-3" name="name" required value="{{ old('name', $product->name) }}">
        <label for="f-description" class="form-label">الوصف</label>
        <textarea id="f-description" class="form-control mb-3" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
        <label for="f-category_id" class="form-label">القسم</label>
        <select id="f-category_id" class="form-select mb-3" name="category_id" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <label for="f-price" class="form-label">السعر (ل.س)</label>
        <input id="f-price" class="form-control mb-3" type="number" name="price" min="0.01" step="0.01" required value="{{ old('price', $product->price / 100) }}">
        <label for="f-compare_at_price" class="form-label">السعر قبل الخصم (اختياري)</label>
        <input id="f-compare_at_price" class="form-control mb-3" type="number" name="compare_at_price" min="0.01" step="0.01" value="{{ old('compare_at_price', $product->compare_at_price ? $product->compare_at_price / 100 : '') }}">
        <label for="f-stock_quantity" class="form-label">الكمية بالمخزون</label>
        <input id="f-stock_quantity" class="form-control mb-3" type="number" name="stock_quantity" min="0" required value="{{ old('stock_quantity', $product->stock_quantity) }}">
        <button class="btn btn-primary">حفظ التعديلات</button>
    </form>
</div>
@endsection
