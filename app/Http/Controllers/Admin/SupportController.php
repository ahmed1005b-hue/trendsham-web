<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\SellerSupportGrant;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Services\SellerSupportAccess;
use App\Services\StaffActivityLog;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $conversations = StaffConversation::with('vendor', 'messages')
            ->latest('last_message_at')
            ->get();
        // المتاجر التي سمح أصحابها لفريق الدعم بفتح لوحاتها الآن
        $openStoreIds = SellerSupportGrant::query()->active()->pluck('seller_profile_id')->flip();

        return view('admin.support.index', compact('conversations', 'openStoreIds'));
    }

    public function show(Request $request, StaffConversation $conversation, SellerSupportAccess $access)
    {
        $conversation->load('vendor', 'messages.sender');
        // رسائل البائع فقط: ردود المدير والموظفين تبقى «غير مقروءة» حتى يفتحها البائع
        $conversation->messages()->where('sender_id', $conversation->vendor->user_id)->whereNull('read_at')->update(['read_at' => now()]);
        $storeAccess = $access->activeGrant($conversation->vendor);

        return view('admin.support.show', compact('conversation', 'storeAccess'));
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
        app(StaffActivityLog::class)->support($request->user(), 'support.reply', $conversation);

        AppNotification::send(
            $conversation->vendor->user_id,
            'staff_message',
            'رد جديد من الإدارة',
            mb_substr($validated['body'], 0, 120),
            route('seller.support.show', $conversation)
        );

        return back()->with('success', 'تم إرسال ردك للبائع.');
    }

    public function close(Request $request, StaffConversation $conversation)
    {
        $conversation->update(['status' => 'closed']);
        app(StaffActivityLog::class)->support($request->user(), 'support.close', $conversation);

        return back()->with('success', 'تم إغلاق المحادثة.');
    }

    public function reopen(Request $request, StaffConversation $conversation)
    {
        $conversation->update(['status' => 'open']);
        app(StaffActivityLog::class)->support($request->user(), 'support.reopen', $conversation);

        return back()->with('success', 'تم إعادة فتح المحادثة.');
    }
}
