<?php

namespace App\Http\Controllers\Seller;

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

        return view('seller.support.index', compact('conversations', 'vendor'));
    }

    public function create(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        return view('seller.support.create', compact('vendor'));
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

        return redirect()->route('seller.support.show', $conversation)
            ->with('success', 'تم إرسال رسالتك إلى الإدارة.');
    }

    public function show(Request $request, StaffConversation $conversation)
    {
        $vendor = $request->user()->sellerProfile;

        Gate::authorize('manage', $conversation);

        $conversation->load('staff', 'messages.sender');
        $conversation->messages()->where('sender_id', '!=', $vendor->user_id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('seller.support.show', compact('conversation', 'vendor'));
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

        return back()->with('success', 'تم إرسال ردك.');
    }
}
