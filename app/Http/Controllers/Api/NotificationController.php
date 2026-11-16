<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return NotificationResource::collection($request->user()->appNotifications()->paginate(20));
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        Gate::authorize('markRead', $notification);

        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $request->user()->appNotifications()->whereNull('read_at')->count(),
        ]);
    }
}
