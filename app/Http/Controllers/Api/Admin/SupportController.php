<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $conversations = StaffConversation::with('vendor', 'messages')
            ->latest('last_message_at')
            ->get();

        return response()->json([
            'conversations' => $conversations->map(function ($c) use ($request) {
                return [
                    'id' => $c->id,
                    'vendor_id' => $c->vendor_id,
                    'vendor_name' => $c->vendor->user->name,
                    'store_name' => $c->vendor->store_name,
                    'subject' => $c->subject,
                    'status' => $c->status,
                    'last_message_at' => $c->last_message_at?->toISOString(),
                    'last_message' => $c->messages->first()?->body,
                    'unread_count' => $c->messages->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->count(),
                ];
            }),
        ]);
    }

    public function show(Request $request, StaffConversation $conversation)
    {
        $conversation->load('vendor', 'messages.sender');
        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'vendor_name' => $conversation->vendor->user->name,
                'store_name' => $conversation->vendor->store_name,
                'created_at' => $conversation->created_at->toISOString(),
            ],
            'messages' => $conversation->messages->map(function ($m) use ($request) {
                return [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_me' => $m->sender_id === $request->user()->id,
                    'sender_name' => $m->sender->name,
                    'sender_role' => $m->sender->role,
                    'created_at' => $m->created_at->toISOString(),
                    'read_at' => $m->read_at?->toISOString(),
                ];
            }),
        ]);
    }

    public function sendMessage(Request $request, StaffConversation $conversation)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        StaffMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        AppNotification::send(
            $conversation->vendor->user_id,
            'staff_message',
            'رد جديد من الإدارة',
            mb_substr($validated['body'], 0, 120),
            route('seller.support.show', $conversation)
        );

        return response()->json([
            'message' => 'تم إرسال ردك للبائع.',
        ]);
    }

    public function close(Request $request, StaffConversation $conversation)
    {
        $conversation->update(['status' => 'closed']);

        return response()->json(['message' => 'تم إغلاق المحادثة.']);
    }

    public function reopen(Request $request, StaffConversation $conversation)
    {
        $conversation->update(['status' => 'open']);

        return response()->json(['message' => 'تم إعادة فتح المحادثة.']);
    }
}
