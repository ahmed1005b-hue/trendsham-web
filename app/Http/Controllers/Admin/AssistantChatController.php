<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use Illuminate\Http\Request;

/** محادثات المساعد التي حوّلها العملاء لموظف */
class AssistantChatController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status') === 'closed' ? 'closed' : 'escalated';

        $conversations = ChatConversation::where('status', $status)
            ->whereNotNull('escalated_at')
            ->with(['user', 'messages' => fn ($q) => $q->where('role', 'user')])
            ->latest('escalated_at')
            ->paginate(20)->withQueryString();

        return view('admin.assistant.chats.index', compact('conversations', 'status'));
    }

    public function show(ChatConversation $conversation)
    {
        abort_if($conversation->escalated_at === null, 404);

        return view('admin.assistant.chats.show', [
            'conversation' => $conversation->load(['user', 'messages']),
        ]);
    }

    public function reply(Request $request, ChatConversation $conversation)
    {
        abort_unless($conversation->status === 'escalated', 409, 'المحادثة مغلقة.');
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']], ['message.required' => 'اكتب الرد.']);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $data['message'],
            'tool_calls' => ['intent' => 'agent', 'agent' => true, 'agent_name' => $request->user()->first_name],
        ]);
        $conversation->update(['escalated_to' => $request->user()->id]);

        return back()->with('success', 'تم إرسال الرد.');
    }

    public function close(ChatConversation $conversation)
    {
        $conversation->update(['status' => 'closed']);

        return redirect()->route('admin.assistant-chats.index')->with('success', 'تم إغلاق المحادثة؛ العميل يعود للمساعد الآلي.');
    }
}
