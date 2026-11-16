@extends('layouts.app')

@section('title', __('تحليلات المنصة').' - تريندشام')

@push('styles')
@include('partials._analytics_styles')
<style>
    .an-bar { block-size: 8px; border-radius: 999px; background: var(--an-surface-2); overflow: hidden; }
    .an-bar span { display: block; block-size: 100%; background: var(--an-accent); border-radius: 999px; }
</style>
@endpush

@section('content')
@php
    $currency = __('ل.س');
    $k = $data['kpis'];
    $c = $data['changes'];
    $u = $data['users'];
    $cat = $data['catalog'];
    $r = $data['returns'];
    $f = $data['finance'];
    $m = $data['marketing'];
    $s = $data['support'];
    $money = fn ($v) => number_format($v);
@endphp
<div class="container ts-an mb-5">
    {{-- العنوان والفترة --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="small text-decoration-none d-inline-flex align-items-center gap-1 mb-1" style="color: var(--an-accent);">
                <i class="bi bi-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" aria-hidden="true"></i> {{ __('لوحة المدير') }}
            </a>
            <h1 class="an-heading h3 mb-0">{{ __('تحليلات المنصة') }}</h1>
        </div>
        <nav class="an-days" aria-label="{{ __('الفترة') }}">
            @foreach (\App\Services\AdminAnalyticsService::WINDOWS as $period)
                <a href="{{ route('admin.analytics', ['days' => $period]) }}"
                   class="{{ $days === $period ? 'active' : '' }}"
                   @if ($days === $period) aria-current="page" @endif>
                    {{ __('آخر :n يوم', ['n' => $period]) }}
                </a>
            @endforeach
        </nav>
    </div>
    <p class="an-muted small mb-4">{{ __('من :from إلى :to — النسب مقارنة بالفترة السابقة بنفس الطول.', ['from' => $data['from'], 'to' => $data['to']]) }}</p>

    {{-- المؤشرات الرئيسية --}}
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-graph-up-arrow', 'label' => __('إجمالي المبيعات'), 'value' => $money($k['gmv']), 'unit' => $currency, 'change' => $c['gmv']])
        @include('admin.partials._stat', ['icon' => 'bi-bag-check', 'label' => __('الطلبات'), 'value' => $money($k['orders']), 'change' => $c['orders'], 'sub' => __('متوسط الطلب').': '.$money($k['avg_order_value']).' '.$currency])
        @include('admin.partials._stat', ['icon' => 'bi-bank', 'label' => __('عمولة المنصة'), 'value' => $money($k['commission']), 'unit' => $currency, 'change' => $c['commission']])
        @include('admin.partials._stat', ['icon' => 'bi-person-plus', 'label' => __('عملاء جدد'), 'value' => $money($k['new_customers']), 'change' => $c['new_customers']])
        @include('admin.partials._stat', ['icon' => 'bi-arrow-counterclockwise', 'label' => __('مبالغ مستردة'), 'value' => $money($k['refunded']), 'unit' => $currency])
        @include('admin.partials._stat', ['icon' => 'bi-x-octagon', 'label' => __('نسبة الإلغاء'), 'value' => $k['cancel_rate'].'%'])
        @include('admin.partials._stat', ['icon' => 'bi-arrow-return-left', 'label' => __('نسبة الإرجاع'), 'value' => $r['return_rate'].'%'])
        @include('admin.partials._stat', ['icon' => 'bi-wallet2', 'label' => __('أرصدة محافظ البائعين'), 'value' => $money($f['wallets_balance']), 'unit' => $currency])
    </div>

    {{-- المبيعات والعمولة --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <h2 class="an-section"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> {{ __('المبيعات والعمولة') }}</h2>
            <div class="an-card"><div class="an-chart" style="block-size: 300px;"><canvas id="salesChart" role="img" aria-label="{{ __('المبيعات والعمولة') }}"></canvas></div></div>
        </div>
        <div class="col-lg-4">
            <h2 class="an-section"><i class="bi bi-pie-chart" aria-hidden="true"></i> {{ __('حالات الطلبات') }}</h2>
            <div class="an-card"><div class="an-chart" style="block-size: 300px;"><canvas id="statusChart" role="img" aria-label="{{ __('حالات الطلبات') }}"></canvas></div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <h2 class="an-section"><i class="bi bi-people" aria-hidden="true"></i> {{ __('الطلبات والعملاء الجدد') }}</h2>
            <div class="an-card"><div class="an-chart" style="block-size: 260px;"><canvas id="ordersChart" role="img" aria-label="{{ __('الطلبات والعملاء الجدد') }}"></canvas></div></div>
        </div>
        <div class="col-lg-4">
            <h2 class="an-section"><i class="bi bi-credit-card" aria-hidden="true"></i> {{ __('طرق الدفع') }}</h2>
            <div class="an-card">
                @foreach ($data['payment_methods'] as $pm)
                    <div class="d-flex justify-content-between small mb-1"><span>{{ $pm['label'] }}</span><span>{{ $money($pm['count']) }} · {{ $money($pm['amount']) }} {{ $currency }}</span></div>
                    @php($maxPm = max(1, collect($data['payment_methods'])->max('amount')))
                    <div class="an-bar mb-3"><span style="inline-size: {{ round($pm['amount'] / $maxPm * 100) }}%"></span></div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- الأفضل أداءً --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-shop" aria-hidden="true"></i> {{ __('أفضل المتاجر') }}</h2>
            <div class="an-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table an-table">
                        <thead><tr><th>{{ __('المتجر') }}</th><th>{{ __('المبيعات') }}</th><th>{{ __('الطلبات') }}</th><th>{{ __('العمولة') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['top_sellers'] as $row)
                                <tr><td translate="no">{{ $row['name'] }}</td><td>{{ $money($row['sales']) }}</td><td>{{ $row['orders'] }}</td><td>{{ $money($row['commission']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="an-empty">{{ __('لا توجد مبيعات في هذه الفترة.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-trophy" aria-hidden="true"></i> {{ __('أكثر المنتجات مبيعاً') }}</h2>
            <div class="an-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table an-table">
                        <thead><tr><th>{{ __('المنتج') }}</th><th>{{ __('الكمية') }}</th><th>{{ __('الإيراد') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['top_products'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td>{{ $row['sold_count'] }}</td><td>{{ $money($row['revenue']) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="an-empty">{{ __('لا توجد مبيعات في هذه الفترة.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-grid" aria-hidden="true"></i> {{ __('أفضل الأقسام') }}</h2>
            <div class="an-card"><div class="an-chart" style="block-size: 260px;"><canvas id="categoryChart" role="img" aria-label="{{ __('أفضل الأقسام') }}"></canvas></div></div>
        </div>
        <div class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> {{ __('أسباب الإرجاع') }}</h2>
            <div class="an-card"><div class="an-chart" style="block-size: 260px;"><canvas id="reasonChart" role="img" aria-label="{{ __('أسباب الإرجاع') }}"></canvas></div></div>
        </div>
    </div>

    {{-- المستخدمون --}}
    <h2 class="an-section"><i class="bi bi-people" aria-hidden="true"></i> {{ __('المستخدمون') }}</h2>
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-person', 'label' => __('العملاء'), 'value' => $money($u['customers'])])
        @include('admin.partials._stat', ['icon' => 'bi-cart-check', 'label' => __('مشترون نشطون'), 'value' => $money($u['active_buyers']), 'sub' => __('منهم متكررون').': '.$money($u['repeat_buyers'])])
        @include('admin.partials._stat', ['icon' => 'bi-shop-window', 'label' => __('المتاجر المعتمدة'), 'value' => $money($u['sellers']), 'sub' => __('باعت في الفترة').': '.$money($u['active_sellers']), 'href' => route('admin.sellers.index')])
        @include('admin.partials._stat', ['icon' => 'bi-hourglass-split', 'label' => __('طلبات متاجر معلّقة'), 'value' => $money($u['pending_sellers']), 'sub' => __('متاجر جديدة في الفترة').': '.$money($u['new_sellers']).' · '.__('موقوفة').': '.$money($u['suspended_sellers']), 'href' => route('admin.dashboard')])
    </div>

    {{-- المنتجات --}}
    <h2 class="an-section"><i class="bi bi-box-seam" aria-hidden="true"></i> {{ __('المنتجات') }}</h2>
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-box', 'label' => __('منتجات منشورة'), 'value' => $money($cat['active_products']), 'sub' => __('أقسام فعّالة').': '.$money($cat['categories'])])
        @include('admin.partials._stat', ['icon' => 'bi-clipboard-check', 'label' => __('بانتظار المراجعة'), 'value' => $money($cat['pending_review']), 'href' => route('admin.products.index')])
        @include('admin.partials._stat', ['icon' => 'bi-exclamation-triangle', 'label' => __('مخزون منخفض'), 'value' => $money($cat['low_stock'])])
        @include('admin.partials._stat', ['icon' => 'bi-slash-circle', 'label' => __('نفد من المخزون'), 'value' => $money($cat['out_of_stock'])])
    </div>

    {{-- المرتجعات --}}
    <h2 class="an-section"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('المرتجعات') }}</h2>
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-inbox', 'label' => __('طلبات إرجاع في الفترة'), 'value' => $money($r['total'])])
        @include('admin.partials._stat', ['icon' => 'bi-bell', 'label' => __('تنتظر الإدارة'), 'value' => $money($r['needs_action']), 'href' => route('admin.returns.index')])
        <div class="col-12 col-xl-6">
            <div class="an-card">
                @foreach ($r['by_status'] as $st)
                    <span class="an-chip">{{ $st['label'] }}: {{ $st['count'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- المال --}}
    <h2 class="an-section"><i class="bi bi-cash-stack" aria-hidden="true"></i> {{ __('المال') }}</h2>
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-check2-circle', 'label' => __('عمولة مُسوّاة'), 'value' => $money($f['settled_commission']), 'unit' => $currency])
        @include('admin.partials._stat', ['icon' => 'bi-hourglass', 'label' => __('سحوبات معلّقة'), 'value' => $money($f['pending_withdrawals_amount']), 'unit' => $currency, 'sub' => __('عدد الطلبات').': '.$money($f['pending_withdrawals']), 'href' => route('admin.withdrawals.index')])
        @include('admin.partials._stat', ['icon' => 'bi-send-check', 'label' => __('سحوبات مدفوعة'), 'value' => $money($f['paid_withdrawals_amount']), 'unit' => $currency])
        @include('admin.partials._stat', ['icon' => 'bi-megaphone', 'label' => __('إيراد الإعلانات'), 'value' => $money($f['showcase_revenue'] + $f['story_revenue']), 'unit' => $currency, 'sub' => __('العروض').': '.$money($f['showcase_revenue']).' · '.__('الستوري').': '.$money($f['story_revenue'])])
    </div>

    {{-- التسويق --}}
    <h2 class="an-section"><i class="bi bi-stars" aria-hidden="true"></i> {{ __('التسويق والولاء') }}</h2>
    <div class="row g-3 mb-4">
        @include('admin.partials._stat', ['icon' => 'bi-ticket-perforated', 'label' => __('استخدامات الكوبونات'), 'value' => $money($m['coupon_redemptions']), 'sub' => __('حملات فعّالة').': '.$money($m['active_campaigns']), 'href' => route('admin.coupon-campaigns.index')])
        @include('admin.partials._stat', ['icon' => 'bi-percent', 'label' => __('قيمة الخصومات'), 'value' => $money($m['coupon_discount']), 'unit' => $currency, 'sub' => __('على حساب المنصة').': '.$money($m['platform_funded_discount'])])
        @include('admin.partials._stat', ['icon' => 'bi-gem', 'label' => __('نقاط الولاء'), 'value' => $money($m['points_earned']), 'sub' => __('مستبدلة').': '.$money($m['points_redeemed']), 'href' => route('admin.loyalty.index')])
        @include('admin.partials._stat', ['icon' => 'bi-heart', 'label' => __('تفاعل'), 'value' => $money($m['favorites']), 'sub' => __('متابعات متاجر جديدة').': '.$money($m['store_followers'])])
    </div>

    {{-- الدعم --}}
    <h2 class="an-section"><i class="bi bi-headset" aria-hidden="true"></i> {{ __('الدعم') }}</h2>
    <div class="row g-3">
        @include('admin.partials._stat', ['col' => 'col-12 col-md-4', 'icon' => 'bi-chat-left-text', 'label' => __('رسائل بائعين مفتوحة'), 'value' => $money($s['open_seller_tickets']), 'href' => route('admin.support.index')])
        @include('admin.partials._stat', ['col' => 'col-12 col-md-4', 'icon' => 'bi-robot', 'label' => __('محادثات محوّلة للإدارة'), 'value' => $money($s['escalated_chats']), 'href' => route('admin.assistant-chats.index')])
        @include('admin.partials._stat', ['col' => 'col-12 col-md-4', 'icon' => 'bi-question-circle', 'label' => __('أسئلة منتجات بلا جواب'), 'value' => $money($s['unanswered_questions'])])
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (!window.Chart) return;

    const series = @json($data['series']);
    const statuses = @json($data['order_status']);
    const categories = @json($data['top_categories']);
    const reasons = @json($data['returns']['by_reason']);
    const t = {
        gmv: @json(__('المبيعات')),
        commission: @json(__('العمولة')),
        orders: @json(__('الطلبات')),
        customers: @json(__('عملاء جدد')),
        revenue: @json(__('الإيراد')),
        returns: @json(__('طلبات الإرجاع')),
        currency: @json($currency),
    };
    const fmt = (n) => new Intl.NumberFormat('en-US').format(n);
    const soft = (hex, alpha) => {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(x => x + x).join('') : h;
        const n = parseInt(full, 16);
        return Number.isNaN(n) ? 'transparent' : `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };
    const root = document.querySelector('.ts-an');
    const palette = () => {
        const css = getComputedStyle(root);
        const read = (name) => css.getPropertyValue(name).trim();
        return { accent: read('--an-accent'), gold: read('--an-gold'), blue: read('--an-blue'), surface: read('--an-surface'), muted: read('--an-muted'), border: read('--an-border') };
    };
    const statusColors = { pending: '#E0A800', paid: '#17A2B8', processing: '#0F6EBA', shipped: '#8E44AD', delivered: '#0B8A68', cancelled: '#EB5757' };
    const moneyTip = { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + fmt(ctx.parsed.y ?? ctx.parsed.x) + ' ' + t.currency } };

    Chart.defaults.font.family = 'Tajawal, sans-serif';
    const charts = [];
    const add = (id, config) => { const el = document.getElementById(id); if (el) charts.push(new Chart(el, config)); };
    const build = () => {
        const c = palette();
        Chart.defaults.color = c.muted;
        Chart.defaults.borderColor = c.border;
        charts.splice(0).forEach(chart => chart.destroy());
        const labels = series.map(p => p.date.slice(5));

        add('salesChart', {
            type: 'line',
            data: { labels, datasets: [
                { label: t.gmv, data: series.map(p => p.gmv), borderColor: c.accent, backgroundColor: soft(c.accent, .12), fill: true, tension: .35, pointRadius: 2 },
                { label: t.commission, data: series.map(p => p.commission), borderColor: c.gold, backgroundColor: soft(c.gold, .12), fill: true, tension: .35, pointRadius: 2 },
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { tooltip: moneyTip },
                scales: { y: { beginAtZero: true, ticks: { callback: (v) => fmt(v) } }, x: { grid: { display: false } } } },
        });

        add('ordersChart', {
            data: { labels, datasets: [
                { type: 'bar', label: t.orders, data: series.map(p => p.orders), backgroundColor: soft(c.blue, .55), borderRadius: 4 },
                { type: 'line', label: t.customers, data: series.map(p => p.new_customers), borderColor: c.gold, tension: .35, pointRadius: 2 },
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
        });

        const active = statuses.filter(s => s.count > 0);
        add('statusChart', {
            type: 'doughnut',
            data: { labels: active.map(s => s.label), datasets: [{ data: active.map(s => s.count), backgroundColor: active.map(s => statusColors[s.status] || '#888'), borderColor: c.surface, borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });

        add('categoryChart', {
            type: 'bar',
            data: { labels: categories.map(x => x.name), datasets: [{ label: t.revenue, data: categories.map(x => x.revenue), backgroundColor: soft(c.accent, .6), borderRadius: 4 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: moneyTip },
                scales: { x: { beginAtZero: true, ticks: { callback: (v) => fmt(v) } }, y: { grid: { display: false } } } },
        });

        add('reasonChart', {
            type: 'bar',
            data: { labels: reasons.map(x => x.label), datasets: [{ label: t.returns, data: reasons.map(x => x.count), backgroundColor: soft(c.gold, .7), borderRadius: 4 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } },
        });
    };

    build();
    document.addEventListener('ts:themechange', build);
})();
</script>
@endpush
