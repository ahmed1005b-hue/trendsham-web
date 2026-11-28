@extends('layouts.app')

@section('title', 'دعم البائعين - تريندشام')

@section('content')
@php $area = request()->routeIs('employee.*') ? 'employee' : 'admin'; @endphp
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - رسائل البائعين</span>
            <a href="{{ route($area.'.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ $area === 'employee' ? __('لوحة الموظف') : __('لوحة المدير') }}</a>
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
                    <h5 class="card-title">رسائل البائعين</h5>
                    <p class="text-muted small">الرد على استفسارات وشكاوى البائعين عبر المنصة.</p>
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
                        <h5>لا توجد رسائل دعم</h5>
                        <p class="mb-0">عندما يرسل بائع رسالة دعم، ستظهر هنا للرد عليها.</p>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 25%;">البائع</td>
                                <th style="width: 25%;">المتجر</td>
                                <th style="width: 25%;">الموضوع</th>
                                <th style="width: 15%;">آخر تحديث</th>
                                <th style="width: 10%;">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($conversations as $conv)
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $conv->vendor->user->name }}</td>
                                    <td><span translate="no">{{ $conv->vendor->store_name }}</span>@if (isset($openStoreIds[$conv->vendor_id]))<span class="badge text-bg-warning ms-1"><i class="bi bi-unlock" aria-hidden="true"></i> {{ __('سمح بفتح لوحته') }}</span>@endif</td>
                                    <td translate="no">{{ $conv->subject }}</td>
                                    <td>{{ $conv->last_message_at?->diffForHumans() }}</td>
                                    <td>
                                        <span class="badge bg-{{ $conv->status === 'open' ? 'success' : 'danger' }}">{{ $conv->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route($area.'.support.show', $conv) }}" class="btn btn-outline-primary btn-sm px-3">عرض والرد</a>
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
