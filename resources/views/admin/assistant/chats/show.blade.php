@extends('layouts.app')

@section('title', 'محادثة عميل - لوحة المدير')

@section('content')
@php $area = request()->routeIs('employee.*') ? 'employee' : 'admin'; @endphp
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - محادثة
            {{ $conversation->user ? trim($conversation->user->first_name.' '.$conversation->user->last_name) : 'زائر' }}</span>
        <div class="d-flex gap-2">
            @if ($conversation->status === 'escalated')
                <form action="{{ route($area.'.assistant-chats.close', $conversation) }}" method="POST" data-confirm="إغلاق المحادثة وإرجاع العميل للمساعد الآلي؟">
                    @csrf
                    <button class="btn btn-sm btn-outline-success">إغلاق المحادثة</button>
                </form>
            @endif
            <a href="{{ route($area.'.assistant-chats.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
        </div>
    </div>
</nav>

<div class="container mb-5" style="max-width: 820px">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if ($conversation->user)
        <p class="small text-muted">
            {{ $conversation->user->phone }} @if ($conversation->user->email) · {{ $conversation->user->email }} @endif
        </p>
    @endif

    <div class="card card-body mb-3" style="max-height: 60vh; overflow-y: auto">
        @foreach ($conversation->messages as $m)
            @php
                $isAgent = (bool) ($m->tool_calls['agent'] ?? false);
                $isUser = $m->role === 'user';
            @endphp
            <div class="d-flex mb-2 {{ $isUser ? 'justify-content-start' : 'justify-content-end' }}">
                <div class="p-2 rounded-3 {{ $isUser ? 'bg-light border' : ($isAgent ? 'bg-success-subtle' : 'bg-secondary-subtle') }}" style="max-width: 80%">
                    <div class="small text-muted mb-1">
                        {{ $isUser ? 'العميل' : ($isAgent ? 'الموظف '.($m->tool_calls['agent_name'] ?? '') : 'المساعد الآلي') }}
                        · {{ $m->created_at?->format('Y-m-d H:i') }}
                    </div>
                    <div style="white-space: pre-line">{{ $m->content }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($conversation->status === 'escalated')
        <form action="{{ route($area.'.assistant-chats.reply', $conversation) }}" method="POST" class="d-flex gap-2">
            @csrf
            <input name="message" class="form-control @error('message') is-invalid @enderror" placeholder="اكتب ردك للعميل..." maxlength="2000" autocomplete="off">
            <button class="btn btn-primary text-nowrap">إرسال</button>
        </form>
        @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @else
        <div class="alert alert-secondary">المحادثة مغلقة.</div>
    @endif
</div>
@endsection
