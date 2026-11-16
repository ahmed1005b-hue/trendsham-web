@extends('layouts.app')

@section('title', 'ملفات البائعين - المحاسبة')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - ملفات البائعين</span>
            <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المحاسبة</a>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.accounting.sellers') }}" method="GET" class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="q" class="form-control"
                            placeholder="ابحث بالاسم أو الهاتف أو البريد أو المتجر..." value="{{ request('q') }}">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="{{ route('admin.accounting.sellers') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>الرصيد الحالي (ل.س)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sellers as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ $s->first_name }} {{ $s->last_name }}</td>
                                <td dir="ltr">{{ $s->phone }}</td>
                                <td class="fw-bold">{{ number_format($s->account_balance ?? 0, 2) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.accounting.seller', $s->id) }}"
                                        class="btn btn-sm btn-outline-success">فتح الملف</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">لا يوجد بائعون.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $sellers->links() }}</div>
    </div>
@endsection