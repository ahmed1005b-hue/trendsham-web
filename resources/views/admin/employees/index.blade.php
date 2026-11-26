@extends('layouts.app')

@section('title', 'طلبات الموظفين - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - طلبات الموظفين</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>
<div class="container mb-5">
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>الاسم</th><th>الهاتف</th><th>البريد</th><th>تاريخ الطلب</th><th></th></tr></thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->name }}</td>
                            <td>{{ $employee->phone }}</td>
                            <td>{{ $employee->email }}</td>
                            <td>{{ $employee->created_at->format('Y-m-d') }}</td>
                            <td class="text-end">
                                <form class="d-inline" method="POST" action="{{ route('admin.employees.approve', $employee) }}">@csrf<button class="btn btn-sm btn-success">موافقة</button></form>
                                <form class="d-inline" method="POST" action="{{ route('admin.employees.reject', $employee) }}">@csrf<button class="btn btn-sm btn-outline-danger">رفض</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد طلبات موظفين معلّقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $employees->links() }}</div>

    <h2 class="h5 mt-5 mb-1">{{ __('أقسام الموظفين') }}</h2>
    <p class="text-muted small">{{ __('اختر لكل موظف الأقسام التي يعمل فيها. لا يرى الموظف غيرها في الموقع ولا في التطبيق.') }}
        <a href="{{ route('admin.staff-activity.index') }}">{{ __('سجل نشاط الفريق') }}</a></p>
    @error('sections') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @forelse ($team as $member)
        <form method="POST" action="{{ route('admin.employees.permissions', $member) }}" class="card card-body mb-2" data-employee-sections="{{ $member->id }}">
            @csrf @method('PUT')
            <div class="d-flex flex-wrap align-items-center gap-3">
                <strong class="me-2">{{ $member->name }}</strong>
                @foreach ($sections as $value => $label)
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="sections[]" value="{{ $value }}" id="section-{{ $member->id }}-{{ $value }}" @checked(in_array($value, $member->employeeSections(), true))>
                        <label class="form-check-label" for="section-{{ $member->id }}-{{ $value }}">{{ __($label) }}</label>
                    </div>
                @endforeach
                <button class="btn btn-sm btn-primary ms-auto">{{ __('حفظ') }}</button>
            </div>
        </form>
    @empty
        <div class="card card-body text-muted">{{ __('لا يوجد موظفون معتمدون بعد.') }}</div>
    @endforelse
</div>
@endsection
