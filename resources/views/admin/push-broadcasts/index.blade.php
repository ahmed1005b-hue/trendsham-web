@extends('layouts.app')

@section('title', 'إشعار جماعي - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إشعار جماعي</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3 mb-4" data-total-customers="{{ $audience['total'] }}" data-reachable="{{ $audience['reachable'] }}">
        <div class="col-6 col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">كل العملاء (يصلهم في صفحة الإشعارات)</div>
                <div class="fs-3 fw-bold">{{ number_format($audience['total']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">يصلهم على الهاتف (فعّلوا العروض)</div>
                <div class="fs-3 fw-bold text-success">{{ number_format($audience['reachable']) }}</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">الإرسال التالي المتاح</div>
                @if ($nextAllowedAt)
                    <div class="fw-semibold text-warning-emphasis">{{ $nextAllowedAt->timezone(config('app.timezone'))->translatedFormat('l j F، H:i') }}</div>
                    <div class="small text-muted">إشعار جماعي واحد كل 7 أيام.</div>
                @else
                    <div class="fw-semibold text-success">متاح الآن</div>
                    <div class="small text-muted">إشعار جماعي واحد كل 7 أيام.</div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <h2 class="h5 mb-3">إشعار جديد لكل العملاء</h2>
                <form method="POST" action="{{ route('admin.push-broadcasts.store') }}" id="broadcastForm">
                    @csrf
                    <div class="mb-3">
                        <label for="bTitle" class="form-label">العنوان <span class="text-muted small">(حتى 60 حرفاً)</span></label>
                        <input id="bTitle" name="title" maxlength="60" required class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" @disabled($nextAllowedAt)>
                    </div>
                    <div class="mb-3">
                        <label for="bBody" class="form-label">النص <span class="text-muted small">(حتى 180 حرفاً)</span></label>
                        <textarea id="bBody" name="body" maxlength="180" rows="3" required class="form-control @error('body') is-invalid @enderror" @disabled($nextAllowedAt)>{{ old('body') }}</textarea>
                        <div class="form-text"><span id="bBodyCount">0</span>/180</div>
                    </div>
                    <div class="mb-3">
                        <label for="bLink" class="form-label">رابط اختياري <span class="text-muted small">(صفحة في الموقع تبدأ بـ / مثل /store/4)</span></label>
                        <input id="bLink" name="link" maxlength="255" dir="ltr" class="form-control @error('link') is-invalid @enderror" value="{{ old('link') }}" placeholder="/store/4" @disabled($nextAllowedAt)>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="bConfirm" @disabled($nextAllowedAt)>
                        <label class="form-check-label" for="bConfirm">
                            {{ __('أؤكد الإرسال إلى :n عميل. ليلاً (22:00–9:00) يتأجل إلى 9 صباحاً.', ['n' => number_format($audience['total'])]) }}
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success" @disabled($nextAllowedAt)><i class="bi bi-send"></i> إرسال</button>
                </form>
            </div></div>
        </div>

        <div class="col-lg-5">
            <div class="text-muted small mb-2">معاينة كما تظهر على الهاتف</div>
            <div id="broadcastPreview" class="ts-push-preview" aria-live="polite">
                <div class="d-flex align-items-center gap-2 mb-1 small text-muted">
                    <span class="ts-push-icon" translate="no">{{ mb_substr(__('تريندشام'), 0, 1) }}</span> تريندشام · الآن
                </div>
                <div class="fw-semibold" id="pTitle">{{ old('title') ?: 'عنوان الإشعار' }}</div>
                <div class="small" id="pBody">{{ old('body') ?: 'نص الإشعار يظهر هنا.' }}</div>
            </div>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">الإرسالات السابقة</h2>
    @if ($broadcasts->isEmpty())
        <p class="text-muted">لم يُرسل أي إشعار جماعي بعد.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>العنوان</th><th>الموعد</th><th>الحالة</th><th>في الإشعارات</th><th>على الهاتف</th><th>بواسطة</th></tr></thead>
                <tbody>
                    @foreach ($broadcasts as $b)
                        <tr>
                            <td style="min-inline-size: 220px"><div class="fw-semibold">{{ $b->title }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($b->body, 70) }}</div></td>
                            <td class="small text-nowrap">{{ $b->scheduled_for->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($b->isSent())
                                    <span class="badge text-bg-success">أُرسل</span>
                                @else
                                    <span class="badge text-bg-warning">مجدول</span>
                                @endif
                            </td>
                            <td>{{ number_format($b->recipients_count) }}</td>
                            <td>{{ number_format($b->pushed_count) }}</td>
                            <td class="small">{{ $b->creator?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
    .ts-push-preview { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: 18px; padding: 12px 14px; box-shadow: 0 6px 18px rgba(0,0,0,.08); max-width: 380px; overflow-wrap: anywhere; }
    .ts-push-icon { display: inline-flex; align-items: center; justify-content: center; inline-size: 20px; block-size: 20px; border-radius: 6px; background: var(--ts-emerald, #10b981); color: #fff; font-size: .7rem; font-weight: 700; }
</style>
<script>
    (function () {
        var t = document.getElementById('bTitle'), b = document.getElementById('bBody');
        var pt = document.getElementById('pTitle'), pb = document.getElementById('pBody'), c = document.getElementById('bBodyCount');
        function sync() {
            pt.textContent = t.value.trim() || @json(__('عنوان الإشعار'));
            pb.textContent = b.value.trim() || @json(__('نص الإشعار يظهر هنا.'));
            c.textContent = b.value.length;
        }
        t.addEventListener('input', sync); b.addEventListener('input', sync); sync();
    })();
</script>
@endsection
