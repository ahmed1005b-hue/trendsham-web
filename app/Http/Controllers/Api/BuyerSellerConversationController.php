<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BuyerSellerConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isCustomer() || $user->isSeller(), 403);

        $conversations = $user->isCustomer()
            ? $user->conversations()->with('vendor')->latest('last_message_at')->get()
            : ($user->sellerProfile?->conversations()->with('customer')->latest('last_message_at')->get() ?? collect());

        return response()->json(['data' => $conversations->map(fn (Conversation $conversation) => $this->summary($conversation, $user->id))]);
    }

    public function start(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        abort_unless($request->user()->isCustomer(), 403);

        $conversation = Conversation::firstOrCreate([
            'customer_id' => $request->user()->id,
            'vendor_id' => $sellerProfile->id,
        ]);

        return response()->json(['data' => $this->summary($conversation->load('vendor'), $request->user()->id)], 201);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => $this->summary($conversation->load(['vendor', 'customer', 'messages.sender']), $request->user()->id),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);
        $validated = $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000']]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);
        $conversation->update(['last_message_at' => now()]);

        $recipientId = $request->user()->id === $conversation->customer_id
            ? $conversation->vendor->user_id
            : $conversation->customer_id;
        $recipientLink = $request->user()->id === $conversation->customer_id
            ? route('seller.conversations.show', $conversation)
            : route('customer.conversations.show', $conversation);
        AppNotification::send(
            $recipientId,
            'new_message',
            'لديك رسالة جديدة من '.$request->user()->name,
            'افتح المحادثة للرد على الرسالة.',
            $recipientLink,
        );

        return response()->json([
            'data' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        Gate::authorize('participate', $conversation);
    }

    private function summary(Conversation $conversation, int $userId): array
    {
        return [
            'id' => $conversation->id,
            'store' => $conversation->vendor ? [
                'id' => $conversation->vendor->id,
                'store_name' => $conversation->vendor->store_name,
            ] : null,
            'customer' => $conversation->customer ? [
                'id' => $conversation->customer->id,
                'name' => $conversation->customer->name,
            ] : null,
            'last_message_at' => $conversation->last_message_at,
            'messages' => $conversation->relationLoaded('messages')
                ? $conversation->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'sender_id' => $message->sender_id,
                    'is_mine' => $message->sender_id === $userId,
                    'created_at' => $message->created_at,
                ])
                : [],
        ];
    }
}
