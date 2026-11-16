@extends('layouts.app')

@section('title', 'الإشعارات - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الإشعارات</span>
    </div>
</nav>

<div class="container mb-5" style="max-width: 640px;">
    <div class="list-group">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.read', $notification) }}"
               class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'fw-bold' }}">
                <div class="d-flex justify-content-between">
                    <span translate="no">{{ \App\Support\MessageTranslator::translate($notification->title) }}</span>
                    <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                </div>
                @if ($notification->body)
                    <p class="small text-muted mb-0 mt-1" translate="no">{{ \App\Support\MessageTranslator::translate($notification->body) }}</p>
                @endif
            </a>
        @empty
            <div class="text-center text-muted py-5">لا يوجد إشعارات.</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
</div>
@endsection
