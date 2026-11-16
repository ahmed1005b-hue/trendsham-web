{{-- بنود طلب الإرجاع ومبالغه (items.orderItem محمّلة مسبقاً) --}}
@foreach ($returnRequest->items as $line)
    <div class="d-flex justify-content-between small border-bottom py-1">
        <span><span translate="no">{{ $line->orderItem->product_name }}</span> × {{ $line->quantity }}</span>
        <span>{{ number_format($line->refund_amount / 100) }} ل.س</span>
    </div>
@endforeach
@if ($returnRequest->shipping_refund > 0)
    <div class="d-flex justify-content-between small py-1">
        <span>{{ __('استرداد الشحن') }}</span>
        <span>{{ number_format($returnRequest->shipping_refund / 100) }} ل.س</span>
    </div>
@endif
<div class="d-flex justify-content-between fw-bold mt-2">
    <span>{{ __('إجمالي الاسترداد') }}</span>
    <span>{{ number_format($returnRequest->refund_amount / 100) }} ل.س</span>
</div>
