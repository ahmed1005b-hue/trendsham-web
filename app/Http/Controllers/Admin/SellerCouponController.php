<?php

namespace App\Http\Controllers\Admin;

use App\Coupons\CampaignReview;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

/** إشراف المدير على كوبونات البائعين */
class SellerCouponController extends Controller
{
    public function __construct(private readonly CampaignReview $review) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'store' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(CouponCampaign::STATUSES))],
        ]);

        $campaigns = CouponCampaign::where('funder', 'seller')
            ->with(['seller:id,store_name', 'giftProduct:id,name'])
            ->when($filters['store'] ?? null, fn ($q, $id) => $q->where('seller_profile_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("status = 'pending_review' DESC")->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.seller-coupons.index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
            'pendingCount' => CouponCampaign::where('funder', 'seller')->where('status', 'pending_review')->count(),
            'stores' => SellerProfile::whereIn('id', CouponCampaign::where('funder', 'seller')->select('seller_profile_id'))
                ->orderBy('store_name')->get(['id', 'store_name']),
        ]);
    }

    public function approve(Request $request, CouponCampaign $campaign)
    {
        return $this->run(fn () => $this->review->approve($campaign, $request->user()), 'تمت الموافقة، والكوبون يعمل الآن.');
    }

    public function reject(Request $request, CouponCampaign $campaign)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'اكتب سبب الرفض ليصل للبائع.']);

        return $this->run(fn () => $this->review->reject($campaign, $request->user(), $data['reason']), 'رُفض الكوبون وأُبلغ البائع.');
    }

    public function suspend(Request $request, CouponCampaign $campaign)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'اكتب سبب الإيقاف ليصل للبائع.']);

        return $this->run(fn () => $this->review->suspend($campaign, $request->user(), $data['reason']), 'أُوقف الكوبون وأُبلغ البائع.');
    }

    private function run(callable $action, string $success)
    {
        try {
            $action();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
