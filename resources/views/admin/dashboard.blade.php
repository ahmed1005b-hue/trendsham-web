@extends('layouts.app')

@section('title', 'لوحة المدير - تريندشام')

@section('content')
@push('styles')
<style>
    .ad-group { font-family: 'Reem Kufi', sans-serif; font-weight: 700; font-size: 1rem; color: var(--ts-ink); margin-block: 0 .6rem; display: flex; align-items: center; gap: .45rem; }
    .ad-group i { color: var(--ts-gold); }
    .ad-tile { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .45rem; text-align: center;
        min-block-size: 104px; padding: .9rem .5rem; border-radius: 16px; border: 1px solid var(--ts-border); background: var(--ts-surface);
        color: var(--ts-ink); text-decoration: none; font-weight: 600; font-size: .85rem; transition: border-color .15s, transform .15s; }
    .ad-tile:hover, .ad-tile:focus-visible { border-color: var(--ts-gold); color: var(--ts-ink); transform: translateY(-2px); }
    .ad-tile i { font-size: 1.6rem; color: var(--ts-emerald); }
    .ad-tile .badge { position: absolute; inset-block-start: .45rem; inset-inline-end: .45rem; }
    .ad-tile.ad-primary { background: var(--ts-emerald); border-color: var(--ts-emerald); color: #fff; }
    .ad-tile.ad-primary i { color: #fff; }
    @media (prefers-reduced-motion: reduce) { .ad-tile { transition: none; } .ad-tile:hover { transform: none; } }
</style>
@endpush

<div class="container mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 ts-logo">تريند<span>شام</span> — {{ __('لوحة المدير') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bell" aria-hidden="true"></i> {{ __('الإشعارات') }}</a>
            <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-circle" aria-hidden="true"></i> {{ __('ملفي الشخصي') }}</a>
            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> {{ __('تسجيل الخروج') }}</button>
            </form>
        </div>
    </div>

    @php
        $adminGroups = [
            ['title' => __('نظرة عامة'), 'icon' => 'bi-speedometer2', 'items' => [
                ['route' => 'admin.analytics', 'icon' => 'bi-graph-up-arrow', 'label' => __('التحليلات'), 'primary' => true],
                ['route' => 'admin.reports.index', 'icon' => 'bi-file-earmark-bar-graph', 'label' => __('كشف الحساب')],
            ]],
            ['title' => __('المبيعات والطلبات'), 'icon' => 'bi-bag', 'items' => [
                ['route' => 'admin.orders.index', 'icon' => 'bi-receipt', 'label' => __('الطلبات')],
                ['route' => 'admin.returns.index', 'icon' => 'bi-arrow-return-left', 'label' => __('المرتجعات'), 'badge' => $returnsNeedingActionCount ?? 0, 'badge_class' => 'bg-warning text-dark'],
                ['route' => 'admin.shippingCompanies.index', 'icon' => 'bi-truck', 'label' => __('شركات الشحن')],
            ]],
            ['title' => __('المنتجات والمحتوى'), 'icon' => 'bi-box-seam', 'items' => [
                ['route' => 'admin.products.index', 'icon' => 'bi-clipboard-check', 'label' => __('مراجعة المنتجات'), 'badge' => $pendingProductsCount ?? 0, 'badge_class' => 'bg-danger'],
                ['route' => 'admin.categories.index', 'icon' => 'bi-diagram-3', 'label' => __('الأقسام')],
                ['route' => 'admin.daily-deals.index', 'icon' => 'bi-lightning-charge', 'label' => __('عروض اليوم')],
                ['route' => 'admin.stories.index', 'icon' => 'bi-camera-reels', 'label' => __('الستوري')],
            ]],
            ['title' => __('البائعون والفريق'), 'icon' => 'bi-shop', 'items' => [
                ['route' => 'admin.sellers.index', 'icon' => 'bi-shop-window', 'label' => __('البائعون'), 'badge' => $pendingSellers ?? 0, 'badge_class' => 'bg-danger'],
                ['route' => 'admin.employees.index', 'icon' => 'bi-person-badge', 'label' => __('طلبات الموظفين')],
            ]],
            ['title' => __('المالية'), 'icon' => 'bi-cash-stack', 'items' => [
                ['route' => 'admin.accounting.dashboard', 'icon' => 'bi-journal-text', 'label' => __('المحاسبة')],
                ['route' => 'admin.withdrawals.index', 'icon' => 'bi-wallet2', 'label' => __('طلبات السحب'), 'badge' => $pendingWithdrawalsCount ?? 0, 'badge_class' => 'bg-warning text-dark'],
                ['route' => 'admin.loyalty.index', 'icon' => 'bi-gem', 'label' => __('نقاط الولاء')],
            ]],
            ['title' => __('التسويق'), 'icon' => 'bi-megaphone', 'items' => [
                ['route' => 'admin.coupon-campaigns.index', 'icon' => 'bi-ticket-perforated', 'label' => __('الكوبونات')],
                ['route' => 'admin.seller-coupons.index', 'icon' => 'bi-tags', 'label' => __('كوبونات البائعين'), 'badge' => $pendingSellerCoupons ?? 0, 'badge_class' => 'bg-warning text-dark'],
                ['route' => 'admin.showcases.index', 'icon' => 'bi-easel', 'label' => __('العروض المؤجّرة'), 'badge' => $pendingShowcasesCount ?? 0, 'badge_class' => 'bg-warning text-dark'],
                ['route' => 'admin.showcase-slots.index', 'icon' => 'bi-layout-wtf', 'label' => __('مساحات العرض')],
                ['route' => 'admin.push-broadcasts.index', 'icon' => 'bi-broadcast', 'label' => __('إشعار جماعي')],
            ]],
            ['title' => __('الدعم والتواصل'), 'icon' => 'bi-headset', 'items' => [
                ['route' => 'admin.support.index', 'icon' => 'bi-chat-left-text', 'label' => __('رسائل البائعين'), 'badge' => $openSupportTickets ?? 0, 'badge_class' => 'bg-warning text-dark'],
                ['route' => 'admin.assistant-chats.index', 'icon' => 'bi-chat-dots', 'label' => __('محادثات العملاء')],
                ['route' => 'admin.assistant-faqs.index', 'icon' => 'bi-robot', 'label' => __('أجوبة المساعد')],
            ]],
            ['title' => __('النظام'), 'icon' => 'bi-gear', 'items' => [
                ['route' => 'admin.settings.edit', 'icon' => 'bi-sliders', 'label' => __('الإعدادات')],
            ]],
        ];
    @endphp

    <div class="row g-4">
        @foreach ($adminGroups as $group)
            <section class="col-12 col-lg-6" aria-labelledby="ad-group-{{ $loop->index }}">
                <h2 class="ad-group" id="ad-group-{{ $loop->index }}"><i class="bi {{ $group['icon'] }}" aria-hidden="true"></i> {{ $group['title'] }}</h2>
                <div class="row g-2">
                    @foreach ($group['items'] as $item)
                        <div class="col-6 col-sm-4 col-xl-3">
                            <a href="{{ route($item['route']) }}" class="ad-tile {{ ! empty($item['primary']) ? 'ad-primary' : '' }}">
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="badge rounded-pill {{ $item['badge_class'] }}">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>

<div class="container">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="h5 text-muted">عدد البائعين</h2>
                    <p class="display-6 mb-0">{{ $sellersCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="h5 text-muted">عدد العملاء</h2>
                    <p class="display-6 mb-0">{{ $customersCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="h5 text-muted">طلبات بانتظار المراجعة</h2>
                    <p class="display-6 mb-0">{{ $pendingSellers }}</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">تقارير مالية</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">إجمالي المبيعات</h3>
                    <p class="h4 mb-0">{{ number_format($totalSales / 100) }} ل.س</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">عمولة المنصة المحصّلة</h3>
                    <p class="h4 mb-0">{{ number_format($totalCommission / 100) }} ل.س</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">طلبات سحب معلّقة</h3>
                    <p class="h4 mb-0">{{ $pendingWithdrawalsCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">قيمة السحوبات المعلّقة</h3>
                    <p class="h4 mb-0">{{ number_format($pendingWithdrawalsAmount / 100) }} ل.س</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-{{ $openSupportTickets > 0 ? 'warning' : 'primary' }}">
                <div class="card-body">
                    <h3 class="h6 text-muted">رسائل دعم مفتوحة</h3>
                    <p class="h4 mb-0">{{ $openSupportTickets }}</p>
                </div>
            </div>
        </div>
    </div>


    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0">هويات بائعين قدامى بانتظار المراجعة</h2>
    <span class="badge bg-warning text-dark">{{ $identityReviewCount ?? 0 }}</span>
</div>
<form method="GET" action="{{ route('admin.dashboard') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="f-q" class="form-label small mb-1">بحث</label>
            <input id="f-q" type="text" name="q" value="{{ $identityReviewFilters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="متجر / مالك / هاتف / بريد / رقم هوية">
        </div>
        <div class="col-md-2">
            <label for="f-identity_type" class="form-label small mb-1">نوع الوثيقة</label>
            <select id="f-identity_type" name="identity_type" class="form-select form-select-sm">
                <option value="">الكل</option>
                @foreach (['national_id' => 'هوية وطنية', 'passport' => 'جواز سفر', 'residence' => 'إقامة', 'commercial_register' => 'سجل تجاري'] as $v => $l)
                    <option value="{{ $v }}" @selected(($identityReviewFilters['identity_type'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label for="f-has_back" class="form-label small mb-1">خلفية</label>
            <select id="f-has_back" name="has_back" class="form-select form-select-sm">
                <option value="">الكل</option>
                <option value="yes" @selected(($identityReviewFilters['has_back'] ?? '') === 'yes')>يوجد</option>
                <option value="no" @selected(($identityReviewFilters['has_back'] ?? '') === 'no')>لا يوجد</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="f-review_kind" class="form-label small mb-1">نوع الطلب</label>
            <select id="f-review_kind" name="review_kind" class="form-select form-select-sm">
                <option value="">الكل</option>
                <option value="legacy" @selected(($identityReviewFilters['review_kind'] ?? '') === 'legacy')>بائع قديم أعاد الإرسال</option>
                <option value="new" @selected(($identityReviewFilters['review_kind'] ?? '') === 'new')>جديد/اعتيادي</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="f-submitted_from" class="form-label small mb-1">من تاريخ</label>
            <input id="f-submitted_from" type="date" name="submitted_from" value="{{ $identityReviewFilters['submitted_from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label for="f-submitted_to" class="form-label small mb-1">إلى تاريخ</label>
            <input id="f-submitted_to" type="date" name="submitted_to" value="{{ $identityReviewFilters['submitted_to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-primary">تصفية</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
        </div>
    </div>
</form>
<div class="card mb-4 border-warning">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>المتجر</th><th>صاحب المتجر</th><th>بيانات الهوية المرسلة</th><th>الوثائق</th><th style="min-width:260px"></th></tr>
            </thead>
            <tbody>
                @forelse (($identityReviewProfiles ?? collect()) as $profile)
                    <tr>
                        <td class="fw-bold" translate="no">
                            {{ $profile->store_name }}
                            <div><span class="badge bg-info text-dark">بائع قديم — المتجر نشط</span></div>
                        </td>
                        <td>
                            <div>{{ $profile->user->name ?? '' }}</div>
                            <div class="small text-muted">{{ $profile->user->phone ?? '' }}</div>
                            <div class="small text-muted">{{ $profile->user->email ?? '' }}</div>
                        </td>
                        <td class="small">
                            <div><strong>وقت الإرسال:</strong> {{ $profile->identity_document_submitted_at?->format('Y-m-d H:i') ?? '—' }}</div>
                            <div><strong>نوع الوثيقة:</strong> {{ $profile->identity_type ?: '—' }}</div>
                            <div><strong>رقم الهوية/السجل:</strong> {{ $profile->identity_number ?: '—' }}</div>
                            <div><strong>السجل التجاري:</strong> {{ $profile->commercial_register ?: '—' }}</div>
                            <div><strong>وصف المتجر:</strong> {{ \Illuminate\Support\Str::limit((string) $profile->store_description, 120) ?: '—' }}</div>
                        </td>
                        <td>
                            @if ($profile->identity_document_path && \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_path))
                                @php($isPdf = str_ends_with(strtolower((string) $profile->identity_document_path), '.pdf'))
                                <div class="mb-2">
                                    <div class="small fw-semibold mb-1">الأمامية</div>
                                    <a href="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'front']) }}" target="_blank" rel="noopener">
                                        @if ($isPdf)
                                            <span class="d-inline-flex align-items-center justify-content-center border rounded bg-light" style="width:160px;height:100px"><i class="bi bi-file-earmark-pdf fs-3 text-danger"></i><span class="small ms-1">PDF</span></span>
                                        @else
                                            <img src="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'front']) }}" alt="هوية أمامية — {{ $profile->store_name }}" class="border rounded" style="width:160px;height:100px;object-fit:contain;background:#f8f9fa;">
                                        @endif
                                    </a>
                                </div>
                                @if ($profile->identity_document_back_path && \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_back_path))
                                    @php($isPdfBack = str_ends_with(strtolower((string) $profile->identity_document_back_path), '.pdf'))
                                    <div>
                                        <div class="small fw-semibold mb-1">الخلفية/إضافية</div>
                                        <a href="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'back']) }}" target="_blank" rel="noopener">
                                            @if ($isPdfBack)
                                                <span class="d-inline-flex align-items-center justify-content-center border rounded bg-light" style="width:160px;height:100px"><i class="bi bi-file-earmark-pdf fs-3 text-danger"></i><span class="small ms-1">PDF</span></span>
                                            @else
                                                <img src="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'back']) }}" alt="هوية خلفية — {{ $profile->store_name }}" class="border rounded" style="width:160px;height:100px;object-fit:contain;background:#f8f9fa;">
                                            @endif
                                        </a>
                                    </div>
                                @else
                                    <div class="small text-muted">لا يوجد وجه خلفي/صفحة إضافية.</div>
                                @endif
                            @else
                                <span class="text-danger small">لا توجد وثيقة صالحة</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <form action="{{ route('admin.sellers.approve', $profile) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="text" name="internal_note" class="form-control form-control-sm mb-1" placeholder="ملاحظة داخلية (اختياري — لا يراها البائع)" maxlength="500">
                                <button class="btn btn-sm btn-success w-100" @disabled(! $profile->identity_document_path || ! \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_path))>اعتماد الهوية</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="collapse" data-bs-target="#review-reject-{{ $profile->id }}">طلب إعادة إرسال</button>
                            <div class="collapse mt-2" id="review-reject-{{ $profile->id }}">
                                <form action="{{ route('admin.sellers.reject', $profile) }}" method="POST" class="d-grid gap-1">
                                    @csrf
                                    <select name="reason_code" class="form-select form-select-sm">
                                        <option value="">سبب جاهز (اختياري)</option>
                                        <option value="unclear">الوثيقة غير واضحة أو مقصوصة</option>
                                        <option value="incomplete">الوثيقة ناقصة / الوجه الخلفي مطلوب</option>
                                        <option value="expired">الوثيقة منتهية أو غير صالحة</option>
                                        <option value="mismatch">البيانات لا تطابق المتجر/المالك</option>
                                        <option value="unreadable">الملف غير قابل للقراءة</option>
                                        <option value="other">سبب آخر (اكتبه أدناه)</option>
                                    </select>
                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="سبب للبائع (مطلوب إذا لم يكفِ الجاهز)" maxlength="255">
                                    <input type="text" name="internal_note" class="form-control form-control-sm" placeholder="ملاحظة داخلية (اختياري)" maxlength="500">
                                    <button class="btn btn-sm btn-danger">إرسال طلب إعادة الإرسال</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نتائج مطابقة للفلاتر الحالية.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<h2 class="h5 mb-3">طلبات البائعين المعلّقة</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>اسم المتجر</th><th>صاحب الطلب</th><th>الهاتف</th><th>وثيقة الهوية</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($pendingSellerProfiles as $profile)
                        <tr>
                            <td class="fw-bold" translate="no">{{ $profile->store_name }}</td>
                            <td>{{ $profile->user->name }}</td>
                            <td>{{ $profile->user->phone }}</td>
                            <td style="min-width:340px">
                                @if ($profile->identity_document_path && \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_path))
                                    <div class="small mb-2">
                                        <div><strong>الإرسال:</strong> {{ $profile->identity_document_submitted_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                        <div><strong>النوع:</strong> {{ $profile->identity_type ?: '—' }} @if($profile->identity_number)| <strong>الرقم:</strong> {{ $profile->identity_number }}@endif</div>
                                        @if($profile->commercial_register)<div><strong>سجل تجاري:</strong> {{ $profile->commercial_register }}</div>@endif
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <div>
                                            @php($isPdf = str_ends_with(strtolower((string) $profile->identity_document_path), '.pdf'))
                                            <div class="small text-muted">أمامية</div>
                                            <a href="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'front']) }}" target="_blank" rel="noopener">
                                                @if ($isPdf)<span class="d-inline-flex align-items-center justify-content-center border rounded bg-light" style="width:140px;height:92px"><i class="bi bi-file-earmark-pdf fs-4 text-danger"></i></span>
                                                @else<img src="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'front']) }}" alt="هوية أمامية" class="border rounded" style="width:140px;height:92px;object-fit:contain;background:#f8f9fa;">@endif
                                            </a>
                                        </div>
                                        @if ($profile->identity_document_back_path && \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_back_path))
                                            @php($isPdfBack = str_ends_with(strtolower((string) $profile->identity_document_back_path), '.pdf'))
                                            <div>
                                                <div class="small text-muted">خلفية/إضافية</div>
                                                <a href="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'back']) }}" target="_blank" rel="noopener">
                                                    @if ($isPdfBack)<span class="d-inline-flex align-items-center justify-content-center border rounded bg-light" style="width:140px;height:92px"><i class="bi bi-file-earmark-pdf fs-4 text-danger"></i></span>
                                                    @else<img src="{{ route('admin.sellers.identity-document', ['sellerProfile' => $profile, 'side' => 'back']) }}" alt="هوية خلفية" class="border rounded" style="width:140px;height:92px;object-fit:contain;background:#f8f9fa;">@endif
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-danger small">لا توجد وثيقة — يلزم إعادة الإرسال</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.sellers.approve', $profile) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success" @disabled(! $profile->identity_document_path || ! \Illuminate\Support\Facades\Storage::disk('identity_documents')->exists($profile->identity_document_path))>موافقة</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-{{ $profile->id }}">رفض</button>

                                <div class="collapse mt-2" id="reject-{{ $profile->id }}">
                                    <form action="{{ route('admin.sellers.reject', $profile) }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="سبب الرفض" required>
                                        <button class="btn btn-sm btn-danger">تأكيد الرفض</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد طلبات معلّقة حالياً.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
