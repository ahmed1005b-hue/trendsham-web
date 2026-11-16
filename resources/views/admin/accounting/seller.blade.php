@extends('layouts.app')

@section('title', 'ملف البائع - المحاسبة')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - ملف البائع</span>
            <a href="{{ route('admin.accounting.sellers') }}" class="btn btn-outline-secondary btn-sm">كل البائعين</a>
        </div>
    </nav>

    <div class="container mb-5">
        {{-- رأس الملف --}}
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1" style="color:#0F3D2E;">{{ $seller->first_name }} {{ $seller->last_name }}</h5>
                    <div class="text-muted small">المتجر: {{ $storeName ?? 'غير محدد' }} &nbsp;|&nbsp; هاتف: <span
                            dir="ltr">{{ $seller->phone }}</span></div>
                </div>
                <div class="text-center">
                    <div class="small text-muted">الرصيد الحالي</div>
                    <div class="h4 mb-0 fw-bold" style="color:#0F3D2E;">{{ number_format($account->balance ?? 0, 2) }} ل.س
                    </div>
                </div>
            </div>
        </div>

        {{-- الفلاتر --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.accounting.seller', $seller->id) }}" method="GET"
                    class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="f-from" class="form-label small">من</label>
                        <input id="f-from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-to" class="form-label small">إلى</label>
                        <input id="f-to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-type" class="form-label small">نوع القيد</label>
                        <select id="f-type" name="type" class="form-select">
                            <option value="">الكل</option>
                            @foreach ($labels as $k => $v)
                                <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">تطبيق</button>
                        <a href="{{ route('admin.accounting.export', array_merge(request()->only('from', 'to', 'type'), ['seller' => $seller->id])) }}"
                            class="btn btn-outline-secondary">تصدير CSV</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ملخص الفترة --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="small text-muted">مبيعات الفترة</div>
                        <div class="h5 fw-bold mb-0" style="color:#0F3D2E;">
                            {{ number_format($summary['sale']->credit ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="small text-muted">عمولات المنصة</div>
                        <div class="h5 fw-bold mb-0">{{ number_format($summary['commission']->debit ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="small text-muted">مسحوبات الفترة</div>
                        <div class="h5 fw-bold mb-0">{{ number_format($summary['payout']->debit ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- استخراج كشف حساب --}}
        <div class="card mb-4 border-success">
            <div class="card-header bg-white"><b><i class="bi bi-file-earmark-pdf"></i> استخراج كشف حساب PDF</b></div>
            <div class="card-body">
                <form action="{{ route('admin.accounting.statements.store', $seller->id) }}" method="POST"
                    class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label for="f-from-2" class="form-label small">من</label>
                        <input id="f-from-2" type="date" name="from" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="f-to-2" class="form-label small">إلى</label>
                        <input id="f-to-2" type="date" name="to" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success">استخراج الكشف</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- الحركات --}}
        <div class="card mb-4">
            <div class="card-header bg-white"><b>حركات الفترة</b></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>البيان</th>
                            <th>مدين</th>
                            <th>دائن</th>
                            <th>الرصيد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $e)
                            <tr>
                                <td class="small" dir="ltr">{{ $e->posted_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $labels[$e->entry_type] ?? $e->entry_type }}</td>
                                <td class="small">{{ $e->description }}</td>
                                <td>{{ (float) $e->debit > 0 ? number_format($e->debit, 2) : '—' }}</td>
                                <td>{{ (float) $e->credit > 0 ? number_format($e->credit, 2) : '—' }}</td>
                                <td class="fw-bold">{{ number_format($e->balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">لا توجد حركات في هذه الفترة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $entries->appends(request()->only('from', 'to', 'type'))->links() }}</div>

        {{-- الكشوف السابقة --}}
        <div class="card mb-4">
            <div class="card-header bg-white"><b>الكشوف الصادرة سابقاً</b></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>الفترة</th>
                            <th>الرصيد الختامي</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statements as $st)
                            <tr>
                                <td dir="ltr">{{ $st->number }}</td>
                                <td dir="ltr">{{ $st->period_from->toDateString() }} → {{ $st->period_to->toDateString() }}</td>
                                <td>{{ number_format($st->closing_balance, 2) }}</td>
                                <td>
                                    <span class="badge {{ $st->status === 'final' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $st->status === 'final' ? 'نهائي' : 'مستبدل' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.accounting.statements.download', $st->id) }}"
                                        class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download"></i> تنزيل PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">لم يُستخرج أي كشف بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- تسوية يدوية --}}
        <div class="card border-warning">
            <div class="card-header bg-white text-warning-emphasis"><b><i class="bi bi-exclamation-triangle"></i> تسوية
                    يدوية</b></div>
            <div class="card-body">
                <form action="{{ route('admin.accounting.adjust', $seller->id) }}" method="POST" class="row g-2"
                    data-confirm="هل أنت متأكد من تسجيل هذه التسوية؟ سُجّل باسمك وسنّة سبب إلزامية.">
                    @csrf
                    <div class="col-md-2">
                        <label for="f-direction" class="form-label small">الاتجاه</label>
                        <select id="f-direction" name="direction" class="form-select" required>
                            <option value="credit">دائن (زيادة)</option>
                            <option value="debit">مدين (خصم)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="f-amount" class="form-label small">المبلغ (ل.س)</label>
                        <input id="f-amount" type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label for="f-reason" class="form-label small">السبب (إلزامي)</label>
                        <input id="f-reason" type="text" name="reason" class="form-control" minlength="5" maxlength="200" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning w-100">تسجيل التسوية</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection