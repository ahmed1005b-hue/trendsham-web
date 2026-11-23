<?php

namespace App\Http\Controllers\Api\Admin;

use App\Coupons\CampaignReview;
use App\Coupons\CouponPresenter;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** مراجعة كوبونات البائعين في التطبيق (نفس صفحة «كوبونات البائعين» في الموقع). */
class SellerCouponController extends Controller
{
    public function __construct(private readonly CampaignReview $review) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'store' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(CouponCampaign::STATUSES))],
        ]);

        $page = CouponCampaign::where('funder', 'seller')
            ->with(['seller:id,store_name', 'giftProduct:id,name'])
            ->when($filters['store'] ?? null, fn ($q, $id) => $q->where('seller_profile_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("status = 'pending_review' DESC")->latest('id')
            ->paginate(30);

        return response()->json([
            'pending_count' => CouponCampaign::where('funder', 'seller')->where('status', 'pending_review')->count(),
            'statuses' => collect(CouponCampaign::STATUSES)->map(fn ($label, $key) => ['value' => $key, 'label' => __($label)])->values(),
            'data' => $page->getCollection()->map(fn (CouponCampaign $c) => $this->present($c))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function approve(Request $request, CouponCampaign $campaign): JsonResponse
    {
        return $this->run($campaign, fn () => $this->review->approve($campaign, $request->user()), __('تمت الموافقة، والكوبون يعمل الآن.'));
    }

    public function reject(Request $request, CouponCampaign $campaign): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('اكتب سبب الرفض ليصل للبائع.')]);

        return $this->run($campaign, fn () => $this->review->reject($campaign, $request->user(), $data['reason']), __('رُفض الكوبون وأُبلغ البائع.'));
    }

    public function suspend(Request $request, CouponCampaign $campaign): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('اكتب سبب الإيقاف ليصل للبائع.')]);

        return $this->run($campaign, fn () => $this->review->suspend($campaign, $request->user(), $data['reason']), __('أُوقف الكوبون وأُبلغ البائع.'));
    }

    private function run(CouponCampaign $campaign, callable $action, string $message): JsonResponse
    {
        abort_unless($campaign->funder === 'seller', 404);

        try {
            $action();
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message, 'data' => $this->present($campaign->fresh(['seller', 'giftProduct']))]);
    }

    /** @return array<string, mixed> */
    private function present(CouponCampaign $c): array
    {
        return CouponPresenter::campaign($c) + [
            'status_label' => __(CouponCampaign::STATUSES[$c->status] ?? $c->status),
            'gift_product' => $c->giftProduct?->name,
            'review_note' => $c->review_note,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
