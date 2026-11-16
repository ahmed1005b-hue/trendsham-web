@extends('layouts.app')

@section('title', $conversation->vendor->store_name.' - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ $conversation->vendor->store_name }}</span>
        <a href="{{ route('customer.conversations.index') }}" class="btn btn-outline-secondary btn-sm">كل المحادثات</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 640px;">
    <div class="card mb-3">
        <div class="card-body" style="max-height: 420px; overflow-y: auto;">
            @forelse ($conversation->messages as $message)
                <div class="mb-3 d-flex {{ $message->sender_id === auth()->id() ? 'justify-content-start' : 'justify-content-end' }}">
                    <div class="p-2 rounded {{ $message->sender_id === auth()->id() ? 'bg-light' : 'text-white' }}"
                         style="max-width: 75%; {{ $message->sender_id !== auth()->id() ? 'background: var(--ts-emerald);' : '' }}">
                        <div class="small">{{ $message->body }}</div>
                        <div class="small opacity-75 mt-1">{{ $message->created_at->format('H:i') }}</div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center">ابدأ المحادثة بإرسال أول رسالة.</p>
            @endforelse
        </div>
    </div>

    <form action="{{ route('customer.conversations.send', $conversation) }}" method="POST" class="d-flex gap-2">
        @csrf
        <input type="text" name="body" class="form-control" placeholder="اكتب رسالتك..." required>
        <button class="btn btn-primary">إرسال</button>
    </form>
</div>
@endsection
