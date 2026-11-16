@extends('layouts.app')

@section('title', 'لوحة الموظف')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الموظف</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary btn-sm">تسجيل الخروج</button></form>
    </div>
</nav>
<div class="container mb-5">
    <h1 class="h4 mb-4">مرحبًا، {{ auth()->user()->name }}</h1>
    <div class="row g-3">
        <div class="col-md-6">
            <a href="{{ route('employee.orders.index') }}" class="card card-body h-100 text-decoration-none">
                <h2 class="h5">إدارة الطلبات</h2>
                <p class="text-muted mb-0">عرض الطلبات وتحديث حالتها وأرشفتها مع الاحتفاظ بسجلها.</p>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('employee.products.index') }}" class="card card-body h-100 text-decoration-none">
                <h2 class="h5">إدارة المنتجات</h2>
                <p class="text-muted mb-0">مراجعة المنتجات وتعديل بياناتها أو إخفاؤها من المتجر.</p>
            </a>
        </div>
    </div>
</div>
@endsection
