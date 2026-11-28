@extends('layouts.app')

@section('title', $conversation->subject.' - تريندشام')

@section('styles')
<style>
    .ts-conversation-area {
        height: 450px;
        overflow-y: auto;
        background: #FBF7F0;
        border-radius: 14px;
        border: 1px solid #ECE4D3;
        padding: 1rem;
        display: flex;
        flex-direction: column-reverse;
    }
    .ts-message {
        max-width: 75%;
        padding: .75rem 1rem;
        border-radius: 12px;
        margin-bottom: .75rem;
        position: relative;
        line-height: 1.5;
        word-wrap: break-word;
    }
    .ts-message.me {
        align-self: flex-end;
        background: var(--ts-emerald);
        color: #fff;
        border-bottom-right-radius: 2px;
    }
    .ts-message.them {
        align-self: flex-start;
        background: #fff;
        color: #1F2A24;
        border: 1px solid #ECE4D3;
        border-bottom-left-radius: 2px;
    }
    .ts-message-content { font-size: .95rem; }
    .ts-message-time {
        font-size: .7rem;
        opacity: .7;
        margin-top: .3rem;
        display: block;
    }
    .ts-message.me .ts-message-time { color: rgba(255,255,255,.7); }
    .ts-message.them .ts-message-time { color: #6B7A72; }
    .ts-message-sender {
        font-size: .7rem;
        font-weight: 700;
        margin-bottom: .3rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .ts-message.me .ts-message-sender { color: var(--ts-gold-light); }
    .ts-message.them .ts-message-sender { color: var(--ts-emerald-dark); }
    .ts-reply-form {
        position: sticky;
        bottom: 0;
        background: #FBF7F0;
        padding-top: 1rem;
        padding-bottom: 1rem;
        border-top: 1px solid #ECE4D3;
    }
</style>
@endsection

@section('content')
@php $area = request()->routeIs('employee.*') ? 'employee' : 'admin'; @endphp
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route($area.'.support.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> جميع الرسائل</a>
                <span class="badge bg-{{ $conversation->status === 'open' ? 'success' : 'danger' }}">{{ $conversation->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span>
            </div>
            <a href="{{ route($area.'.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ $area === 'employee' ? __('لوحة الموظف') : __('لوحة المدير') }}</a>
        </div>
    </nav>

    <!-- معلومات البائع -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">{{ $conversation->subject }}</h5>
                            <p class="text-muted small mb-0">
                                من: <strong>{{ $conversation->vendor->user->name }}</strong><br>
                                متجر: <span translate="no">{{ $conversation->vendor->store_name }}</span>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            @if($conversation->status === 'open')
                                <form action="{{ route($area.'.support.close', $conversation) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-outline-secondary btn-sm">إغلاق المحادثة</button>
                                </form>
                            @else
                                <form action="{{ route($area.'.support.reopen', $conversation) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-outline-secondary btn-sm">إعادة الفتح</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إذن البائع بفتح لوحة متجره -->
    <div class="row mb-4">
        <div class="col-12">
            @if ($storeAccess)
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                    <span>
                        <i class="bi bi-unlock me-1" aria-hidden="true"></i>
                        {{ __('سمح البائع لفريق الدعم بفتح لوحة متجره منذ :time. كل ما تفعله داخلها يُسجَّل باسمك ويراه البائع.', ['time' => $storeAccess->created_at->diffForHumans()]) }}
                    </span>
                    <form action="{{ route($area.'.support-access.enter', $conversation->vendor) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-dark btn-sm">{{ __('فتح لوحة المتجر') }}</button>
                    </form>
                </div>
            @else
                <div class="alert alert-light border mb-0 small">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i>
                    {{ __('لفتح لوحة هذا المتجر يجب أن يسمح البائع بذلك من صفحة الدعم في لوحته.') }}
                </div>
            @endif
        </div>
    </div>

    <!-- منطقة الرسائل -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="ts-conversation-area" id="conversationArea">
                @forelse ($conversation->messages as $message)
                    <div class="ts-message {{ $message->sender_id === auth()->id() ? 'me' : 'them' }}">
                        @if ($message->sender_id !== auth()->id())
                            <span class="ts-message-sender">{{ $message->sender->name }} (بائع)</span>
                        @else
                            <span class="ts-message-sender">أنت (الإدارة)</span>
                        @endif
                        <div class="ts-message-content">{{ $message->body }}</div>
                        <span class="ts-message-time">{{ $message->created_at->format('d M Y, h:i A') }}</span>
                    </div>
                @empty
                    <div class="text-center p-4 text-muted">
                        لا توجد رسائل بعد.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- نموذج الرد -->
    <div class="row">
        <div class="col-12">
            <div class="ts-reply-form">
                <form action="{{ route($area.'.support.message', $conversation) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="body" class="form-control" placeholder="اكتب ردك للبائع..." required>
                    <button class="btn btn-primary px-4">إرسال الرد</button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    const conversationArea = document.getElementById('conversationArea');
    if (conversationArea) {
        conversationArea.scrollTop = conversationArea.scrollHeight;
    }
</script>
@endpush
