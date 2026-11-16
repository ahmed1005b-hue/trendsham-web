{{-- سؤال واحد بعد أول متابعة لمتجر: هل يريد العميل إشعارات العروض؟ --}}
@if (session('ask_offers_opt_in'))
    <div class="container mt-3">
        <div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center gap-2 mb-0" role="dialog" aria-labelledby="offersOptInTitle">
            <div class="flex-grow-1">
                <strong id="offersOptInTitle"><i class="bi bi-bell me-1"></i> هل تريد إشعارات عروض ومنتجات المتاجر التي تتابعها؟</strong>
                <div class="small">حتى 3 إشعارات يومياً، ولا شيء ليلاً. يمكنك تغيير ذلك لاحقاً من إعدادات الإشعارات في التطبيق.</div>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('customer.notification-settings.offers') }}">
                    @csrf
                    <input type="hidden" name="offers" value="1">
                    <button type="submit" class="btn btn-success btn-sm">نعم، أرسلها لي</button>
                </form>
                <form method="POST" action="{{ route('customer.notification-settings.offers') }}">
                    @csrf
                    <input type="hidden" name="offers" value="0">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">لا، شكراً</button>
                </form>
            </div>
        </div>
    </div>
@endif
