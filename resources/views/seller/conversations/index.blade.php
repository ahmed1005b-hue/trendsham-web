@extends('layouts.app')

@section('title', 'المحادثات - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - المحادثات</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 640px;">
    <div class="list-group">
        @forelse ($conversations as $conversation)
            <a href="{{ route('seller.conversations.show', $conversation) }}" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">{{ $conversation->customer->name }}</span>
                    <small class="text-muted">{{ $conversation->last_message_at?->diffForHumans() }}</small>
                </div>
            </a>
        @empty
            <div class="text-center text-muted py-5">لا يوجد محادثات بعد.</div>
        @endforelse
    </div>
</div>
@endsection
