<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->appNotifications()->paginate(20);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        Gate::authorize('markRead', $notification);

        $notification->markAsRead();

        return redirect($notification->link ?? route('notifications.index'));
    }
}
