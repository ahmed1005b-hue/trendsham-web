@extends('layouts.app')

@section('title', __('سجل نشاط الفريق').' - '.__('لوحة المدير'))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('سجل نشاط الفريق') }}</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('لوحة المدير') }}</a>
    </div>
</nav>
<div class="container mb-5">
    <p class="text-muted">{{ __('كل ما فعله الموظفون والمدير في الطلبات والمنتجات والدعم والمرتجعات، الأحدث أولاً.') }}</p>

    <form method="GET" action="{{ route('admin.staff-activity.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="activity-user">{{ __('الشخص') }}</label>
                <select name="user_id" id="activity-user" class="form-select form-select-sm">
                    <option value="">{{ __('الكل') }}</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $member->id)>
                            {{ $member->name }} — {{ $member->role === 'admin' ? __('مدير') : __('موظف') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="activity-group">{{ __('نوع العمل') }}</label>
                <select name="group" id="activity-group" class="form-select form-select-sm">
                    <option value="">{{ __('الكل') }}</option>
                    @foreach ($groups as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['group'] ?? '') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="activity-from">{{ __('من تاريخ') }}</label>
                <input type="date" name="from" id="activity-from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="activity-to">{{ __('إلى تاريخ') }}</label>
                <input type="date" name="to" id="activity-to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">{{ __('عرض') }}</button>
                <a href="{{ route('admin.staff-activity.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('مسح') }}</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('الوقت') }}</th><th>{{ __('الشخص') }}</th><th>{{ __('ما فعله') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr data-activity="{{ $activity->action }}">
                            <td class="text-nowrap small">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-nowrap">
                                {{ $activity->actor_name }}
                                <span class="badge {{ $activity->actor_role === 'admin' ? 'bg-dark' : 'bg-info text-dark' }}">{{ $activity->actor_role === 'admin' ? __('مدير') : __('موظف') }}</span>
                            </td>
                            <td>{{ $activity->description() }}</td>
                            <td class="text-end">
                                @if ($url = $activity->subjectUrl())
                                    <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary">{{ __('فتح') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('لا يوجد نشاط مسجّل بهذه التصفية.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $activities->links() }}</div>
</div>
@endsection
