@extends('layouts.app')

@section('title', ($faq->exists ? 'تعديل سؤال' : 'سؤال جديد').' - أجوبة المساعد')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ $faq->exists ? 'تعديل سؤال' : 'سؤال جديد' }}</span>
        <a href="{{ route('admin.assistant-faqs.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 760px">
    <form method="POST" action="{{ $faq->exists ? route('admin.assistant-faqs.update', $faq) : route('admin.assistant-faqs.store') }}" class="card card-body">
        @csrf
        @if ($faq->exists) @method('PUT') @endif

        <div class="mb-3">
            <label class="form-label fw-bold" for="question">السؤال</label>
            <input id="question" name="question" class="form-control @error('question') is-invalid @enderror"
                   value="{{ old('question', $faq->question) }}" placeholder="مثال: كم مدة التوصيل؟" maxlength="255">
            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold" for="keywords">الكلمات التي يُعرف بها السؤال</label>
            <input id="keywords" name="keywords" class="form-control @error('keywords') is-invalid @enderror"
                   value="{{ old('keywords', $faq->keywords) }}" placeholder="توصيل, بيوصل, مدة الشحن, كم يوم">
            <div class="form-text">افصل بينها بفاصلة. اكتب الكلمات كما يكتبها العملاء بالعامية؛ العبارات الأطول أدق.</div>
            @error('keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold" for="answer">الجواب</label>
            <textarea id="answer" name="answer" rows="5" class="form-control @error('answer') is-invalid @enderror" maxlength="3000">{{ old('answer', $faq->answer) }}</textarea>
            @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3 align-items-center mb-3">
            <div class="col-auto">
                <label class="form-label mb-0" for="sort_order">الترتيب</label>
                <input id="sort_order" type="number" min="0" name="sort_order" class="form-control" style="width: 110px" value="{{ old('sort_order', $faq->sort_order ?? 0) }}">
            </div>
            <div class="col-auto form-check mt-4">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $faq->is_active))>
                <label for="is_active" class="form-check-label">مفعّل</label>
            </div>
        </div>

        <button class="btn btn-primary">حفظ</button>
    </form>
</div>
@endsection
