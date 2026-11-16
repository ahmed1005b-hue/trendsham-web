@extends('layouts.app')

@section('title', 'أسئلة المنتجات - تريندشام')

@section('content')
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - أسئلة المنتجات</span>
            <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
        </div>
    </nav>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">أسئلة العملاء على منتجاتك</h5>
                    
                    @forelse ($questions as $question)
                        <div class="ts-question-card mb-3 p-3 border rounded-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">{{ $question->product->name }}</h6>
                                    <small class="text-muted">{{ $question->product->vendor->store_name }}</small>
                                </div>
                                <small class="text-muted">{{ $question->created_at->diffForHumans() }}</small>
                            </div>
                            
                            <div class="mb-2">
                                <strong class="text-muted small d-block">العميل يسأل:</strong>
                                <p class="mb-0 small">{{ $question->question }}</p>
                            </div>

                            @if ($question->answers->isNotEmpty())
                                <div class="mb-2">
                                    <strong class="text-success small d-block">الردود:</strong>
                                    @foreach ($question->answers as $answer)
                                        <div class="small p-2 rounded {{ $answer->is_seller_answer ? 'bg-success bg-opacity-10 border-success border-opacity-25' : 'bg-light' }}">
                                            <span class="fw-bold text-{{ $answer->is_seller_answer ? 'success' : 'muted' }} small">
                                                {{ $answer->is_seller_answer ? 'أنت (البائع)' : $answer->user->name }}
                                            </span>
                                            <span class="ms-2">{{ $answer->answer }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <form action="{{ route('seller.questions.answer', $question) }}" method="POST" class="d-flex gap-2">
                                    @csrf
                                    <input type="text" name="answer" class="form-control" placeholder="اكتب ردك على السؤال..." required maxlength="1000">
                                    <button class="btn btn-success btn-sm">إرسال الرد</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-square-text fs-1 mb-3 opacity-25"></i>
                            <h5>لا توجد أسئلة على منتجاتك بعد</h5>
                            <p class="mb-0">عندما يطرح عميل سؤالاً، سيظهر هنا للرد عليه.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{ $questions->links() }}

</div>

<style>
    .ts-question-card {
        background: #fff;
        border: 1px solid #ECE4D3;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .ts-question-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(11,95,74,.08);
    }
</style>
@endsection
