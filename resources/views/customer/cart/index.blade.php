@extends('layouts.app')

@section('title', 'سلة التسوق - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - سلة التسوق</span>
        <a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحتي</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($items->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3">سلتك فارغة.</p>
                <a href="{{ route('home') }}" class="btn btn-primary btn-sm">تصفّح المنتجات</a>
            </div>
        </div>
    @else
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>المنتج</th><th>السعر</th><th>الكمية</th><th>الإجمالي</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php($hasDeal = ($item->product->effective_price ?? $item->product->price) < $item->product->price)
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    @if ($item->product->primaryImage)
                                        <img src="{{ $item->product->primaryImage->thumb_url }}" alt="{{ $item->product->name }}" width="44" height="44" class="rounded" style="object-fit:cover;">
                                    @endif
                                    <div>
                                        <div translate="no">{{ $item->product->name }}</div>
                                        <div class="text-muted small" translate="no">{{ $item->product->vendor->store_name }}</div>
                                        @if ($item->variant)
                                            <div class="small">
                                                @if ($item->variant->color_name || $item->variant->color)
                                                    <span class="badge bg-light text-dark border">اللون: {{ $item->variant->color_name ?? $item->variant->color }}</span>
                                                @endif
                                                @if ($item->variant->size)
                                                    <span class="badge bg-light text-dark border">المقاس: {{ $item->variant->size }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @if (! $item->variant && $item->product->variants()->where('is_active', true)->exists())
                                            <div class="small text-danger mt-1">لم يتم اختيار اللون/المقاس — احذف المنتج وأعد إضافته بعد الاختيار.</div>
                                        @endif
                                        @if ($item->product->short_description)
                                            <div class="text-muted small" style="max-width:320px;">{{ \Illuminate\Support\Str::limit($item->product->short_description, 90) }}</div>
                                        @elseif ($item->product->description)
                                            <div class="text-muted small" style="max-width:320px;">{{ \Illuminate\Support\Str::limit(strip_tags($item->product->description), 90) }}</div>
                                        @endif
                                        @if ($hasDeal)
                                            <div class="small text-success">يشمل سعر عرض اليوم</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if (($item->original_unit_price ?? 0) > ($item->unit_price ?? 0) || $hasDeal)
                                        <div class="fw-bold text-success">{{ number_format(($item->unit_price ?? $item->product->effective_price) / 100) }} ل.س</div>
                                        <del class="text-muted small">{{ number_format(($item->original_unit_price ?? $item->product->price) / 100) }} ل.س</del>
                                    @else
                                        {{ number_format(($item->unit_price ?? $item->product->price) / 100) }} ل.س
                                    @endif
                                    @if ($item->variant)
                                        <div class="text-muted small">{{ $item->variant->label ?? '' }}</div>
                                    @endif
                                </td>
                                <td style="width:120px;">
                                    <form action="{{ route('customer.cart.update', $item) }}" method="POST" class="d-flex gap-1">
                                        @csrf @method('PUT')
                                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $item->variant ? $item->variant->stock_quantity : $item->product->stock_quantity }}" class="form-control form-control-sm" style="width:70px;">
                                        <button class="btn btn-sm btn-outline-secondary">تحديث</button>
                                    </form>
                                </td>
                                <td class="fw-bold">{{ number_format($item->subtotal / 100) }} ل.س</td>
                                <td class="text-end">
                                    <form action="{{ route('customer.cart.destroy', $item) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="h5 mb-0">الإجمالي: {{ number_format($total / 100) }} ل.س</div>
                    <div class="small text-muted">الإجمالي محسوب بأسعار العروض النشطة الحالية.</div>
                    @include('partials.loyalty-earn-hint', ['amount' => $total])
                </div>
                <a href="{{ route('customer.checkout.show') }}" class="btn btn-primary">إتمام الشراء</a>
            </div>
        </div>
    @endif
</div>
@endsection
