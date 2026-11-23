<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\PushBroadcastController as WebPushBroadcastController;
use App\Http\Controllers\Controller;
use App\Models\PushBroadcast;
use App\Notifications\Push\BroadcastService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** الإشعار الجماعي للعملاء من التطبيق (نفس صفحة الموقع، مرة كل 7 أيام). */
class PushBroadcastController extends Controller
{
    public function __construct(private BroadcastService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'audience' => $this->service->audience(),
            'next_allowed_at' => $this->service->nextAllowedAt()?->toIso8601String(),
            'data' => PushBroadcast::with('creator')->latest('id')->limit(20)->get()->map(fn (PushBroadcast $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'body' => $b->body,
                'link' => $b->link,
                'creator' => $b->creator?->name,
                'scheduled_for' => $b->scheduled_for?->toIso8601String(),
                'sent_at' => $b->sent_at?->toIso8601String(),
                'recipients_count' => $b->recipients_count,
                'pushed_count' => $b->pushed_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(WebPushBroadcastController::rules(), WebPushBroadcastController::messages());

        try {
            $broadcast = $this->service->create($data, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['title' => [$e->getMessage()]]], 422);
        }

        if ($broadcast->scheduled_for->isFuture()) {
            return response()->json(['message' => __('الوقت الآن ليل، سيُرسل الإشعار الساعة 9 صباحاً.'), 'scheduled' => true], 201);
        }

        // الإرسال بعد الرد: لا ينتظر المدير مرور الإرسال على كل العملاء.
        dispatch(fn () => app(BroadcastService::class)->sendDue())->afterResponse();

        return response()->json(['message' => __('سيُرسل الإشعار الآن لكل العملاء.'), 'scheduled' => false], 201);
    }
}
