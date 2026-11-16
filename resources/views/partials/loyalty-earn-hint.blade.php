{{-- "ستكسب X نقاط" أو "أضف Y ل.س لتكسب أول نقطة". المدخل: $amount بالوحدة الصغرى (×100) --}}
@php($loyaltyHint = app(\App\Loyalty\LoyaltyCalculator::class)->hintText((int) $amount))
@if ($loyaltyHint)
    <p class="small mb-2 ts-loyalty-hint" style="color:#9A7425">
        <i class="bi bi-gem" aria-hidden="true"></i> {{ $loyaltyHint }}
    </p>
@endif
