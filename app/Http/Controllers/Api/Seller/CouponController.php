<?php

namespace App\Http\Controllers\Api\Seller;

use App\Coupons\CouponPresenter;
use App\Coupons\FollowerIssuer;
use App\Coupons\Models\CouponCampaign;
use App\Coupons\SellerCampaignWriter;
use App\Coupons\SellerCouponStats;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** كوبونات البائع في التطبيق: نفس قواعد لوحة البائع في الموقع (SellerCampaignWriter). */
class CouponController extends Controller
{
    public function __construct(private readonly SellerCampaignWriter $writer) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->user()->sellerProfile;
        $page = CouponCampaign::ofSeller($seller->id)->with(['seller', 'targets'])->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (CouponCampaign $c) => $this->present($c))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'block_reason' => $this->blockReason($seller),
            'review_above_percent' => CouponCampaign::REVIEW_ABOVE_PERCENT,
            'max_percent' => SellerCampaignWriter::MAX_PERCENT,
            'followers_count' => $seller->followers()->count(),
            'products' => Product::where('vendor_id', $seller->id)->orderBy('name')->withCount('variants')
                ->get(['id', 'name', 'price'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price / 100, 'has_variants' => $p->variants_count > 0])
                ->values(),
        ]);
    }

    public function show(Request $request, CouponCampaign $campaign, SellerCouponStats $stats): JsonResponse
    {
        abort_unless(Gate::allows('manage', $campaign), 404);
        $campaign->load(['seller', 'targets']);
        $numbers = $stats->for($campaign);

        return response()->json([
            'data' => $this->present($campaign),
            'stats' => [
                'issued' => $numbers['issued'],
                'redeemed' => $numbers['redeemed'],
                'discount_total' => $numbers['discount_total'] / 100,
                'sales_total' => $numbers['sales_total'] / 100,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $seller = $request->user()->sellerProfile;
        if ($reason = $this->blockReason($seller)) {
            return response()->json(['message' => $reason], 403);
        }

        $data = $request->validate(SellerCampaignWriter::rules($seller), SellerCampaignWriter::messages());
        $campaign = $this->writer->create($data, $seller, $request->user());

        if ($campaign->status === 'active') {
            app(FollowerIssuer::class)->activate($campaign);
        }

        return response()->json([
            'message' => $campaign->status === 'pending_review'
                ? __('حُفظ الكوبون، وهو بانتظار موافقة الإدارة لأن الخصم أكثر من :p%.', ['p' => CouponCampaign::REVIEW_ABOVE_PERCENT])
                : __('الكوبون يعمل الآن ويظهر في صفحة متجرك.'),
            'data' => $this->present($campaign->fresh(['seller', 'targets'])),
        ], 201);
    }

    public function update(Request $request, CouponCampaign $campaign): JsonResponse
    {
        abort_unless(Gate::allows('manage', $campaign), 404);
        if (in_array($campaign->status, ['suspended', 'rejected'], true)) {
            return response()->json(['message' => __('لا يمكن تعديل كوبون أوقفته أو رفضته الإدارة.')], 403);
        }

        $data = $campaign->isLocked()
            ? $request->validate([
                'ends_at' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addYear()->toDateString()],
                'total_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            ], SellerCampaignWriter::messages())
            : $request->validate(SellerCampaignWriter::rules($request->user()->sellerProfile), SellerCampaignWriter::messages());

        $campaign = $this->writer->update($campaign, $data);

        return response()->json([
            'message' => __('حُفظت التعديلات.'),
            'data' => $this->present($campaign->fresh(['seller', 'targets'])),
        ]);
    }

    /** البائع يوقف ويشغّل كوبونه فقط؛ ما أوقفته الإدارة أو ينتظر مراجعتها ليس بيده. */
    public function toggle(CouponCampaign $campaign): JsonResponse
    {
        abort_unless(Gate::allows('manage', $campaign), 404);

        if (! in_array($campaign->status, ['active', 'paused'], true)) {
            return response()->json(['message' => match ($campaign->status) {
                'suspended' => __('أوقفت الإدارة هذا الكوبون.').' '.$campaign->review_note,
                'rejected' => __('رفضت الإدارة هذا الكوبون.').' '.$campaign->review_note,
                default => __('الكوبون بانتظار موافقة الإدارة.'),
            }], 422);
        }

        $campaign->update(['status' => $campaign->status === 'active' ? 'paused' : 'active']);
        if ($campaign->status === 'active') {
            app(FollowerIssuer::class)->activate($campaign);
        }

        return response()->json([
            'message' => $campaign->status === 'paused' ? __('أوقفت الكوبون مؤقتاً.') : __('الكوبون يعمل من جديد.'),
            'data' => $this->present($campaign->fresh(['seller', 'targets'])),
        ]);
    }

    public function destroy(CouponCampaign $campaign): JsonResponse
    {
        abort_unless(Gate::allows('manage', $campaign), 404);

        if ($campaign->isLocked()) {
            return response()->json(['message' => __('لا يمكن حذف كوبون جمعه عملاء. أوقفه بدلاً من ذلك.')], 422);
        }

        $campaign->delete();

        return response()->json(['message' => __('حُذف الكوبون.')]);
    }

    private function blockReason(SellerProfile $seller): ?string
    {
        return match (true) {
            $seller->isSuspended() => __('متجرك موقوف حالياً، لذلك لا يمكنك إنشاء كوبونات.'),
            ! $seller->isIdentityVerified() => __('لا يمكنك إنشاء كوبونات قبل توثيق متجرك وموافقة الإدارة عليه.'),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function present(CouponCampaign $c): array
    {
        return CouponPresenter::campaign($c) + [
            'status_label' => __($c->statusLabel()),
            'review_note' => $c->review_note,
            'is_locked' => $c->isLocked(),
            'auto_followers' => (bool) $c->auto_followers,
            'buy_product_id' => $c->buy_product_id,
            'gift_product_id' => $c->gift_product_id,
            'product_ids' => $c->scope === 'products' ? $c->targetIds('product') : [],
        ];
    }
}
