@extends('layouts.app')

@section('title', 'تعديل منتج - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - تعديل منتج</span>
        <a href="{{ route('seller.products.index') }}" class="btn btn-outline-secondary btn-sm">رجوع للمنتجات</a>
    </div>
</nav>

<div class="container">
    @if ($product->images->count())
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">صور المنتج الحالية</h2>
                <p class="text-muted small">اربط الصورة بلون (اختياري) لتظهر عند اختيار هذا اللون، ثم احفظ المنتج.</p>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach ($product->images as $image)
                        <div class="position-relative border rounded p-2" style="width: 132px;">
                            <img src="{{ $image->thumb_url }}" alt="صورة المنتج {{ $loop->iteration }}" width="114" height="90" class="rounded d-block" style="object-fit: cover;">
                            @if ($image->is_primary)
                                <span class="badge bg-success position-absolute top-0 start-0 m-2" style="font-size:.6rem;">رئيسية</span>
                            @endif
                            @php $imageColor = (string) $image->color; @endphp
                            <select name="existing_image_color[{{ $image->id }}]" class="form-select form-select-sm mt-2" data-ts-color-select data-selected="{{ $imageColor }}" form="seller-product-form" title="{{ __('لون الصورة') }}" aria-label="{{ __('لون الصورة') }}">
                                <option value="">{{ __('بدون لون') }}</option>
                                @if ($imageColor !== '')
                                    <option value="{{ $imageColor }}" selected>{{ $image->color_name ?: $imageColor }}</option>
                                @endif
                            </select>
                            <input type="hidden" name="existing_image_color_name[{{ $image->id }}]" value="{{ $image->color_name ?: $imageColor }}" data-ts-color-name-for="existing_image_color[{{ $image->id }}]" form="seller-product-form">
                            <div class="d-flex gap-1 mt-1">
                                @unless ($image->is_primary)
                                    <button type="submit" form="set-primary-{{ $image->id }}" class="btn btn-outline-secondary btn-sm flex-grow-1 p-0" style="font-size:.65rem;" title="تعيين كصورة رئيسية"><i class="bi bi-star"></i></button>
                                @endunless
                                <button type="submit" form="delete-image-{{ $image->id }}" class="btn btn-outline-danger btn-sm flex-grow-1 p-0" style="font-size:.65rem;" title="حذف الصورة"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
                @foreach ($product->images as $image)
                    <form id="set-primary-{{ $image->id }}" action="{{ route('seller.products.images.setPrimary', [$product, $image]) }}" method="POST" class="d-none">@csrf</form>
                    <form id="delete-image-{{ $image->id }}" action="{{ route('seller.products.images.destroy', [$product, $image]) }}" method="POST" class="d-none" data-confirm="حذف هذه الصورة نهائياً؟">@csrf @method('DELETE')</form>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form id="seller-product-form" action="{{ route('seller.products.update', $product) }}" method="POST" enctype="multipart/form-data">
                @method('PUT')
                @include('seller.products._form')
            </form>
        </div>
    </div>
</div>

@endsection
