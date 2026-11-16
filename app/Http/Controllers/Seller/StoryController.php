<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Story;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StoryController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    /** قائمة ستوريات البائع (لوحة البائع - واجهة Blade). */
    public function index()
    {
        $vendor = auth()->user()->sellerProfile;
        abort_unless($vendor, 404);

        return view('seller.stories.index', [
            'stories' => $vendor->stories()->latest()->get(),
            'wallet' => $vendor->walletOrCreate(),
            'fee' => PlatformSetting::current()->story_fee,
        ]);
    }

    /** نموذج إنشاء ستوري جديد. */
    public function create()
    {
        $vendor = auth()->user()->sellerProfile;
        abort_unless($vendor, 404);

        return view('seller.stories.create', [
            'wallet' => $vendor->walletOrCreate(),
            'fee' => PlatformSetting::current()->story_fee,
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor?->isApproved(), 403, 'يجب اعتماد متجرك من الإدارة أولاً.');

        $validated = $request->validate([
            'image' => ImageUploadService::validationRules(),
            'caption' => ['nullable', 'string', 'max:150'],
        ], ImageUploadService::validationMessages());

        $fee = PlatformSetting::current()->story_fee;
        $wallet = $vendor->walletOrCreate();

        if ($wallet->balance < $fee) {
            return back()->with('error', __('رصيد محفظتك غير كافٍ لنشر ستوري. الرسم الحالي: :fee ل.س.', ['fee' => number_format($fee / 100)]));
        }

        // نتحقق من الصورة ونخزّنها أولاً - وما نخصم الرسم إلا لو نجح فعلاً.
        $path = $this->imageUploadService->store($request->file('image'), 'stories');
        if ($path === null) {
            return back()->with('error', 'الملف المرفوع ليس صورة صالحة، جرّب صورة أخرى.');
        }

        $wallet->applyTransaction('debit', $fee, 'story_fee', [
            'note' => 'رسم نشر ستوري',
        ]);

        Story::create([
            'vendor_id' => $vendor->id,
            'image' => $path,
            'caption' => $validated['caption'] ?? null,
            'amount_paid' => $fee,
            'expires_at' => now()->addHours(24),
        ]);

        return redirect()->route('seller.stories.index')
            ->with('success', 'تم نشر الستوري، سيظهر على الصفحة الرئيسية لمدة 24 ساعة.');
    }

    public function destroy(Request $request, Story $story)
    {
        Gate::authorize('manage', $story);

        $story->update(['is_active' => false]);

        return redirect()->route('seller.stories.index')->with('success', 'تم إخفاء الستوري.');
    }
}
