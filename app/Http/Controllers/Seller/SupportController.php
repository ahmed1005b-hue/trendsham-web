<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
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

        return view('seller.support.index', compact('conversations', 'vendor'));
    }

    public function create(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        $admins = User::where('role', 'admin')->orWhere('role', 'employee')->get();

        return view('seller.support.create', compact('vendor', 'admins'));
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $validated = $request->validate([
            'staff_id' => ['required', 'exists:users,id'],
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

        return back()->with('success', 'تم إرسال ردك.');
    }
}
