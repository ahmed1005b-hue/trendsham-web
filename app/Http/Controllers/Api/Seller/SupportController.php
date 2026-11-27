<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Services\SellerSupportTeam;
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
                    // البائع يتحدث مع فريق الدعم، لا مع شخص باسمه
                    'staff_name' => __('فريق الدعم'),
                    'staff_role' => 'support',
                    'last_message_at' => $c->last_message_at?->toISOString(),
                    'last_message' => $c->messages->first()?->body,
                    'unread_count' => $c->messages->where('sender_id', '!=', $sellerUserId)->whereNull('read_at')->count(),
                ];
            }),
        ]);
    }

    public function store(Request $request, SellerSupportTeam $team)
    {
        $vendor = $request->user()->sellerProfile;

        // البائع لا يختار موظفاً: الرسالة تصل لكل فريق الدعم (staff_id من نسخ التطبيق القديمة يُتجاهل)
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $conversation = StaffConversation::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $team->ownerId(),
            'subject' => $validated['subject'],
            'status' => 'open',
        ]);

        StaffMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $vendor->user_id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        $team->notify($conversation, "رسالة دعم جديدة من {$vendor->store_name}", $validated['body']);

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
                // البائع يراسل فريق الدعم، لا يُكشف له اسم موظف بعينه
                'staff_name' => __('فريق الدعم'),
                'staff_role' => 'support',
                'created_at' => $conversation->created_at->toISOString(),
            ],
            'messages' => $conversation->messages->map(function ($m) use ($request) {
                return [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_me' => $m->sender_id === $request->user()->id,
                    'sender_name' => $m->sender_id === $request->user()->id ? $m->sender->name : __('فريق الدعم'),
                    'created_at' => $m->created_at->toISOString(),
                    'read_at' => $m->read_at?->toISOString(),
                ];
            }),
        ]);
    }

    public function sendMessage(Request $request, StaffConversation $conversation, SellerSupportTeam $team)
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

        $team->notify($conversation, "رد جديد من {$vendor->store_name}", $validated['body']);

        return response()->json([
            'message' => 'تم إرسال ردك.',
        ]);
    }
}
