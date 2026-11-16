<?php

namespace App\Http\Controllers\Seller;

use App\Coupons\FollowerIssuer;
use App\Coupons\Models\CouponCampaign;
use App\Coupons\SellerCampaignWriter;
use App\Coupons\SellerCouponStats;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** كوبونات البائع: ينشئها لمتجره ويتحمّل خصمها من أرباحه. */
class CouponCampaignController extends Controller
{
    public function __construct(private readonly SellerCampaignWriter $writer) {}

    private function store_(Request $r): SellerProfile
    {
        return $r->user()->sellerProfile;
    }

    /** سبب منع الإنشاء، أو null إن كان مسموحاً. */
    private function blockReason(SellerProfile $seller): ?string
    {
        return match (true) {
            $seller->isSuspended() => 'متجرك موقوف حالياً، لذلك لا يمكنك إنشاء كوبونات.',
            ! $seller->isIdentityVerified() => 'لا يمكنك إنشاء كوبونات قبل توثيق متجرك وموافقة الإدارة عليه.',
            default => null,
        };
    }

    public function index(Request $r)
    {
        $seller = $this->store_($r);

        return view('seller.coupons.index', [
            'campaigns' => CouponCampaign::ofSeller($seller->id)->with('giftProduct')->latest()->paginate(20),
            'blockReason' => $this->blockReason($seller),
        ]);
    }

    public function create(Request $r)
    {
        $seller = $this->store_($r);

        return view('seller.coupons.create', $this->formData($seller) + [
            'campaign' => new CouponCampaign(['type' => 'percentage', 'scope' => 'all', 'audience' => 'collectable', 'per_customer_limit' => 1]),
            'blockReason' => $this->blockReason($seller),
        ]);
    }

    public function store(Request $r)
    {
        $seller = $this->store_($r);
        abort_if($this->blockReason($seller), 403);

        $data = $r->validate(SellerCampaignWriter::rules($seller), SellerCampaignWriter::messages());
        $campaign = $this->writer->create($data, $seller, $r->user());

        if ($campaign->status === 'active') {
            app(FollowerIssuer::class)->activate($campaign);
        }

        return redirect()->route('seller.coupons.index')->with('success', $campaign->status === 'pending_review'
            ? 'حُفظ الكوبون، وهو بانتظار موافقة الإدارة لأن الخصم أكثر من '.CouponCampaign::REVIEW_ABOVE_PERCENT.'%.'
            : 'الكوبون يعمل الآن ويظهر في صفحة متجرك.');
    }

    public function show(Request $r, CouponCampaign $campaign)
    {
        abort_unless(Gate::allows('manage', $campaign), 404);
        $campaign->load('giftProduct', 'buyProduct');

        return view('seller.coupons.show', [
            'campaign' => $campaign,
            'stats' => app(SellerCouponStats::class)->for($campaign),
            'recent' => app(SellerCouponStats::class)->recentOrders($campaign),
        ]);
    }

    public function edit(Request $r, CouponCampaign $campaign)
    {
        abort_unless(Gate::allows('manage', $campaign), 404);
        $campaign->load('targets');
        abort_if(in_array($campaign->status, ['suspended', 'rejected'], true), 403);

        return view('seller.coupons.create', $this->formData($this->store_($r)) + ['campaign' => $campaign, 'blockReason' => null]);
    }

    public function update(Request $r, CouponCampaign $campaign)
    {
        abort_unless(Gate::allows('manage', $campaign), 404);
        abort_if(in_array($campaign->status, ['suspended', 'rejected'], true), 403);

        $data = $campaign->isLocked()
            ? $r->validate([
                'ends_at' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addYear()->toDateString()],
                'total_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            ], SellerCampaignWriter::messages())
            : $r->validate(SellerCampaignWriter::rules($this->store_($r)), SellerCampaignWriter::messages());

        $this->writer->update($campaign, $data);

        return redirect()->route('seller.coupons.show', $campaign)->with('success', 'حُفظت التعديلات.');
    }

    /** البائع يوقف ويشغّل كوبونه فقط؛ ما أوقفته الإدارة أو ينتظر مراجعتها ليس بيده. */
    public function toggle(Request $r, CouponCampaign $campaign)
    {
        abort_unless(Gate::allows('manage', $campaign), 404);

        if (! in_array($campaign->status, ['active', 'paused'], true)) {
            return back()->with('error', match ($campaign->status) {
                'suspended' => 'أوقفت الإدارة هذا الكوبون: '.$campaign->review_note,
                'rejected' => 'رفضت الإدارة هذا الكوبون: '.$campaign->review_note,
                default => 'الكوبون بانتظار موافقة الإدارة.',
            });
        }

        $campaign->update(['status' => $campaign->status === 'active' ? 'paused' : 'active']);
        if ($campaign->status === 'active') {
            app(FollowerIssuer::class)->activate($campaign);
        }

        return back()->with('success', $campaign->status === 'paused' ? 'أوقفت الكوبون مؤقتاً.' : 'الكوبون يعمل من جديد.');
    }

    public function destroy(Request $r, CouponCampaign $campaign)
    {
        abort_unless(Gate::allows('manage', $campaign), 404);

        if ($campaign->isLocked()) {
            return back()->with('error', 'لا يمكن حذف كوبون جمعه عملاء. أوقفه بدلاً من ذلك.');
        }

        $campaign->delete();

        return redirect()->route('seller.coupons.index')->with('success', 'حُذف الكوبون.');
    }

    private function formData(SellerProfile $seller): array
    {
        return [
            'products' => Product::where('vendor_id', $seller->id)->orderBy('name')
                ->withCount('variants')->get(['id', 'name', 'price', 'stock_quantity']),
            'followersCount' => $seller->followers()->count(),
            'commissionRate' => (float) $seller->effective_commission_rate,
        ];
    }
}
