@extends('layouts.app')

@section('title', 'شركات الشحن - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - شركات الشحن</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">إضافة شركة شحن</h2>
                    <form action="{{ route('admin.shippingCompanies.store') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label for="f-name" class="form-label small">اسم الشركة</label>
                            <input id="f-name" type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="f-phone" class="form-label small">رقم الهاتف (اختياري)</label>
                            <input id="f-phone" type="text" name="phone" class="form-control">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">إضافة</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>الاسم</th><th>الهاتف</th><th>مفعّلة</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($companies as $company)
                                <tr>
                                    <td class="fw-bold">{{ $company->name }}</td>
                                    <td>{{ $company->phone ?? '-' }}</td>
                                    <td>
                                        <form action="{{ route('admin.shippingCompanies.toggle', $company) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-sm {{ $company->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                                {{ $company->is_active ? 'مفعّلة' : 'معطّلة' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.shippingCompanies.destroy', $company) }}" method="POST" data-confirm="حذف الشركة؟">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">لا يوجد شركات شحن مضافة بعد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
