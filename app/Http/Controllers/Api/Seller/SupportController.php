<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\SupportController as WebSupportController;
use App\Models\AppNotification;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        $conversations = $vendor->staffConversations()
            ->with('staff', 'messages')
            ->latest('last_message_at')
            ->get();
        $sellerUserId = $request->user()->id;

        return response()->json([
            'conversations' => $conversations->map(function ($c) use ($sellerUserId) {
                return [
                    'id' => $c->id,
                    'subject' => $c->subject,
                    'status' => $c->status,
                    'staff_name' => $c->staff->name,
                    'staff_role' => $c->staff->role,
                    'last_message_at' => $c->last_message_at?->toISOString(),
                    'last_message' => $c->messages->first()?->body,
                    'unread_count' => $c->messages->where('sender_id', '!=', $sellerUserId)->whereNull('read_at')->count(),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', WebSupportController::staffRule()],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $staffUser = User::findOrFail($validated['staff_id']);

        $conversation = StaffConversation::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $staffUser->id,
            'subject' => $validated['subject'],
            'status' => 'open',
        ]);

        StaffMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $vendor->user_id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        AppNotification::send(
            $staffUser->id,
            'staff_message',
            "رسالة دعم جديدة من {$vendor->store_name}",
            mb_substr($validated['body'], 0, 120),
            route('admin.support.show', $conversation)
        );

        return response()->json([
            'message' => 'تم إرسال رسالتك إلى الإدارة.',
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function show(Request $request, StaffConversation $conversation)
    {
        $vendor = $request->user()->sellerProfile;

        Gate::authorize('manage', $conversation);

        $conversation->load('staff', 'messages.sender');
        $conversation->messages()->where('sender_id', '!=', $vendor->user_id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'staff_name' => $conversation->staff->name,
                'staff_role' => $conversation->staff->role,
                'created_at' => $conversation->created_at->toISOString(),
            ],
            'messages' => $conversation->messages->map(function ($m) use ($request) {
                return [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_me' => $m->sender_id === $request->user()->id,
                    'sender_name' => $m->sender->name,
                    'created_at' => $m->created_at->toISOString(),
                    'read_at' => $m->read_at?->toISOString(),
                ];
            }),
        ]);
    }

    public function sendMessage(Request $request, StaffConversation $conversation)
    {
        $vendor = $request->user()->sellerProfile;

        Gate::authorize('manage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        StaffMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $vendor->user_id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        AppNotification::send(
            $conversation->staff_id,
            'staff_message',
            "رد جديد من {$vendor->store_name}",
            mb_substr($validated['body'], 0, 120),
            route('admin.support.show', $conversation)
        );

        return response()->json([
            'message' => 'تم إرسال ردك.',
        ]);
    }
}
