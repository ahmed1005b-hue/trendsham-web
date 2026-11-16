<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = $request->user()->sellerProfile
            ->conversations()
            ->with('customer')
            ->latest('last_message_at')
            ->get();

        return view('seller.conversations.index', ['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        Gate::authorize('asSeller', $conversation);

        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('seller.conversations.show', [
            'conversation' => $conversation->load('customer', 'messages.sender'),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        Gate::authorize('asSeller', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        AppNotification::send(
            $conversation->customer_id,
            'new_message',
            'رد جديد من '.$conversation->vendor->store_name,
            'افتح المحادثة لقراءة الرد.',
            route('customer.conversations.show', $conversation)
        );

        return back();
    }
}
