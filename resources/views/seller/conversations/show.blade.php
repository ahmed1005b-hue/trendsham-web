@extends('layouts.app')

@section('title', $conversation->customer->name.' - تريندشام')

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
    .ts-message-content {
        font-size: .95rem;
    }
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
    .ts-empty-conversation {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6B7A72;
        text-align: center;
        padding: 2rem;
    }
    .ts-empty-conversation i { font-size: 3rem; margin-bottom: 1rem; opacity: .4; }
    .ts-reply-form {
        position: sticky;
        bottom: 0;
        background: #FBF7F0;
        padding-top: 1rem;
        padding-bottom: 1rem;
        border-top: 1px solid #ECE4D3;
    }
    .ts-customer-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--ts-emerald);
    }
    .ts-seller-badge {
        font-size: .7rem;
        padding: .15rem .5rem;
        border-radius: 12px;
        background: var(--ts-gold-light);
        color: var(--ts-emerald-dark);
        font-weight: 700;
    }
</style>
@endsection

@section('content')
<div class="container-fluid px-4">

    <!-- رأس المحادثة -->
    <div class="row mb-4">
        <div class="col-12">
            <nav class="navbar ts-header py-3">
                <div class="container d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('seller.conversations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> كل المحادثات</a>
                        <div class="d-flex align-items-center gap-2">
                            @if ($conversation->customer->avatar)
                                <img src="{{ asset('storage/'.$conversation->customer->avatar) }}" class="ts-customer-avatar" alt="{{ $conversation->customer->name }}">
                            @else
                                <span class="ts-customer-avatar d-inline-flex align-items-center justify-content-center bg-light text-success" role="img" aria-label="{{ $conversation->customer->name }}">
                                    <i class="bi bi-person"></i>
                                </span>
                            @endif
                            <div>
                                <span class="fw-bold d-block">{{ $conversation->customer->name }}</span>
                                <span class="ts-seller-badge">العميل</span>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
                </div>
            </nav>
        </div>
    </div>

    <!-- منطقة الرسائل -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="ts-conversation-area" id="conversationArea">
                @forelse ($conversation->messages as $message)
                    <div class="ts-message {{ $message->sender_id === auth()->id() ? 'me' : 'them' }}">
                        @if ($message->sender_id !== auth()->id())
                            <span class="ts-message-sender">{{ $conversation->customer->name }}</span>
                        @else
                            <span class="ts-message-sender">أنت</span>
                        @endif
                        <div class="ts-message-content">{{ $message->body }}</div>
                        <span class="ts-message-time">{{ $message->created_at->format('d M Y, h:i A') }}</span>
                    </div>
                @empty
                    <div class="ts-empty-conversation">
                        <i class="bi bi-chat-square-text"></i>
                        <h4>لا يوجد رسائل بعد</h4>
                        <p class="mb-0">ابدأ المحادثة بإرسال رسالة للعميل.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- نموذج الرد -->
    <div class="row">
        <div class="col-12">
            <div class="ts-reply-form">
                <form action="{{ route('seller.conversations.send', $conversation) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="body" class="form-control" placeholder="اكتب ردك للعميل..." required>
                    <button class="btn btn-primary px-4">إرسال الرد</button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    // تمرير تلقائي إلى أسفل الرسائل عند التحميل
    const conversationArea = document.getElementById('conversationArea');
    if (conversationArea) {
        conversationArea.scrollTop = conversationArea.scrollHeight;
    }
</script>
@endpush
