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
                                                <div class="fw-bold small">{{ $conv->staff->name }}</div>
                                                <div class="text-muted smaller">{{ $conv->staff->role === 'admin' ? 'مدير النظام' : 'موظف دعم' }}</div>
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
