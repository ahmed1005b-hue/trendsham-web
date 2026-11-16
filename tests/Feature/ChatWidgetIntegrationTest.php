<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ChatWidgetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_web_layout_exposes_the_assistant_widget(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="trendsham-chat-widget"', false)
            ->assertSee('id="ts-chat-toggle"', false)
            ->assertSee('id="ts-chat-window"', false)
            ->assertSee('id="ts-chat-form"', false)
            ->assertSee('/js/chat-widget.js?v=', false);
    }

    public function test_widget_api_urls_include_the_application_subdirectory(): void
    {
        $homeUrl = route('home');
        URL::forceRootUrl('http://localhost/trendsham/public');
        URL::forceScheme('http');

        $this->get($homeUrl)
            ->assertOk()
            // مسارات الموقع (بجلسة الموقع) ليتعرّف المساعد على العميل المسجّل
            ->assertSee('data-history-url="http://localhost/trendsham/public/assistant/chat"', false)
            ->assertSee('data-send-url="http://localhost/trendsham/public/assistant/chat/send"', false);
    }

    public function test_widget_mobile_panel_avoids_the_top_strip_and_keeps_messages_scrollable(): void
    {
        $page = $this->get(route('home'))->assertOk()->getContent();
        // تنسيقات الودجت في ملف ثابت يُحمَّل من الصفحة (لا تُضمَّن في كل طلب)
        $this->assertStringContainsString('css/ts-chat.css', $page);
        $html = file_get_contents(public_path('css/ts-chat.css'));

        preg_match('/\.ts-chat\s*\{([^}]+)\}/', $html, $widgetRule);
        $this->assertNotEmpty($widgetRule, 'Expected overlay styles for the assistant widget.');
        $this->assertStringContainsString('position: fixed;', $widgetRule[1]);
        $this->assertStringContainsString('z-index: 1041;', $widgetRule[1]);
        $this->assertStringContainsString('pointer-events: none;', $widgetRule[1]);
        $this->assertStringContainsString('pointer-events: auto;', $this->cssRule($html, '.ts-chat-toggle'));

        $this->assertStringContainsString('top: max(56px, calc(env(safe-area-inset-top, 0px) + 40px))', $html);
        $this->assertStringContainsString('min-height: 0;', $html);
        $this->assertStringContainsString('overflow-y: auto;', $html);

        preg_match('/\.ts-chat-toggle\s*\{([^}]+)\}/', $html, $toggleRule);
        $this->assertNotEmpty($toggleRule, 'Expected base styles for the assistant launcher.');
        $this->assertStringContainsString('transform: none !important;', $toggleRule[1]);
        $this->assertStringContainsString('transition: background-color .18s ease, box-shadow .18s ease !important;', $toggleRule[1]);
        $this->assertStringNotContainsString('translateY', $toggleRule[1] . $this->cssRule($html, '.ts-chat-toggle:hover'));
        $this->assertStringContainsString('transform: none !important;', $this->cssRule($html, '.ts-chat-toggle:hover'));
        $this->assertStringContainsString('transform: none !important;', $this->cssRule($html, '.ts-chat-toggle:focus-visible'));
    }

    private function cssRule(string $html, string $selector): string
    {
        preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]+)\}/', $html, $matches);

        return $matches[1] ?? '';
    }

    public function test_chat_api_keeps_guest_history_scoped_to_the_guest_id_header(): void
    {
        $guestId = 'chat-guest-test-id';

        // قراءة السجل لا تنشئ محادثة
        $this->getJson('/api/v1/chat', ['X-Guest-Id' => $guestId])
            ->assertOk()
            ->assertJsonStructure(['conversation_id', 'messages'])
            ->assertJsonPath('messages', []);
        $this->assertDatabaseCount('chat_conversations', 0);

        $conversation = ChatConversation::create(['guest_session_id' => $guestId, 'status' => 'open']);
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'أهلاً بك',
        ]);

        $this->getJson('/api/v1/chat', ['X-Guest-Id' => $guestId])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('messages.0.content', 'أهلاً بك');

        $this->getJson('/api/v1/chat', ['X-Guest-Id' => 'another-guest'])
            ->assertOk()
            ->assertJsonPath('messages', []);

        $this->getJson('/api/v1/chat')->assertUnprocessable();
    }

    public function test_chat_send_returns_a_non_streaming_reply_and_stores_both_messages(): void
    {
        // المساعد مجاني محلي: لا اتصال خارجي
        Http::fake();
        Http::preventStrayRequests();

        $guestId = 'chat-send-guest-test';

        $this->postJson('/api/v1/chat/send', ['message' => 'أين طلبي؟'], ['X-Guest-Id' => $guestId])
            ->assertOk()
            ->assertJsonPath('reply.role', 'assistant')
            ->assertJsonPath('reply.meta.intent', 'order_status')
            ->assertJsonStructure(['conversation_id', 'status', 'reply' => ['role', 'content', 'meta', 'created_at']]);

        $conversation = ChatConversation::where('guest_session_id', $guestId)->firstOrFail();
        $this->assertSame(
            ['user', 'assistant'],
            $conversation->messages()->pluck('role')->all()
        );
        $this->assertSame('أين طلبي؟', $conversation->messages()->first()->content);
    }
}
