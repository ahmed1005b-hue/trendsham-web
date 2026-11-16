@extends('layouts.app')

@section('title', 'طلب الموظف قيد المراجعة')

@section('content')
<div class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm border-0 text-center">
        <div class="card-body p-5">
            <i class="bi bi-hourglass-split text-warning" style="font-size:3rem;"></i>
            <h1 class="h4 mt-3">طلبك قيد مراجعة المدير</h1>
            <p class="text-muted">تم تأكيد رقم هاتفك. ستتمكن من تسجيل الدخول إلى صفحة الموظف بعد الموافقة على طلبك.</p>
            <a href="{{ route('login') }}" class="btn btn-primary">العودة إلى تسجيل الدخول</a>
        </div>
    </div>
</div>
@endsection
