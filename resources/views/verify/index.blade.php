@extends('layouts.app')

@section('title', 'التحقق من مستند - تريندشام')

@section('content')
<div class="container" style="max-inline-size: 720px">
    <div class="text-center mb-4">
        <div class="display-6 text-success mb-2"><i class="bi bi-shield-check"></i></div>
        <h1 class="h3">التحقق من مستند تريندشام</h1>
        <p class="text-muted mb-0">كل فاتورة أو كشف يصدر من تريندشام يحمل رمز QR ورمزاً مثل <span dir="ltr" class="fw-semibold">TS-7K2M-9QXA-4BCD</span>. تأكد هنا أنه أصلي ولم يُعدَّل.</p>
    </div>

    @isset($fileResult)
        @if ($fileResult === 'match')
            <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
                <i class="bi bi-patch-check-fill fs-4"></i>
                <div>
                    <strong>الملف مطابق تماماً للمستند الأصلي.</strong><br>
                    {{ __(':type رقم :number — رمز التحقق', ['type' => $matched->typeLabel(), 'number' => $matched->summary['number'] ?? '']) }}
                    <a href="{{ route('verify.show', $matched->code) }}" dir="ltr" class="fw-semibold">{{ $matched->displayCode() }}</a>
                </div>
            </div>
        @else
            <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                <div>
                    <strong>هذا الملف لا يطابق أي مستند أصلي صادر من تريندشام.</strong><br>
                    قد يكون معدَّلاً بعد إصداره. اطلب من صاحبه النسخة الأصلية، أو تحقق برمز QR المطبوع عليه.
                </div>
            </div>
        @endif
    @endisset

    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-keyboard"></i> اكتب رمز التحقق</h2>
        <form method="GET" action="{{ route('verify.index') }}" class="d-flex flex-column flex-sm-row gap-2">
            <input name="code" value="{{ old('code') }}" required dir="ltr" autocomplete="off" placeholder="TS-XXXX-XXXX-XXXX"
                   class="form-control text-center fw-semibold @error('code') is-invalid @enderror" aria-label="رمز التحقق">
            <button class="btn btn-success text-nowrap" type="submit">تحقق</button>
        </form>
        @error('code')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div></div>

    <div class="card"><div class="card-body">
        <h2 class="h6 mb-1"><i class="bi bi-file-earmark-pdf"></i> افحص ملف PDF</h2>
        <p class="small text-muted">نقارن الملف بالأصل المحفوظ لدينا؛ أي تعديل ولو حرف واحد يظهر.</p>
        <form method="POST" action="{{ route('verify.file') }}" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
            @csrf
            <input type="file" name="file" accept="application/pdf,.pdf" required class="form-control @error('file') is-invalid @enderror" aria-label="ملف PDF">
            <button class="btn btn-outline-success text-nowrap" type="submit">افحص الملف</button>
        </form>
        @error('file')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div></div>
</div>
@endsection
