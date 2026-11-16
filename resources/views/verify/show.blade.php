@extends('layouts.app')

@section('title', 'نتيجة التحقق - تريندشام')

@section('content')
<div class="container" style="max-inline-size: 720px">
    @if (! $doc)
        <div class="alert alert-danger d-flex gap-3 align-items-start" role="alert">
            <i class="bi bi-x-octagon-fill fs-2"></i>
            <div>
                <h1 class="h4 mb-1">لا يوجد مستند بهذا الرمز</h1>
                <p class="mb-0">الرمز <span dir="ltr" class="fw-semibold">{{ \Illuminate\Support\Str::limit($code, 40) }}</span> غير مسجّل لدى تريندشام. تأكد من كتابته بشكل صحيح؛ وإن كان مطبوعاً هكذا على المستند فقد يكون مزوراً.</p>
            </div>
        </div>
    @else
        @if ($status === 'valid')
            <div class="alert alert-success d-flex gap-3 align-items-start" role="status">
                <i class="bi bi-patch-check-fill fs-2"></i>
                <div>
                    <h1 class="h4 mb-1">مستند صحيح صادر من تريندشام</h1>
                    <p class="mb-0">قارن البيانات أدناه بالمستند الذي بين يديك؛ أي اختلاف يعني أنه عُدِّل.</p>
                </div>
            </div>
        @elseif ($status === 'order_cancelled')
            <div class="alert alert-warning d-flex gap-3 align-items-start" role="status">
                <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                <div>
                    <h1 class="h4 mb-1">مستند صحيح، لكن أُلغي الطلب بعد إصداره</h1>
                    <p class="mb-0">هذه الفاتورة صدرت فعلاً من تريندشام، لكن الطلب ملغى الآن فلا يُعتدّ بها.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning d-flex gap-3 align-items-start" role="status">
                <i class="bi bi-clock-history fs-2"></i>
                <div>
                    <h1 class="h4 mb-1">مستند صحيح، لكن صدرت نسخة أحدث منه</h1>
                    <p class="mb-0">تغيّرت بياناته بعد إصدار هذه النسخة. اطلب النسخة الأحدث للاعتماد عليها.</p>
                </div>
            </div>
        @endif

        <div class="card mb-3"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5 col-sm-4 text-muted fw-normal">نوع المستند</dt><dd class="col-7 col-sm-8 fw-semibold">{{ $doc->typeLabel() }}</dd>
                <dt class="col-5 col-sm-4 text-muted fw-normal">الرقم</dt><dd class="col-7 col-sm-8 fw-semibold"><span dir="ltr">{{ $doc->summary['number'] ?? '—' }}</span></dd>
                @if (! empty($doc->summary['date']))
                    <dt class="col-5 col-sm-4 text-muted fw-normal">تاريخ المستند</dt><dd class="col-7 col-sm-8">{{ $doc->summary['date'] }}</dd>
                @endif
                @if (! empty($doc->summary['period']))
                    <dt class="col-5 col-sm-4 text-muted fw-normal">الفترة</dt><dd class="col-7 col-sm-8">{{ $doc->summary['period'] }}</dd>
                @endif
                <dt class="col-5 col-sm-4 text-muted fw-normal">المبلغ</dt><dd class="col-7 col-sm-8 fw-bold fs-5">{{ $doc->summary['amount_text'] ?? '—' }}</dd>
                <dt class="col-5 col-sm-4 text-muted fw-normal">صدر في</dt><dd class="col-7 col-sm-8">{{ $doc->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                <dt class="col-5 col-sm-4 text-muted fw-normal">رمز التحقق</dt><dd class="col-7 col-sm-8"><span dir="ltr" class="fw-semibold">{{ $doc->displayCode() }}</span></dd>
            </dl>
        </div></div>

        @if ($showDetails && $doc->details)
            <div class="card mb-3 border-success-subtle"><div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-person-lock"></i> التفاصيل الكاملة <span class="text-muted small fw-normal">(تظهر لك لأنك صاحب المستند أو مدير)</span></h2>
                @if ($doc->type === 'invoice')
                    <p class="mb-2">{{ $doc->details['customer'] ?? '' }} — <span dir="ltr">{{ $doc->details['phone'] ?? '' }}</span><br><span class="text-muted">{{ $doc->details['address'] ?? '' }}</span></p>
                    @if (! empty($doc->details['stores']))<p class="small mb-2">المتجر: {{ implode(__('،').' ', $doc->details['stores']) }}</p>@endif
                    <ul class="list-group list-group-flush mb-2">
                        @foreach ($doc->details['items'] ?? [] as $item)
                            <li class="list-group-item d-flex justify-content-between px-0"><span>{{ $item['name'] }} × {{ $item['qty'] }}</span><span>{{ number_format($item['subtotal'] / 100) }} ل.س</span></li>
                        @endforeach
                    </ul>
                    <div class="small text-muted">الشحن {{ number_format(($doc->details['shipping'] ?? 0) / 100) }} ل.س @if (($doc->details['discount'] ?? 0) > 0) · الخصم {{ number_format($doc->details['discount'] / 100) }} ل.س @endif</div>
                @elseif ($doc->type === 'seller_statement')
                    <p class="mb-1">المتجر: {{ $doc->details['store'] ?? '' }}</p>
                    <p class="small mb-0">{{ __('الافتتاحي') }} {{ number_format($doc->details['opening'] ?? 0, 2) }} · {{ __('الدائن') }} {{ number_format($doc->details['credit'] ?? 0, 2) }} · {{ __('المدين') }} {{ number_format($doc->details['debit'] ?? 0, 2) }} · {{ __('الختامي') }} {{ number_format($doc->details['closing'] ?? 0, 2) }} {{ __('ل.س') }} · {{ __(':n قيد', ['n' => $doc->details['entries'] ?? 0]) }}</p>
                @else
                    <p class="small mb-0">{{ __(':n طلب', ['n' => $doc->details['orders'] ?? 0]) }} · العمولة {{ number_format(($doc->details['commission'] ?? 0) / 100) }} ل.س · أرباح البائعين {{ number_format(($doc->details['vendor_earnings'] ?? 0) / 100) }} ل.س</p>
                @endif
                @if ($sourceUrl)
                    <a href="{{ $sourceUrl }}" class="btn btn-sm btn-outline-success mt-3">فتح الطلب</a>
                @endif
            </div></div>
        @elseif (! $showDetails)
            <p class="small text-muted"><i class="bi bi-lock"></i> لحماية الخصوصية لا نعرض الاسم أو العنوان. صاحب المستند يرى التفاصيل كاملة بعد تسجيل الدخول.</p>
        @endif
    @endif

    <div class="text-center mt-4">
        <a href="{{ route('verify.index') }}" class="btn btn-outline-secondary btn-sm">تحقق من مستند آخر أو افحص ملفاً</a>
    </div>
</div>
@endsection
