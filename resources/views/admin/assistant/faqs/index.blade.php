@extends('layouts.app')

@section('title', 'أجوبة المساعد - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - أجوبة المساعد</span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.assistant-faqs.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> سؤال جديد</a>
            <a href="{{ route('admin.assistant-chats.index') }}" class="btn btn-outline-secondary btn-sm">محادثات العملاء</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <p class="text-muted small">
        المساعد يجيب عن أسئلة الشحن والإرجاع والدفع من هذه القائمة فقط، ولا يخترع جواباً من عنده.
        إذا سُئل عن سياسة لا جواب لها هنا، يعرض على العميل التحويل لموظف.
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>السؤال</th><th>الكلمات التي يُعرف بها</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                    @forelse ($faqs as $faq)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $faq->question }}</div>
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($faq->answer, 120) }}</div>
                            </td>
                            <td class="small">
                                @foreach ($faq->keywordList() as $kw)<span class="badge text-bg-light border me-1">{{ $kw }}</span>@endforeach
                            </td>
                            <td>
                                <span class="badge {{ $faq->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $faq->is_active ? 'مفعّل' : 'معطّل' }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.assistant-faqs.edit', $faq) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                                <form action="{{ route('admin.assistant-faqs.destroy', $faq) }}" method="POST" class="d-inline" data-confirm="حذف هذا السؤال؟">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-5">
                            لا توجد أجوبة بعد. ابدأ بأسئلة مثل: مدة التوصيل، سياسة الإرجاع، طرق الدفع.
                            <a href="{{ route('admin.assistant-faqs.create') }}">أضف أول سؤال</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
