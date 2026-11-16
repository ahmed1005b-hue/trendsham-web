@extends('layouts.app')

@section('title', __('seller/analytics.title').' - تريندشام')

@push('styles')
@include('partials._analytics_styles')
@endpush

@section('content')
@php
    $currency = __('ل.س');
@endphp
<div class="container ts-an mb-5">
    {{-- العنوان والفترة --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <div>
            <a href="{{ route('seller.dashboard') }}" class="small text-decoration-none d-inline-flex align-items-center gap-1 mb-1" style="color: var(--an-accent);">
                <i class="bi bi-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" aria-hidden="true"></i> لوحة البائع
            </a>
            <h1 class="an-heading h3 mb-0">{{ __('seller/analytics.title') }}</h1>
        </div>
        <nav class="an-days" aria-label="{{ __('seller/analytics.title') }}">
            @foreach ([7, 14, 30, 90] as $period)
                <a href="{{ route('seller.analytics', ['days' => $period]) }}"
                   class="{{ $days === $period ? 'active' : '' }}"
                   @if ($days === $period) aria-current="page" @endif>
                    {{ __('seller/analytics.days_n', ['n' => $period]) }}
                </a>
            @endforeach
        </nav>
    </div>
    <p class="an-muted small mb-4">{{ __('seller/analytics.subtitle') }}</p>

    {{-- بطاقات الإجماليات --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="an-card">
                <div class="an-title"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> {{ __('seller/analytics.sales') }}</div>
                <div class="an-value">{{ number_format($data['totals']['sales']) }} <small>{{ $currency }}</small></div>
                @include('seller.partials._analytics_change', ['value' => $data['changes']['sales']])
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="an-card">
                <div class="an-title"><i class="bi bi-cash-coin" aria-hidden="true"></i> {{ __('seller/analytics.net_profit') }}</div>
                <div class="an-value">{{ number_format($data['totals']['net_profit']) }} <small>{{ $currency }}</small></div>
                @include('seller.partials._analytics_change', ['value' => $data['changes']['net_profit']])
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="an-card">
                <div class="an-title"><i class="bi bi-bag" aria-hidden="true"></i> {{ __('seller/analytics.orders') }}</div>
                <div class="an-value">{{ number_format($data['totals']['orders']) }}</div>
                <div class="an-sub">{{ __('seller/analytics.avg_order') }}: {{ number_format($data['totals']['avg_order_value']) }} {{ $currency }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="an-card">
                <div class="an-title"><i class="bi bi-wallet2" aria-hidden="true"></i> {{ __('seller/analytics.wallet') }}</div>
                <div class="an-value">{{ number_format($data['wallet']['balance']) }} <small>{{ $currency }}</small></div>
                <div class="an-sub">{{ __('seller/analytics.commission_paid') }}: {{ number_format($data['totals']['commission']) }} {{ $currency }}</div>
            </div>
        </div>
    </div>

    {{-- المبيعات وصافي الدخل --}}
    <section class="mb-4">
        <h2 class="an-section"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> {{ __('seller/analytics.sales_chart') }}</h2>
        <div class="an-card">
            <div class="an-chart" style="block-size: 280px;">
                <canvas id="salesChart" role="img" aria-label="{{ __('seller/analytics.sales_chart') }}"></canvas>
            </div>
        </div>
    </section>

    {{-- حالات الطلبات + حركة المحفظة --}}
    <div class="row g-4 mb-4">
        <section class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-pie-chart" aria-hidden="true"></i> {{ __('seller/analytics.order_statuses') }}</h2>
            <div class="an-card">
                @if (collect($data['status_breakdown'])->sum('count') === 0)
                    <div class="an-empty">{{ __('seller/analytics.no_sales_window') }}</div>
                @else
                    <div class="an-chart" style="block-size: 260px;">
                        <canvas id="statusChart" role="img" aria-label="{{ __('seller/analytics.order_statuses') }}"></canvas>
                    </div>
                    <div class="mt-3">
                        @foreach ($data['status_breakdown'] as $row)
                            @if ($row['count'] > 0)
                                <span class="an-chip">{{ $row['label'] }}: <strong>{{ number_format($row['count']) }}</strong></span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="col-lg-6">
            <h2 class="an-section"><i class="bi bi-activity" aria-hidden="true"></i> {{ __('seller/analytics.wallet_trend') }}</h2>
            <div class="an-card">
                @if ($data['wallet']['transactions']->isEmpty())
                    <div class="an-empty">{{ __('seller/analytics.no_wallet_activity') }}</div>
                @else
                    <div class="an-chart mb-3" style="block-size: 200px;">
                        <canvas id="walletChart" role="img" aria-label="{{ __('seller/analytics.wallet_trend') }}"></canvas>
                    </div>
                    <div class="table-responsive">
                        <table class="table an-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('seller/analytics.wallet_reason') }}</th>
                                    <th scope="col">{{ __('seller/analytics.wallet_amount') }}</th>
                                    <th scope="col">{{ __('seller/analytics.wallet_balance') }}</th>
                                    <th scope="col">{{ __('seller/analytics.wallet_date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['wallet']['transactions']->reverse()->take(5) as $tx)
                                    <tr>
                                        <td class="small">{{ \App\Models\WalletTransaction::reasonLabel($tx['reason']) }}</td>
                                        <td class="fw-bold text-nowrap {{ $tx['type'] === 'credit' ? 'an-credit' : 'an-debit' }}" dir="ltr">
                                            {{ $tx['type'] === 'credit' ? '+' : '-' }}{{ number_format($tx['amount']) }}
                                        </td>
                                        <td class="text-nowrap">{{ number_format($tx['balance_after']) }}</td>
                                        <td class="small an-muted text-nowrap">{{ $tx['date'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- أفضل المنتجات --}}
    <section>
        <h2 class="an-section"><i class="bi bi-trophy" aria-hidden="true"></i> {{ __('seller/analytics.top_products') }}</h2>
        <div class="an-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table an-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">{{ __('seller/analytics.product') }}</th>
                            <th scope="col">{{ __('seller/analytics.sold') }}</th>
                            <th scope="col">{{ __('seller/analytics.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['top_products'] as $index => $product)
                            <tr>
                                <td class="fw-bold">{{ $index + 1 }}</td>
                                <td translate="no">{{ $product['name'] }}</td>
                                <td>{{ number_format($product['sold_count']) }}</td>
                                <td class="fw-bold text-nowrap">{{ number_format($product['revenue']) }} {{ $currency }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="an-empty">{{ __('seller/analytics.no_sales_window') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (!window.Chart) return;

    const series = @json($data['series']);
    const statuses = @json($data['status_breakdown']);
    const wallet = @json($data['wallet']);
    const labels = {
        sales: @json(__('seller/analytics.legend_sales')),
        net: @json(__('seller/analytics.legend_net')),
        balance: @json(__('seller/analytics.legend_balance')),
        currency: @json($currency),
    };
    const fmt = (n) => new Intl.NumberFormat('en-US').format(n);
    const soft = (hex, alpha) => {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(x => x + x).join('') : h;
        const n = parseInt(full, 16);
        return Number.isNaN(n) ? 'transparent' : `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };
    // ألوان الرسوم من متغيرات الصفحة، فتتبدّل مع الوضع الليلي/النهاري
    const root = document.querySelector('.ts-an');
    const palette = () => {
        const css = getComputedStyle(root);
        const read = (name) => css.getPropertyValue(name).trim();
        return { accent: read('--an-accent'), gold: read('--an-gold'), blue: read('--an-blue'), surface: read('--an-surface'), muted: read('--an-muted'), border: read('--an-border') };
    };
    const statusColors = { pending: '#E0A800', paid: '#17A2B8', processing: '#0F6EBA', shipped: '#8E44AD', delivered: '#0B8A68', cancelled: '#EB5757' };

    Chart.defaults.font.family = 'Tajawal, sans-serif';
    const charts = [];
    const build = () => {
        const c = palette();
        Chart.defaults.color = c.muted;
        Chart.defaults.borderColor = c.border;
        charts.splice(0).forEach(chart => chart.destroy());

        const sales = document.getElementById('salesChart');
        if (sales) {
            charts.push(new Chart(sales, {
                type: 'line',
                data: {
                    labels: series.map(p => p.date.slice(5)),
                    datasets: [
                        { label: labels.sales, data: series.map(p => p.sales), borderColor: c.accent, backgroundColor: soft(c.accent, .12), fill: true, tension: .35, pointRadius: 2 },
                        { label: labels.net, data: series.map(p => p.net_profit), borderColor: c.gold, backgroundColor: soft(c.gold, .12), fill: true, tension: .35, pointRadius: 2 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + fmt(ctx.parsed.y) + ' ' + labels.currency } } },
                    scales: { y: { beginAtZero: true, ticks: { callback: (v) => fmt(v) } }, x: { grid: { display: false } } },
                },
            }));
        }

        const status = document.getElementById('statusChart');
        if (status) {
            const active = statuses.filter(s => s.count > 0);
            charts.push(new Chart(status, {
                type: 'doughnut',
                data: {
                    labels: active.map(s => s.label),
                    datasets: [{ data: active.map(s => s.count), backgroundColor: active.map(s => statusColors[s.status] || '#888'), borderColor: c.surface, borderWidth: 2 }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
            }));
        }

        const walletCanvas = document.getElementById('walletChart');
        if (walletCanvas && wallet.transactions.length) {
            charts.push(new Chart(walletCanvas, {
                type: 'line',
                data: {
                    labels: wallet.transactions.map(t => (t.date || '').slice(0, 10)),
                    datasets: [{ label: labels.balance, data: wallet.transactions.map(t => t.balance_after), borderColor: c.blue, backgroundColor: soft(c.blue, .12), fill: true, stepped: true }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => fmt(ctx.parsed.y) + ' ' + labels.currency } } },
                    scales: { y: { ticks: { callback: (v) => fmt(v) } }, x: { grid: { display: false } } },
                },
            }));
        }
    };

    build();
    document.addEventListener('ts:themechange', build);
})();
</script>
@endpush
