@extends('layouts.app')

@section('title', 'البائعون - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - البائعون</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <p class="text-muted small">
        اترك حقل النسبة فارغاً ليعتمد البائع على نسبة العمولة العامة ({{ \App\Models\PlatformSetting::current()->commission_rate }}%،
        <a href="{{ route('admin.settings.edit') }}">الإعدادات</a>). حدّد رقماً فقط للبائعين اللي عندهم اتفاق خاص متفاوَض عليه.
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>المتجر</th><th>صاحب المتجر</th><th>نسبة العمولة الخاصة</th><th></th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($sellers as $seller)
                        <tr>
                            <td>
                                <span class="fw-bold" translate="no">{{ $seller->store_name }}</span>
                                @if ($seller->isSuspended())
                                    <span class="badge text-bg-danger ms-1">موقوف</span>
                                    <div class="small text-muted mt-1">{{ __('السبب:') }} <span translate="no">{{ $seller->suspension_reason }}</span> · {{ __('منذ :date', ['date' => $seller->suspended_at?->format('Y-m-d')]) }}</div>
                                @endif
                                @if ($seller->user?->deletion_requested_at)
                                    <span class="badge text-bg-warning ms-1">طلب حذف الحساب</span>
                                    <div class="small text-muted mt-1">{{ __('منذ :date', ['date' => $seller->user->deletion_requested_at->format('Y-m-d')]) }} · {{ __('سوِّ الرصيد ثم احذف المتجر، أو أوقفه إن تعذّر الحذف.') }}</div>
                                @endif
                            </td>
                            <td>{{ $seller->user->name }}</td>
                            <td>
                                <form action="{{ route('admin.sellers.updateCommission', $seller) }}" method="POST" class="d-flex align-items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="number" name="commission_rate" class="form-control form-control-sm" style="width:90px;"
                                           value="{{ $seller->commission_rate }}" min="0" max="100" placeholder="عام">
                                    <span class="small text-muted">%</span>
                                    <button class="btn btn-sm btn-outline-primary">حفظ</button>
                                </form>
                            </td>
                            <td class="text-end text-muted small">
                                فعلياً: {{ $seller->effective_commission_rate }}%
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($seller->isSuspended())
                                    <form action="{{ route('admin.sellers.unsuspend', $seller) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success">إعادة تفعيل</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#suspend-{{ $seller->id }}" aria-expanded="false">
                                        إيقاف المتجر
                                    </button>
                                @endif
                                <form action="{{ route('admin.sellers.destroy', $seller) }}" method="POST" class="d-inline" data-confirm="حذف المتجر نهائياً؟ يُسمح فقط لمتجر بلا رصيد أو مبيعات أو سحوبات.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                        @unless ($seller->isSuspended())
                            <tr class="collapse" id="suspend-{{ $seller->id }}">
                                <td colspan="5" class="bg-body-tertiary">
                                    <form action="{{ route('admin.sellers.suspend', $seller) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                        @csrf
                                        <label class="small fw-bold" for="reason-{{ $seller->id }}">سبب الإيقاف (يظهر للبائع):</label>
                                        <input id="reason-{{ $seller->id }}" name="reason" class="form-control form-control-sm" style="max-width:420px" maxlength="500" required placeholder="مثال: شكاوى متكررة من العملاء">
                                        <button class="btn btn-sm btn-warning" data-confirm="إيقاف المتجر؟ ستختفي منتجاته عن الزوار، ويبقى رصيده وطلباته محفوظة.">تأكيد الإيقاف</button>
                                    </form>
                                    <div class="small text-muted mt-1">الإيقاف يُخفي المتجر ومنتجاته وعروضه، ويمنع الشراء منه. المحفظة والطلبات والسجلات تبقى، ويمكن إعادة التفعيل في أي وقت.</div>
                                </td>
                            </tr>
                        @endunless
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد بائعون معتمدون بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
