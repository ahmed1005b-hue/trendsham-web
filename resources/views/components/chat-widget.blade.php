<div
    id="trendsham-chat-widget"
    class="ts-chat"
    data-locale="{{ app()->getLocale() }}"
    data-seller="{{ auth()->user()?->role === 'seller' && auth()->user()?->sellerProfile ? '1' : '0' }}"
    data-history-url="{{ route('assistant.chat.show') }}"
    data-send-url="{{ route('assistant.chat.send') }}"
    dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
>
    <section
        id="ts-chat-window"
        class="ts-chat-window"
        role="dialog"
        aria-labelledby="ts-chat-title"
        aria-hidden="true"
        hidden
    >
        <header class="ts-chat-header">
            <div class="ts-chat-brand">
                <span class="ts-chat-brand__icon" aria-hidden="true">
                    <i class="bi bi-stars"></i>
                </span>
                <div>
                    <h2 id="ts-chat-title">{{ __('مساعد تريندشام') }}</h2>
                    <p>{{ __('اسأل عن طلباتك أو منتجاتنا') }}</p>
                </div>
            </div>
            <button
                id="ts-chat-close"
                class="ts-chat-close"
                type="button"
                aria-label="{{ __('إغلاق') }}"
                title="{{ __('إغلاق') }}"
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div
            id="ts-chat-messages"
            class="ts-chat-messages"
            role="log"
            aria-live="polite"
            aria-relevant="additions text"
            aria-label="{{ __('رسائل المحادثة') }}"
        ></div>

        <form id="ts-chat-form" class="ts-chat-composer">
            <label class="visually-hidden" for="ts-chat-input">{{ __('اكتب سؤالك هنا...') }}</label>
            <input
                id="ts-chat-input"
                class="ts-chat-input"
                type="text"
                maxlength="2000"
                placeholder="{{ __('اكتب سؤالك هنا...') }}"
                autocomplete="off"
            >
            <button id="ts-chat-send" class="ts-chat-send" type="submit">
                <span>{{ __('إرسال') }}</span>
                <i class="bi bi-send-fill" aria-hidden="true"></i>
            </button>
        </form>
    </section>

    <button
        id="ts-chat-toggle"
        class="ts-chat-toggle"
        type="button"
        aria-label="{{ __('افتح مساعد تريندشام') }}"
        aria-controls="ts-chat-window"
        aria-expanded="false"
        title="{{ __('افتح مساعد تريندشام') }}"
    >
        <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
        <span class="ts-chat-toggle__ping" aria-hidden="true"></span>
    </button>
</div>

<link rel="stylesheet" href="{{ asset('css/ts-chat.css') }}?v={{ @filemtime(public_path('css/ts-chat.css')) }}">

<script src="{{ asset('js/chat-widget.js') }}?v={{ filemtime(public_path('js/chat-widget.js')) }}" defer></script>
