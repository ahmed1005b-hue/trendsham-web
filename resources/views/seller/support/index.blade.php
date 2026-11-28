@extends('layouts.app')

@section('title', 'الدعم الفني - تريندشام')

@section('content')
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الدعم الفني</span>
            <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
            <a href="{{ route('seller.support.create') }}" class="btn btn-primary btn-sm">رسالة جديدة</a>
        </div>
    </nav>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-{{ $supportGrant ? 'warning' : 'light' }}" id="support-access">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <h5 class="card-title mb-1"><i class="bi bi-person-gear me-1" aria-hidden="true"></i>{{ __('دخول فريق الدعم إلى لوحة متجرك') }}</h5>
                            @if ($supportGrant)
                                <p class="mb-1 fw-bold text-warning-emphasis">{{ __('مسموح منذ :time. يبقى الإذن حتى تُنهيه.', ['time' => $supportGrant->created_at->diffForHumans()]) }}</p>
                            @else
                                <p class="mb-1 text-muted">{{ __('إذا واجهتك مشكلة في متجرك، اسمح لفريق الدعم بفتح لوحتك لحلها. يبقى الإذن حتى تُنهيه أنت.') }}</p>
                            @endif
                            <p class="small text-muted mb-0">{{ __('لا يستطيع الفريق حتى مع الإذن: سحب الأموال، أو رؤية بياناتك البنكية ووثائق الهوية، أو تغيير كلمة السر، أو قراءة محادثات عملائك، أو حذف منتجاتك.') }}</p>
                        </div>
                        @if ($supportGrant)
                            <form action="{{ route('seller.support-access.revoke') }}" method="POST" class="m-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">{{ __('إنهاء الإذن') }}</button>
                            </form>
                        @else
                            <form action="{{ route('seller.support-access.grant') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ __('اسمح لفريق الدعم بالدخول') }}</button>
                            </form>
                        @endif
                    </div>
                    @if ($supportActions->isNotEmpty())
                        <hr>
                        <h6 class="mb-2">{{ __('ما فعله فريق الدعم في متجرك') }}</h6>
                        <ul class="list-unstyled small mb-0">
                            @foreach ($supportActions as $action)
                                <li class="d-flex justify-content-between gap-3 py-1 border-bottom">
                                    <span>{{ $action['description'] }}</span>
                                    <span class="text-muted text-nowrap">{{ $action['at']?->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">محادثات الدعم الخاصة بك</h5>
                    <p class="text-muted small">تواصل مع فريق الدعم والإدارة بخصوص متجرك.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            @if ($conversations->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-headset fs-1 mb-3 opacity-25"></i>
                        <h5>لا توجد محادثات دعم بعد</h5>
                        <p class="mb-0">ابدأ محادثة جديدة لطلب المساعدة من الإدارة.</p>
                        <a href="{{ route('seller.support.create') }}" class="btn btn-primary mt-3">إنشاء محادثة جديدة</a>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 30%;">الموظف</th>
                                <th style="width: 25%;">الموضوع</th>
                                <th style="width: 25%;">آخر رسالة</th>
                                <th style="width: 10%;">الحالة</th>
                                <th style="width: 10%;">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($conversations as $conv)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="bi bi-person text-muted"></i>
                                            </div>
                                            <div class="ms-2">
                                                <div class="fw-bold small">{{ __('فريق الدعم') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td translate="no">{{ $conv->subject }}</td>
                                    <td>
                                        @if ($conv->messages->isNotEmpty())
                                            <div class="fw-bold small">{{ mb_substr($conv->messages->first()->body, 0, 50) }}...</div>
                                            <div class="text-muted smaller">{{ $conv->last_message_at?->diffForHumans() }}</div>
                                        @else
                                            <span class="text-muted">لا توجد رسائل</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $conv->status === 'open' ? 'success' : 'danger' }}">{{ $conv->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('seller.support.show', $conv) }}" class="btn btn-outline-primary btn-sm px-3">عرض</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
