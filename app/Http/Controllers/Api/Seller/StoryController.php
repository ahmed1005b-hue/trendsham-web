<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Story;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        $wallet = $vendor->walletOrCreate();

        $stories = $vendor->stories()->latest()->get();

        return response()->json([
            'fee' => PlatformSetting::current()->story_fee / 100,
            'wallet_balance' => $wallet->balance / 100,
            'stories' => $stories->map(fn ($s) => [
                'id' => $s->id,
                'image' => asset('storage/'.$s->image),
                'caption' => $s->caption,
                'link_url' => $s->link_url,
                'amount_paid' => $s->amount_paid ? $s->amount_paid / 100 : null,
                'is_active' => $s->is_active,
                'is_expired' => $s->isExpired(),
                'expires_at' => $s->expires_at,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor?->isApproved(), 403, 'يجب اعتماد متجرك من الإدارة أولاً.');

        $validated = $request->validate([
            'image' => ImageUploadService::validationRules(),
            'caption' => ['nullable', 'string', 'max:150'],
            'link_url' => Story::linkRules(),
        ], ImageUploadService::validationMessages());

        $fee = PlatformSetting::current()->story_fee;
        $wallet = $vendor->walletOrCreate();

        if ($wallet->balance < $fee) {
            return response()->json([
                'message' => __('رصيد محفظتك غير كافٍ لنشر ستوري. الرسم الحالي: :fee ل.س.', ['fee' => number_format($fee / 100)]),
            ], 422);
        }

        // نتحقق من الصورة ونخزّنها أولاً - وما نخصم الرسم إلا لو نجح فعلياً.
        $path = $this->imageUploadService->store($request->file('image'), 'stories');

        if ($path === null) {
            return response()->json(['message' => 'الملف المرفوع ليس صورة صالحة، جرّب صورة أخرى.'], 422);
        }

        // الفحص والخصم والنشر معاً تحت قفل المحفظة: نشران متزامنان لا يُنزلان الرصيد تحت الصفر
        $story = DB::transaction(function () use ($wallet, $fee, $vendor, $path, $validated) {
            if (! $wallet->debitIfAvailable($fee, 'story_fee', ['note' => 'رسم نشر ستوري'])) {
                return null;
            }

            return Story::create([
                'vendor_id' => $vendor->id,
                'image' => $path,
                'caption' => $validated['caption'] ?? null,
                'link_url' => $validated['link_url'] ?? null,
                'amount_paid' => $fee,
                'expires_at' => now()->addHours(24),
            ]);
        });

        if (! $story) {
            Storage::disk('public')->delete($path);

            return response()->json([
                'message' => __('رصيد محفظتك غير كافٍ لنشر ستوري. الرسم الحالي: :fee ل.س.', ['fee' => number_format($fee / 100)]),
            ], 422);
        }

        return response()->json([
            'message' => 'تم نشر الستوري، رح تظهر بالصفحة الرئيسية لمدة 24 ساعة.',
            'id' => $story->id,
        ], 201);
    }

    public function destroy(Request $request, Story $story)
    {
        Gate::authorize('manage', $story);

        $story->update(['is_active' => false]);

        return response()->json(['message' => 'تم إخفاء الستوري.']);
    }
}
