@extends('layouts.app')

@section('title', 'المحاسبة - لوحة المدير')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - المحاسبة</span>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.accounting.dashboard') }}" method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="f-from" class="form-label small">من تاريخ</label>
                        <input id="f-from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label for="f-to" class="form-label small">إلى تاريخ</label>
                        <input id="f-to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">تحديث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                    'المبيعات' => $totals['sales'],
                    'عمولات المنصة' => $totals['commissions'],
                    'الاسترجاعات' => $totals['refunds'],
                    'المسحوبات' => $totals['payouts'],
                    'رسوم العروض' => $totals['showcase'],
                ] as $label => $value)
                <div class="col-md-4 col-lg-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="h5 mb-0 fw-bold" style="color:#0F3D2E;">{{ number_format($value, 2) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-md-6 col-lg-4">
                <div class="card text-center h-100 border-success">
                    <div class="card-body">
                        <div class="small text-muted">إجمالي المستحق للبائعين (كل الفترات)</div>
                        <div class="h4 mb-0 fw-bold" style="color:#0F3D2E;">
                            {{ number_format($totals['owed_to_sellers'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><b>ملخص القيود حسب النوع</b></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>عدد القيود</th>
                            <th>مدين</th>
                            <th>دائن</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byType as $type => $row)
                            <tr>
                                <td>{{ $row->entry_type }}</td>
                                <td>{{ $row->n }}</td>
                                <td>{{ number_format($row->debit, 2) }}</td>
                                <td>{{ number_format($row->credit, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">لا توجد قيود في هذه الفترة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="{{ route('admin.accounting.sellers') }}" class="btn btn-outline-success">ملفات البائعين <i
                    class="bi bi-arrow-left"></i></a>
        </div>
    </div>
@endsection