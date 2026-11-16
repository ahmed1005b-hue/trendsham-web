<?php

namespace Tests\Feature\Assistant;

use App\Assistant\Models\AssistantFaq;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    private function escalated(array $attrs = []): ChatConversation
    {
        $c = ChatConversation::create($attrs + ['guest_session_id' => 'g-1', 'status' => 'escalated', 'escalated_at' => now()]);
        $c->messages()->create(['role' => 'user', 'content' => 'طلبي وصل مكسور']);

        return $c;
    }

    // ------------------------------------------------------------ الأسئلة الشائعة

    public function test_admin_creates_updates_and_deletes_faq(): void
    {
        $this->admin();

        $this->post(route('admin.assistant-faqs.store'), [
            'question' => 'كم مدة التوصيل؟', 'keywords' => 'توصيل, بيوصل', 'answer' => 'خلال 3 أيام.', 'is_active' => '1',
        ])->assertRedirect(route('admin.assistant-faqs.index'));

        $faq = AssistantFaq::firstOrFail();
        $this->assertTrue($faq->is_active);

        $this->put(route('admin.assistant-faqs.update', $faq), [
            'question' => 'كم مدة التوصيل؟', 'keywords' => 'توصيل', 'answer' => 'خلال يومين.',
        ])->assertRedirect(route('admin.assistant-faqs.index'));
        $this->assertSame('خلال يومين.', $faq->refresh()->answer);
        $this->assertFalse($faq->is_active);   // صندوق غير محدد = معطّل

        $this->get(route('admin.assistant-faqs.index'))->assertOk()->assertSee('كم مدة التوصيل؟');

        $this->delete(route('admin.assistant-faqs.destroy', $faq))->assertRedirect();
        $this->assertDatabaseCount('assistant_faqs', 0);
    }

    public function test_faq_requires_question_keywords_and_answer(): void
    {
        $this->admin();

        $this->post(route('admin.assistant-faqs.store'), [])
            ->assertSessionHasErrors(['question', 'keywords', 'answer']);
    }

    public function test_non_admin_cannot_manage_faqs_or_handoffs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.assistant-faqs.index'))->assertForbidden();
        $this->get(route('admin.assistant-chats.index'))->assertForbidden();
    }

    // ------------------------------------------------------------ المحادثات المحوّلة

    public function test_inbox_lists_escalated_conversations_only(): void
    {
        $this->admin();
        $this->escalated();
        ChatConversation::create(['guest_session_id' => 'g-2', 'status' => 'open'])
            ->messages()->create(['role' => 'user', 'content' => 'محادثة عادية']);

        $this->get(route('admin.assistant-chats.index'))
            ->assertOk()
            ->assertSee('طلبي وصل مكسور')
            ->assertDontSee('محادثة عادية');
    }

    public function test_agent_reply_reaches_the_guest_and_is_marked_as_agent(): void
    {
        $admin = $this->admin();
        $c = $this->escalated();

        $this->get(route('admin.assistant-chats.show', $c))->assertOk()->assertSee('طلبي وصل مكسور');

        $this->post(route('admin.assistant-chats.reply', $c), ['message' => 'آسفين، رح نبدلك ياه'])->assertRedirect();

        $this->assertSame($admin->id, $c->refresh()->escalated_to);

        $this->app['auth']->forgetGuards();
        $messages = $this->withHeader('X-Guest-Id', 'g-1')->getJson('/api/v1/chat')->assertOk()->json('messages');
        $last = end($messages);
        $this->assertSame('آسفين، رح نبدلك ياه', $last['content']);
        $this->assertTrue($last['meta']['agent']);
    }

    public function test_closing_returns_customer_to_the_bot_in_a_new_conversation(): void
    {
        $this->admin();
        $c = $this->escalated();

        $this->post(route('admin.assistant-chats.close', $c))->assertRedirect();
        $this->assertSame('closed', $c->refresh()->status);

        $this->app['auth']->forgetGuards();
        $res = $this->withHeader('X-Guest-Id', 'g-1')->postJson('/api/v1/chat/send', ['message' => 'مرحبا'])->assertOk()->json();

        $this->assertNotSame($c->id, $res['conversation_id']);
        $this->assertSame('greeting', $res['reply']['meta']['intent']);
    }

    public function test_reply_is_validated(): void
    {
        $this->admin();
        $c = $this->escalated();

        $this->post(route('admin.assistant-chats.reply', $c), ['message' => ''])->assertSessionHasErrors('message');
    }

    public function test_empty_sort_order_is_saved_as_zero(): void
    {
        $this->admin();

        $this->post(route('admin.assistant-faqs.store'), [
            'question' => 'س', 'keywords' => 'ك', 'answer' => 'ج', 'sort_order' => '',
        ])->assertRedirect();

        $this->assertSame(0, AssistantFaq::firstOrFail()->sort_order);
    }
}
