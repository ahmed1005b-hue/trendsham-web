@extends('layouts.app')

@section('title', 'محادثات العملاء - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - محادثات العملاء</span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.assistant-faqs.index') }}" class="btn btn-outline-secondary btn-sm">أجوبة المساعد</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ $status === 'escalated' ? 'active' : '' }}" href="{{ route('admin.assistant-chats.index') }}">بانتظار موظف</a></li>
        <li class="nav-item"><a class="nav-link {{ $status === 'closed' ? 'active' : '' }}" href="{{ route('admin.assistant-chats.index', ['status' => 'closed']) }}">مغلقة</a></li>
    </ul>

    <div class="list-group">
        @forelse ($conversations as $c)
            @php $lastUser = $c->messages->last(); @endphp
            <a href="{{ route('admin.assistant-chats.show', $c) }}" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                    <strong>{{ $c->user ? trim($c->user->first_name.' '.$c->user->last_name) : 'زائر' }}</strong>
                    <small class="text-muted">{{ $c->escalated_at?->diffForHumans() }}</small>
                </div>
                <div class="small text-muted text-truncate">{{ $lastUser?->content }}</div>
            </a>
        @empty
            <div class="text-center text-muted py-5">لا توجد محادثات {{ $status === 'escalated' ? 'بانتظار موظف' : 'مغلقة' }}.</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $conversations->links() }}</div>
</div>
@endsection
