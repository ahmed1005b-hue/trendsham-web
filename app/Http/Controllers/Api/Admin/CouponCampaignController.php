<?php

namespace App\Http\Controllers\Api\Admin;

use App\Coupons\CampaignWriter;
use App\Coupons\CouponPresenter;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** حملات كوبونات المنصة في تطبيق المدير (نفس قواعد لوحة الموقع). */
class CouponCampaignController extends Controller
{
    public function index()
    {
        $page = CouponCampaign::with('seller')->where(fn ($q) => $q->whereNull('trigger')->orWhere('trigger', '!=', 'loyalty_points'))->latest()->paginate(30); // حملات نقاط الولاء نظامية مخفية

        return response()->json([
            'data' => $page->getCollection()->map(fn ($c) => CouponPresenter::campaign($c))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'audience_sizes' => [
                'new_customers' => CampaignWriter::audienceSize('new_customers'),
                'all_customers' => CampaignWriter::audienceSize('all_customers'),
            ],
        ]);
    }

    public function store(Request $request, CampaignWriter $writer)
    {
        $data = $request->validate(CampaignWriter::rules(), CampaignWriter::messages());
        $campaign = $writer->create($data, $request->user());

        return response()->json(['message' => 'تم إنشاء الكوبون.', 'data' => CouponPresenter::campaign($campaign->fresh('seller'))], 201);
    }

    public function toggle(CouponCampaign $campaign)
    {
        $campaign->update(['status' => $campaign->status === 'active' ? 'paused' : 'active']);

        return response()->json(['data' => CouponPresenter::campaign($campaign->fresh('seller'))]);
    }
}
