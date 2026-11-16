@extends('layouts.app')

@section('title', 'لوحة البائع - تريندشام')

@push('styles')
<style>
    /* ألوان اللوحة من متغيرات الموقع (ts-app.css) ويُعاد تعريفها في الوضع الداكن (ts-dark.css) */
    .ts-sd {
        --sd-surface: var(--ts-surface);
        --sd-surface-2: var(--ts-surface-2);
        --sd-border: var(--ts-border);
        --sd-ink: var(--ts-ink);
        --sd-muted: var(--ts-muted);
        --sd-accent: var(--ts-emerald);
        --sd-gold: var(--ts-gold);
        --sd-blue: #0F6EBA;
        --sd-purple: #8E44AD;
        --sd-red: #C23B2A;
        --sd-up: #157347;
        --sd-down: #C23B2A;
        color: var(--sd-ink);
    }
    html.dark .ts-sd {
        --sd-accent: var(--tsd-accent);
        --sd-gold: var(--tsd-gold);
        --sd-blue: #8BB9FF;
        --sd-purple: #C9A3E0;
        --sd-red: #FF8F8F;
        --sd-up: #7ED8A8;
        --sd-down: #FF8F8F;
    }

    .ts-sd-title { font-family: 'Reem Kufi', sans-serif; font-weight: 700; color: var(--sd-ink); }
    .ts-sd-sub { color: var(--sd-muted); }
    .ts-sd .btn-sd-primary { background: var(--ts-emerald); border: 0; color: #fff; font-weight: 700; }
    .ts-sd .btn-sd-primary:hover, .ts-sd .btn-sd-primary:focus-visible { background: var(--ts-emerald-dark); color: #fff; }

    /* نغمات الأيقونات: لون واحد للنص وخلفية شفافة منه */
    .ts-tone { color: var(--tone); background: color-mix(in srgb, var(--tone) 13%, transparent); }
    .ts-tone--emerald { --tone: var(--sd-accent); }
    .ts-tone--gold { --tone: var(--sd-gold); }
    .ts-tone--blue { --tone: var(--sd-blue); }
    .ts-tone--purple { --tone: var(--sd-purple); }
    .ts-tone--red { --tone: var(--sd-red); }

    /* بطاقات المؤشرات */
    .ts-kpi-card {
        background: var(--sd-surface);
        border: 1px solid var(--sd-border);
        border-radius: 16px;
        padding: 1.25rem;
        block-size: 100%;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .ts-kpi-card:hover { transform: translateY(-3px); box-shadow: var(--ts-shadow-lift); border-color: var(--sd-gold); }
    .ts-kpi-icon {
        inline-size: 42px;
        block-size: 42px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        font-size: 1.15rem;
        margin-block-end: .75rem;
    }
    .ts-kpi-label { color: var(--sd-muted); font-size: .85rem; font-weight: 700; }
    .ts-kpi-value { font-family: 'Reem Kufi', sans-serif; font-size: 1.45rem; font-weight: 700; color: var(--sd-ink); margin-block: .2rem; overflow-wrap: anywhere; }
    .ts-kpi-value small { font-family: 'Tajawal', sans-serif; font-size: .8rem; font-weight: 500; color: var(--sd-muted); }
    .ts-kpi-trend { font-size: .78rem; font-weight: 700; color: var(--sd-muted); }
    .ts-kpi-trend.up { color: var(--sd-up); }
    .ts-kpi-trend.down { color: var(--sd-down); }

    /* أقسام اللوحة */
    .ts-panel {
        background: var(--sd-surface);
        border: 1px solid var(--sd-border);
        border-radius: 18px;
        padding: 1.25rem;
    }
    .ts-panel-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-block-end: 1rem; flex-wrap: wrap; }
    .ts-panel-title { font-family: 'Reem Kufi', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--sd-ink); display: flex; align-items: center; gap: .5rem; margin: 0; }
    .ts-panel-title i { color: var(--sd-gold); }
    .ts-chip { background: var(--sd-surface-2); color: var(--sd-muted); border-radius: 999px; padding: .25rem .75rem; font-size: .75rem; font-weight: 700; }
    .ts-panel-link { color: var(--sd-accent); font-size: .85rem; font-weight: 700; text-decoration: none; }
    .ts-panel-link:hover { color: var(--sd-gold); }

    /* صحة المتجر */
    .ts-meter-row { display: flex; justify-content: space-between; font-size: .85rem; font-weight: 700; margin-block-end: .35rem; }
    .ts-meter { block-size: 7px; background: var(--sd-surface-2); border-radius: 999px; overflow: hidden; }
    .ts-meter > span { display: block; block-size: 100%; border-radius: inherit; background: var(--tone); }
    .ts-mini-stat { background: var(--sd-surface-2); border-radius: 12px; padding: .85rem .5rem; text-align: center; }
    .ts-mini-stat .l { font-size: .78rem; color: var(--sd-muted); }
    .ts-mini-stat .v { font-family: 'Reem Kufi', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--sd-ink); }

    /* الإجراءات السريعة */
    .ts-action-btn {
        position: relative;
        background: var(--sd-surface);
        border: 1px solid var(--sd-border);
        border-radius: 14px;
        padding: 1.1rem .75rem;
        block-size: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        text-align: center;
        text-decoration: none;
        color: var(--sd-ink);
        transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
    }
    .ts-action-btn:hover, .ts-action-btn:focus-visible { border-color: var(--sd-gold); background: var(--sd-surface-2); color: var(--sd-ink); transform: translateY(-3px); }
    .ts-action-btn .ts-kpi-icon { margin: 0; }
    .ts-action-btn span.ts-action-label { font-size: .82rem; font-weight: 700; }
    .ts-action-count {
        position: absolute;
        inset-block-start: .5rem;
        inset-inline-end: .5rem;
        min-inline-size: 1.4rem;
        padding: .1rem .4rem;
        border-radius: 999px;
        background: var(--sd-gold);
        color: #14251E;
        font-size: .7rem;
        font-weight: 800;
    }

    /* جدول الطلبات (relative: كي لا يخرج النص المخفي المطلق عن حاوية التمرير فيوسّع الصفحة على الجوال) */
    .ts-sd .table-responsive { position: relative; }
    .ts-sd .ts-table { --bs-table-bg: transparent; --bs-table-color: var(--sd-ink); --bs-table-hover-bg: var(--sd-surface-2); margin: 0; }
    .ts-sd .ts-table thead th { background: var(--sd-surface-2); color: var(--sd-muted); font-size: .78rem; font-weight: 700; border: 0; padding: .8rem 1rem; white-space: nowrap; }
    .ts-sd .ts-table tbody td { background: transparent; color: var(--sd-ink); border-block-end: 1px solid var(--sd-border); padding: .8rem 1rem; vertical-align: middle; font-size: .88rem; }
    .ts-sd .ts-table tbody tr:last-child td { border-block-end: 0; }
    .ts-sd .ts-table thead th:first-child { border-start-start-radius: 10px; }
    .ts-sd .ts-table thead th:last-child { border-start-end-radius: 10px; }

    /* شارات حالة الطلب (لكل حالة لونها في الوضعين) */
    .ts-badge { display: inline-block; padding: .3rem .75rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .ts-badge.pending { background: #FFF3CD; color: #856404; }
    .ts-badge.paid { background: #CCE5FF; color: #004085; }
    .ts-badge.processing { background: #E8F0FE; color: #1A56DB; }
    .ts-badge.shipped { background: #D1ECF1; color: #0C5460; }
    .ts-badge.delivered { background: #D4EDDA; color: #155724; }
    .ts-badge.cancelled { background: #F8D7DA; color: #721C24; }
    html.dark .ts-badge.paid { background: rgba(13, 110, 253, .18); color: #8BB9FF; }
    html.dark .ts-badge.shipped { background: rgba(23, 162, 184, .18); color: #7FD6E3; }

    /* الأكثر مبيعاً */
    .ts-top-item { display: flex; align-items: center; gap: .75rem; padding-block: .6rem; }
    .ts-top-item + .ts-top-item { border-block-start: 1px solid var(--sd-border); }
    .ts-top-thumb { inline-size: 48px; block-size: 48px; flex-shrink: 0; border-radius: 10px; overflow: hidden; background: var(--ts-media-bg); display: grid; place-items: center; color: var(--sd-muted); }
    .ts-top-thumb img { inline-size: 100%; block-size: 100%; object-fit: cover; }
    .ts-top-name { font-size: .88rem; font-weight: 700; color: var(--sd-ink); }
    .ts-top-meta { font-size: .75rem; color: var(--sd-muted); }
    .ts-top-revenue { font-size: .85rem; font-weight: 800; color: var(--sd-accent); white-space: nowrap; }

    .ts-empty-note { text-align: center; color: var(--sd-muted); padding: 1.75rem .5rem; font-size: .9rem; }
</style>
@endpush

@section('content')
<div class="container ts-sd">
    @if ($profile->isSuspended())
        <div class="alert alert-danger d-flex gap-3 align-items-start" role="alert">
            <i class="bi bi-pause-circle-fill fs-3" aria-hidden="true"></i>
            <div>
                <div class="fw-bold mb-1">متجرك موقوف مؤقتاً من الإدارة</div>
                <div>السبب: <span translate="no">{{ $profile->suspension_reason }}</span></div>
                <div class="small mt-1">منتجاتك مخفية عن الزوار ولا يمكن إضافة منتجات جديدة حتى إعادة التفعيل. رصيدك وطلباتك محفوظة كاملة.</div>
                <a href="{{ route('seller.support.create') }}" class="btn btn-sm btn-light mt-2">تواصل مع الإدارة</a>
            </div>
        </div>
    @endif

    {{-- الترحيب --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="ts-sd-title h3 mb-1">مرحباً بك في تريندشام</h1>
            <p class="ts-sd-sub small mb-0">إليك ملخص أداء متجرك.</p>
        </div>
        <a href="{{ route('seller.analytics') }}" class="btn btn-sd-primary px-4">
            <i class="bi bi-graph-up me-2" aria-hidden="true"></i>التحليلات المتقدمة
        </a>
    </div>

    {{-- المؤشرات --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="ts-kpi-card">
                <div class="ts-kpi-icon ts-tone ts-tone--emerald"><i class="bi bi-wallet2" aria-hidden="true"></i></div>
                <div class="ts-kpi-label">المبيعات الإجمالية</div>
                <div class="ts-kpi-value">{{ number_format($total_sales) }} <small>ل.س</small></div>
                @if ($monthly_growth === null)
                    <div class="ts-kpi-trend">لا توجد بيانات للمقارنة</div>
                @else
                    <div class="ts-kpi-trend {{ $monthly_growth >= 0 ? 'up' : 'down' }}">
                        <i class="bi bi-arrow-{{ $monthly_growth >= 0 ? 'up' : 'down' }}" aria-hidden="true"></i>
                        {{ number_format(abs($monthly_growth)) }}% مقارنة بالشهر الماضي
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ts-kpi-card">
                <div class="ts-kpi-icon ts-tone ts-tone--gold"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
                <div class="ts-kpi-label">صافي الأرباح</div>
                <div class="ts-kpi-value">{{ number_format($net_profit) }} <small>ل.س</small></div>
                <div class="ts-kpi-trend">بعد خصم عمولة المنصة</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ts-kpi-card">
                <div class="ts-kpi-icon ts-tone ts-tone--blue"><i class="bi bi-bag-check" aria-hidden="true"></i></div>
                <div class="ts-kpi-label">الطلبات</div>
                <div class="ts-kpi-value">{{ number_format($orders_count) }}</div>
                <div class="ts-kpi-trend">{{ __(':n قطعة مباعة', ['n' => number_format($items_sold)]) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="ts-kpi-card">
                <div class="ts-kpi-icon ts-tone ts-tone--purple"><i class="bi bi-star" aria-hidden="true"></i></div>
                <div class="ts-kpi-label">تقييم المتجر</div>
                <div class="ts-kpi-value">{{ number_format($seller_rating ?? 0, 1) }} <small>/ 5</small></div>
                <div class="ts-kpi-trend">{{ __(':n تقييم', ['n' => number_format($seller_reviews_count)]) }}</div>
            </div>
        </div>
    </div>

    @php
        $hasReviews = $seller_reviews_count > 0;
        $satisfactionPercent = $hasReviews ? (int) round(($seller_rating ?? 0) * 20) : 0;
        $stockPercent = $products_count > 0
            ? (int) max(0, min(100, round((($products_count - $low_stock_count) / $products_count) * 100)))
            : 0;
    @endphp

    {{-- المبيعات وصحة المتجر --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <section class="ts-panel h-100">
                <div class="ts-panel-head">
                    <h2 class="ts-panel-title"><i class="bi bi-activity" aria-hidden="true"></i> أداء المبيعات</h2>
                    <span class="ts-chip">آخر 14 يوم</span>
                </div>
                <div style="block-size: 240px; position: relative;">
                    <canvas id="mainSalesChart" role="img" aria-label="أداء المبيعات"></canvas>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="ts-panel h-100">
                <div class="ts-panel-head">
                    <h2 class="ts-panel-title"><i class="bi bi-shield-check" aria-hidden="true"></i> صحة المتجر</h2>
                </div>

                <div class="mb-4">
                    <div class="ts-meter-row">
                        <span>معدل رضا العملاء</span>
                        <span style="color: var(--sd-accent);">{{ $hasReviews ? $satisfactionPercent.'%' : '—' }}</span>
                    </div>
                    <div class="ts-meter ts-tone--emerald"><span style="inline-size: {{ $satisfactionPercent }}%;"></span></div>
                </div>

                <div class="mb-4">
                    <div class="ts-meter-row">
                        <span>كفاية المخزون</span>
                        <span style="color: var(--sd-gold);">{{ $stockPercent }}%</span>
                    </div>
                    <div class="ts-meter ts-tone--gold"><span style="inline-size: {{ $stockPercent }}%;"></span></div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="ts-mini-stat">
                            <div class="l">المتابعون</div>
                            <div class="v">{{ number_format($followers_count) }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ts-mini-stat">
                            <div class="l">زيارة فريدة</div>
                            <div class="v">{{ number_format($total_views ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ts-mini-stat">
                            <div class="l">زيارة نتج عنها طلب</div>
                            <div class="v">{{ number_format((int) ($attributed_converting_visits ?? 0)) }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ts-mini-stat">
                            <div class="l">معدل التحويل</div>
                            <div class="v" dir="ltr">{{ number_format((float) ($conversion_rate ?? 0), 2) }}%</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- الإجراءات السريعة --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.products.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--emerald"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                <span class="ts-action-label">المنتجات</span>
                @if ($pending_products_count > 0)
                    <span class="ts-action-count">{{ $pending_products_count }}<span class="visually-hidden"> قيد المراجعة</span></span>
                @endif
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.orders.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--gold"><i class="bi bi-cart-check" aria-hidden="true"></i></span>
                <span class="ts-action-label">الطلبات</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.returns.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--gold"><i class="bi bi-arrow-return-left" aria-hidden="true"></i></span>
                <span class="ts-action-label">{{ __('المرتجعات') }}</span>
                @if ($returns_requested_count > 0)
                    <span class="ts-action-count">{{ $returns_requested_count }}<span class="visually-hidden"> {{ __('بانتظار ردك') }}</span></span>
                @endif
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.wallet.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--blue"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                <span class="ts-action-label">المحفظة</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.coupons.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--emerald"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></span>
                <span class="ts-action-label">الكوبونات</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.daily-deals.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--red"><i class="bi bi-lightning-charge" aria-hidden="true"></i></span>
                <span class="ts-action-label">عروض اليوم</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.showcases.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--gold"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                <span class="ts-action-label">مساحات العرض</span>
                @if ($pending_showcases_count > 0)
                    <span class="ts-action-count">{{ $pending_showcases_count }}<span class="visually-hidden"> قيد المراجعة</span></span>
                @endif
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.stories.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--purple"><i class="bi bi-camera-reels" aria-hidden="true"></i></span>
                <span class="ts-action-label">الستوري</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.conversations.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--blue"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                <span class="ts-action-label">الدردشة</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.store.edit') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--emerald"><i class="bi bi-shop-window" aria-hidden="true"></i></span>
                <span class="ts-action-label">إعدادات المتجر</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.identity-verification.create') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--purple"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                <span class="ts-action-label">التحقق من الهوية</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('profile.edit') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--gold"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                <span class="ts-action-label">الملف الشخصي</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('seller.support.index') }}" class="ts-action-btn">
                <span class="ts-kpi-icon ts-tone ts-tone--red"><i class="bi bi-headset" aria-hidden="true"></i></span>
                <span class="ts-action-label">الدعم</span>
            </a>
        </div>
    </div>

    {{-- تنبيه المخزون المنخفض --}}
    @if ($low_stock_count > 0)
        <div class="ts-panel d-flex flex-wrap align-items-center gap-3 mb-4" style="border-color: var(--sd-gold);">
            <span class="ts-kpi-icon ts-tone ts-tone--gold m-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
            <div class="flex-grow-1">
                <div class="fw-bold" style="color: var(--sd-gold);">تنبيه المخزون المنخفض</div>
                <div class="ts-sd-sub small">{{ __('يوجد :n منتج بمخزون منخفض - يرجى إعادة التزويد.', ['n' => $low_stock_count]) }}</div>
            </div>
            <a href="{{ route('seller.products.index') }}" class="btn btn-outline-warning btn-sm px-3">إدارة المنتجات</a>
        </div>
    @endif

    <div class="row g-4">
        {{-- أحدث الطلبات --}}
        <div class="col-lg-8">
            <section class="ts-panel h-100">
                <div class="ts-panel-head">
                    <h2 class="ts-panel-title"><i class="bi bi-clock-history" aria-hidden="true"></i> أحدث الطلبات</h2>
                    <a href="{{ route('seller.orders.index') }}" class="ts-panel-link">عرض الكل</a>
                </div>
                @if ($recent_orders->isEmpty())
                    <div class="ts-empty-note">لا توجد طلبات حالياً</div>
                @else
                    <div class="table-responsive">
                        <table class="table ts-table">
                            <thead>
                                <tr>
                                    <th scope="col">رقم الطلب</th>
                                    <th scope="col">العميل</th>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col">المبلغ</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col"><span class="visually-hidden">عرض الطلب</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent_orders as $order)
                                    <tr>
                                        <td class="fw-bold text-nowrap" translate="no">#{{ $order->order_number ?? $order->id }}</td>
                                        <td>
                                            @if ($order->user)
                                                <span translate="no">{{ $order->user->name }}</span>
                                            @else
                                                <span class="ts-sd-sub">العميل محذوف</span>
                                            @endif
                                        </td>
                                        <td class="ts-sd-sub small text-nowrap">{{ $order->created_at?->format('Y-m-d') }}</td>
                                        <td class="fw-bold text-nowrap">{{ number_format(($order->vendor_subtotal ?? 0) / 100) }} ل.س</td>
                                        <td><span class="ts-badge {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('seller.orders.show', $order) }}" class="btn btn-sm btn-light border" title="عرض الطلب" aria-label="عرض الطلب">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- الأكثر مبيعاً --}}
        <div class="col-lg-4">
            <section class="ts-panel h-100">
                <div class="ts-panel-head">
                    <h2 class="ts-panel-title"><i class="bi bi-trophy" aria-hidden="true"></i> الأكثر مبيعاً</h2>
                </div>
                @forelse ($top_products as $product)
                    <div class="ts-top-item">
                        <div class="ts-top-thumb">
                            @if ($product['image'])
                                <img src="{{ $product['image'] }}" alt="" loading="lazy">
                            @else
                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="ts-top-name text-truncate" translate="no">{{ $product['name'] }}</div>
                            <div class="ts-top-meta">{{ __(':n قطعة مباعة', ['n' => number_format($product['sold_count'])]) }}</div>
                        </div>
                        <div class="ts-top-revenue">{{ number_format($product['revenue']) }} ل.س</div>
                    </div>
                @empty
                    <div class="ts-empty-note">لا توجد مبيعات بعد</div>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const canvas = document.getElementById('mainSalesChart');
    if (!canvas || !window.Chart) return;

    const weeklyData = @json($weekly_sales);
    const currency = @json(__('ل.س'));
    const fmt = (n) => new Intl.NumberFormat('en-US').format(n);
    const soft = (hex, alpha) => {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(x => x + x).join('') : h;
        const n = parseInt(full, 16);
        return Number.isNaN(n) ? 'transparent' : `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    // ألوان الرسم من متغيرات اللوحة، فتتبدّل مع الوضع الليلي/النهاري
    const palette = () => {
        const css = getComputedStyle(canvas.closest('.ts-sd'));
        const read = (name) => css.getPropertyValue(name).trim();
        return { accent: read('--sd-accent'), surface: read('--sd-surface'), muted: read('--sd-muted'), border: read('--sd-border') };
    };

    Chart.defaults.font.family = 'Tajawal, sans-serif';
    const colors = palette();
    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: weeklyData.map(d => d.date.split('-').slice(1).reverse().join('/')),
            datasets: [{
                label: @json(__('المبيعات اليومية')),
                data: weeklyData.map(d => d.total),
                borderColor: colors.accent,
                backgroundColor: soft(colors.accent, .1),
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: colors.surface,
                pointBorderColor: colors.accent,
                pointBorderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => fmt(c.parsed.y) + ' ' + currency } },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: colors.border },
                    border: { dash: [5, 5], display: false },
                    ticks: { color: colors.muted, callback: (value) => fmt(value) + ' ' + currency },
                },
                x: {
                    grid: { display: false },
                    ticks: { color: colors.muted },
                },
            },
        },
    });

    document.addEventListener('ts:themechange', () => {
        const c = palette();
        const ds = chart.data.datasets[0];
        ds.borderColor = c.accent;
        ds.pointBorderColor = c.accent;
        ds.pointBackgroundColor = c.surface;
        ds.backgroundColor = soft(c.accent, .1);
        chart.options.scales.y.grid.color = c.border;
        chart.options.scales.y.ticks.color = c.muted;
        chart.options.scales.x.ticks.color = c.muted;
        chart.update();
    });
})();
</script>
@endpush
