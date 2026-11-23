<?php

namespace App\Http\Controllers\Api\Admin;

use App\Assistant\Models\AssistantFaq;
use App\Http\Controllers\Admin\AssistantFaqController as WebFaqController;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * المساعد الذكي للمدير في التطبيق (نفس صفحات الموقع):
 * محادثات حوّلها العملاء لموظف (رد وإغلاق)، والأسئلة الشائعة التي يجيب منها.
 */
class AssistantController extends Controller
{
    public function chats(Request $request): JsonResponse
    {
        $status = $request->query('status') === 'closed' ? 'closed' : 'escalated';
        $page = ChatConversation::where('status', $status)
            ->whereNotNull('escalated_at')
            ->with(['user', 'messages' => fn ($q) => $q->where('role', 'user')])
            ->latest('escalated_at')
            ->paginate(20);

        return response()->json([
            'status' => $status,
            'data' => $page->getCollection()->map(fn (ChatConversation $c) => [
                'id' => $c->id,
                'customer' => $c->user?->name ?? __('زائر'),
                'last_message' => $c->messages->last()?->content,
                'escalated_at' => $c->escalated_at?->toIso8601String(),
                'status' => $c->status,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function chat(ChatConversation $conversation): JsonResponse
    {
        abort_if($conversation->escalated_at === null, 404);

        return response()->json($this->present($conversation));
    }

    public function reply(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_if($conversation->escalated_at === null, 404);
        if ($conversation->status !== 'escalated') {
            return response()->json(['message' => __('المحادثة مغلقة.')], 409);
        }
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']], ['message.required' => __('اكتب الرد.')]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $data['message'],
            'tool_calls' => ['intent' => 'agent', 'agent' => true, 'agent_name' => $request->user()->first_name],
        ]);
        $conversation->update(['escalated_to' => $request->user()->id]);

        return response()->json(['message' => __('تم إرسال الرد.'), ...$this->present($conversation->fresh())]);
    }

    public function close(ChatConversation $conversation): JsonResponse
    {
        abort_if($conversation->escalated_at === null, 404);
        $conversation->update(['status' => 'closed']);

        return response()->json(['message' => __('تم إغلاق المحادثة؛ العميل يعود للمساعد الآلي.'), ...$this->present($conversation)]);
    }

    public function faqs(): JsonResponse
    {
        return response()->json([
            'data' => AssistantFaq::orderBy('sort_order')->orderBy('id')->get()->map(fn (AssistantFaq $f) => $this->faq($f))->values(),
        ]);
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $faq = AssistantFaq::create(WebFaqController::validated($request));

        return response()->json(['message' => __('تمت إضافة السؤال.'), 'data' => $this->faq($faq)], 201);
    }

    public function updateFaq(Request $request, AssistantFaq $assistantFaq): JsonResponse
    {
        $assistantFaq->update(WebFaqController::validated($request));

        return response()->json(['message' => __('تم حفظ التعديل.'), 'data' => $this->faq($assistantFaq->fresh())]);
    }

    public function destroyFaq(AssistantFaq $assistantFaq): JsonResponse
    {
        $assistantFaq->delete();

        return response()->json(['message' => __('تم حذف السؤال.')]);
    }

    /** @return array<string, mixed> */
    private function present(ChatConversation $conversation): array
    {
        $conversation->load(['user', 'messages']);

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'customer' => $conversation->user?->name ?? __('زائر'),
            'messages' => $conversation->messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'agent' => (bool) data_get($m->tool_calls, 'agent', false),
                'agent_name' => data_get($m->tool_calls, 'agent_name'),
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function faq(AssistantFaq $f): array
    {
        return [
            'id' => $f->id,
            'question' => $f->question,
            'keywords' => $f->keywords,
            'answer' => $f->answer,
            'is_active' => (bool) $f->is_active,
            'sort_order' => (int) $f->sort_order,
        ];
    }
}
