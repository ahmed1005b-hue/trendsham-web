<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function index()
    {
        $stories = Story::with('vendor')->latest()->get();

        return response()->json([
            'data' => $stories->map(fn ($s) => [
                'id' => $s->id,
                'image' => asset('storage/'.$s->image),
                'caption' => $s->caption,
                'link_url' => $s->link_url,
                'is_from_platform' => $s->isFromPlatform(),
                'vendor_name' => $s->vendor?->store_name,
                'amount_paid' => $s->amount_paid ? $s->amount_paid / 100 : null,
                'is_active' => $s->is_active,
                'is_expired' => $s->isExpired(),
                'expires_at' => $s->expires_at,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => ImageUploadService::validationRules(),
            'caption' => ['nullable', 'string', 'max:150'],
            'link_url' => Story::linkRules(),
            'duration_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ], ImageUploadService::validationMessages());

        $path = $this->imageUploadService->store($request->file('image'), 'stories');

        if ($path === null) {
            return response()->json(['message' => 'الملف المرفوع ليس صورة صالحة.'], 422);
        }

        $story = Story::create([
            'vendor_id' => null,
            'image' => $path,
            'caption' => $validated['caption'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'amount_paid' => null,
            'expires_at' => now()->addHours((int) $validated['duration_hours']),
        ]);

        return response()->json(['message' => 'تم نشر ستوري المنصة.', 'id' => $story->id], 201);
    }

    public function destroy(Story $story)
    {
        $story->update(['is_active' => false]);

        return response()->json(['message' => 'تم إخفاء الستوري.']);
    }
}
