@extends('layouts.app')

@section('title', 'الكوبونات - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الكوبونات</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">إنشاء كوبون جديد</h2>
                    <form action="{{ route('admin.coupons.store') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label for="f-code" class="form-label small">الكود</label>
                            <input id="f-code" type="text" name="code" class="form-control" placeholder="SELLER25" value="{{ old('code') }}" required>
                        </div>
                        <div class="mb-2">
                            <label for="f-seller_profile_id" class="form-label small">النطاق</label>
                            <select id="f-seller_profile_id" name="seller_profile_id" class="form-select">
                                <option value="">كوبون منصة (كل المنتجات)</option>
                                @foreach ($sellers as $seller)
                                    <option value="{{ $seller->id }}" @selected(old('seller_profile_id') == $seller->id)>{{ $seller->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="f-product_id" class="form-label small">منتج محدد (اختياري، ضمن البائع)</label>
                            <select id="f-product_id" name="product_id" class="form-select">
                                <option value="">كل منتجات البائع / المنصة</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>#{{ $product->id }} - {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="f-type" class="form-label small">النوع</label>
                            <select id="f-type" name="type" class="form-select">
                                <option value="percentage" @selected(old('type')==='percentage')>نسبة مئوية %</option>
                                <option value="fixed" @selected(old('type')==='fixed')>مبلغ ثابت (ل.س)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="f-value" class="form-label small">القيمة</label>
                            <input id="f-value" type="number" name="value" class="form-control" value="{{ old('value') }}" required>
                        </div>
                        <div class="mb-2">
                            <label for="f-min_order_amount" class="form-label small">أقل قيمة مؤهلة (ل.س، تُحسب على منتجات البائع المؤهلة)</label>
                            <input id="f-min_order_amount" type="number" name="min_order_amount" class="form-control" value="{{ old('min_order_amount') }}">
                        </div>
                        <div class="mb-2">
                            <label for="f-usage_limit" class="form-label small">حد الاستخدام (اختياري)</label>
                            <input id="f-usage_limit" type="number" name="usage_limit" class="form-control" value="{{ old('usage_limit') }}">
                        </div>
                        <div class="mb-3">
                            <label for="f-expires_at" class="form-label small">تاريخ الانتهاء (اختياري)</label>
                            <input id="f-expires_at" type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">إنشاء</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>الكود</th><th>النطاق</th><th>القيمة</th><th>الاستخدام</th><th>الحالة</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($coupons as $coupon)
                                <tr>
                                    <td class="fw-bold">{{ $coupon->code }}</td>
                                    <td class="small">
                                        <div>{{ $coupon->scopeLabel() }}</div>
                                        @if($coupon->sellerProfile)<div class="text-muted">{{ $coupon->sellerProfile->store_name }}</div>@endif
                                        @if($coupon->product)<div class="text-muted">{{ $coupon->product->name }}</div>@endif
                                    </td>
                                    <td>{{ $coupon->type === 'percentage' ? $coupon->value.'%' : number_format($coupon->value / 100).' ل.س' }}</td>
                                    <td>{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                                    <td>
                                        <form action="{{ route('admin.coupons.toggle', $coupon) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-sm {{ $coupon->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                                {{ $coupon->is_active ? 'مفعّل' : 'معطّل' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" data-confirm="حذف الكوبون؟">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">لا يوجد كوبونات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection