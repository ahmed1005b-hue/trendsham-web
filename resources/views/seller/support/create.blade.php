@extends('layouts.app')

@section('title', 'رسالة دعم جديدة - تريندشام')

@section('content')
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - رسالة جديدة</span>
            <a href="{{ route('seller.support.index') }}" class="btn btn-outline-secondary btn-sm">العودة للمحادثات</a>
        </div>
    </nav>

    <div class="row mb-4">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">إرسال رسالة إلى فريق الدعم</h5>
                    <form action="{{ route('seller.support.store') }}" method="POST" class="needs-validation" novalidate>
                        @csrf

                        <p class="text-muted small">{{ __('تصل رسالتك لكل فريق الدعم، ويرد عليك أول موظف متاح.') }}</p>

                        <div class="mb-3">
                            <label for="f-subject" class="form-label">الموضوع</label>
                            <input id="f-subject" type="text" name="subject" class="form-control" placeholder="مثال: استفسار حول عمولة البائع" required>
                            <div class="invalid-feedback">يرجى إدخال الموضوع.</div>
                        </div>

                        <div class="mb-3">
                            <label for="f-body" class="form-label">رسالتك</label>
                            <textarea id="f-body" name="body" rows="5" class="form-control" placeholder="اكتب رسالتك هنا..." required></textarea>
                            <div class="invalid-feedback">يرجى إدخال محتوى الرسالة.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary px-4">إرسال الرسالة</button>
                            <a href="{{ route('seller.support.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
