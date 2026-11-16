<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = $request->user()->conversations()->with('vendor')->latest('last_message_at')->get();

        return view('customer.conversations.index', ['conversations' => $conversations]);
    }

    public function startWithVendor(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        $conversation = Conversation::firstOrCreate([
            'customer_id' => $request->user()->id,
            'vendor_id' => $sellerProfile->id,
        ]);

        return redirect()->route('customer.conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        Gate::authorize('asCustomer', $conversation);

        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('customer.conversations.show', [
            'conversation' => $conversation->load('vendor', 'messages.sender'),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        Gate::authorize('asCustomer', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        AppNotification::send(
            $conversation->vendor->user_id,
            'new_message',
            'رسالة جديدة من '.$request->user()->name,
            'افتح المحادثة للرد على الرسالة.',
            route('seller.conversations.show', $conversation)
        );

        return back();
    }
}
