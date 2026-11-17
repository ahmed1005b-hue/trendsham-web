<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function index()
    {
        return view('admin.stories.index', [
            'stories' => Story::with('vendor')->latest()->get(),
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
            return back()->with('error', 'الملف المرفوع ليس صورة صالحة.');
        }

        Story::create([
            'vendor_id' => null, // ستوري منصة - مجاني
            'image' => $path,
            'caption' => $validated['caption'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'amount_paid' => null,
            'expires_at' => now()->addHours((int) $validated['duration_hours']),
        ]);

        return back()->with('success', 'تم نشر ستوري المنصة.');
    }

    /**
     * إزالة أي ستوري (سواء منصة أو بائع) - أداة رقابة إدارية، مو استرجاع
     * للرسم المدفوع (البائع دفع مقابل النشر، مو مقابل ضمان بقائها).
     */
    public function destroy(Story $story)
    {
        $story->update(['is_active' => false]);

        return back()->with('success', 'تم إخفاء الستوري.');
    }
}
