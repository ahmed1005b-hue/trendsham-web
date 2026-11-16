@extends('layouts.customer-account')

@section('title', __('طلب إرجاع'))

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head">
            <h1>{{ __('طلب إرجاع للطلب :number', ['number' => $order->order_number]) }}</h1>
            <a href="{{ route('customer.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">{{ __('رجوع للطلب') }}</a>
        </div>
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('customer.returns.store', $order) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p class="small text-muted">{{ __('حدّد الكمية المراد إرجاعها من كل منتج. طلب الإرجاع الواحد يخص متجراً واحداً.') }}</p>
            @foreach ($order->items->filter(fn ($item) => $returnable->has($item->id))->groupBy('vendor_id') as $storeItems)
                <h2 class="h6 mt-3 mb-1">{{ __('المتجر') }}: <span translate="no">{{ $storeItems->first()->vendor?->store_name }}</span></h2>
                @foreach ($storeItems as $item)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span><span translate="no">{{ $item->product_name }}</span> <span class="small text-muted">({{ __('المتاح للإرجاع: :n', ['n' => $returnable[$item->id]]) }})</span></span>
                        <input type="number" name="lines[{{ $item->id }}]" min="0" max="{{ $returnable[$item->id] }}"
                            value="{{ old('lines.'.$item->id, 0) }}" class="form-control form-control-sm" style="width:90px">
                    </div>
                @endforeach
            @endforeach

            <div class="mt-3">
                <label class="form-label" for="reason">{{ __('سبب الإرجاع') }}</label>
                <select name="reason" id="reason" class="form-select" required>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ __(\App\Returns\Models\ReturnRequest::REASON_LABELS[$reason]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-3">
                <label class="form-label" for="description">{{ __('تفاصيل إضافية (اختياري)') }}</label>
                <textarea name="description" id="description" rows="3" maxlength="2000" class="form-control">{{ old('description') }}</textarea>
            </div>
            <div class="mt-3">
                <label class="form-label" for="photos">{{ __('صور (حتى 5)') }}</label>
                <input type="file" name="photos[]" id="photos" accept="image/*" multiple class="form-control">
            </div>
            <button class="btn btn-primary mt-3">{{ __('إرسال طلب الإرجاع') }}</button>
        </form>
    </div>
@endsection
