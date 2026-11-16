@extends('layouts.app')

@section('title', 'نقاط العميل - لوحة المدير')

@section('content')
@php($name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: $customer->email)
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - نقاط {{ $name }}</span>
        <a href="{{ route('admin.loyalty.index') }}" class="btn btn-outline-secondary btn-sm">كل الأرصدة</a>
    </div>
</nav>

<div class="container pb-5">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <div class="small text-muted">الرصيد</div>
                <div class="h3 fw-bold">{{ number_format($account->balance) }} <small class="fs-6">نقطة</small></div>
                <div class="small text-muted">{{ __('معلّقة: :pending · دين: :debt · كسب إجمالاً: :earned', ['pending' => number_format($pending), 'debt' => number_format($account->debt), 'earned' => number_format($account->lifetime_earned)]) }}</div>
            </div></div>

            <div class="card"><div class="card-body">
                <h2 class="h6 mb-3">تعديل يدوي</h2>
                <form method="POST" action="{{ route('admin.loyalty.adjust', $customer) }}">
                    @csrf
                    <div class="btn-group w-100 mb-2" role="group" aria-label="نوع التعديل">
                        <input type="radio" class="btn-check" name="direction" id="dAdd" value="add" @checked(old('direction', 'add') === 'add')>
                        <label class="btn btn-outline-success" for="dAdd">إضافة</label>
                        <input type="radio" class="btn-check" name="direction" id="dDeduct" value="deduct" @checked(old('direction') === 'deduct')>
                        <label class="btn btn-outline-danger" for="dDeduct">خصم</label>
                    </div>
                    <label class="form-label small" for="aPoints">عدد النقاط</label>
                    <input class="form-control mb-2" type="number" min="1" id="aPoints" name="points" value="{{ old('points') }}" required>
                    <label class="form-label small" for="aReason">السبب (إلزامي، يظهر للعميل)</label>
                    <input class="form-control mb-3" type="text" maxlength="255" id="aReason" name="reason" value="{{ old('reason') }}" placeholder="مثلاً: تعويض تأخير التوصيل" required>
                    <button class="btn btn-primary w-100">تنفيذ</button>
                </form>
            </div></div>
        </div>

        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <h2 class="h6 mb-3">السجل</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>التاريخ</th><th>الحركة</th><th>النقاط</th><th>بواسطة</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $t)
                                <tr>
                                    <td class="small">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $t->label() }}
                                        @if (in_array($t->type, \App\Loyalty\Models\LoyaltyTransaction::LOT_TYPES, true) && $t->remaining > 0 && $t->expires_at)
                                            <div class="small text-muted">متبقٍّ {{ $t->remaining }} · تنتهي {{ $t->expires_at->format('Y-m-d') }}</div>
                                        @endif
                                    </td>
                                    <td class="fw-bold {{ $t->points >= 0 ? 'text-success' : 'text-danger' }}" dir="ltr">{{ $t->points >= 0 ? '+' : '' }}{{ $t->points }}</td>
                                    <td class="small">{{ $t->admin ? trim($t->admin->first_name.' '.$t->admin->last_name) : 'النظام' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">لا توجد حركات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $transactions->links() }}</div>
            </div></div>
        </div>
    </div>
</div>
@endsection
