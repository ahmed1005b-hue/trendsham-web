<?php

namespace App\Http\Controllers\Admin;

use App\Coupons\CampaignWriter;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

/** حملات كوبونات المنصة (المنصة تتحمّل الخصم). */
class CouponCampaignController extends Controller
{
    public function index()
    {
        return view('admin.coupon-campaigns.index', [
            'campaigns' => CouponCampaign::with('seller')->where(fn ($q) => $q->whereNull('trigger')->orWhere('trigger', '!=', 'loyalty_points'))->latest()->paginate(30), // حملات نقاط الولاء نظامية مخفية
        ]);
    }

    public function create()
    {
        return view('admin.coupon-campaigns.create', [
            'categories' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'stores' => SellerProfile::where('status', 'approved')->orderBy('store_name')->get(['id', 'store_name']),
            'audienceSizes' => [
                'new_customers' => CampaignWriter::audienceSize('new_customers'),
                'all_customers' => CampaignWriter::audienceSize('all_customers'),
            ],
        ]);
    }

    public function store(Request $request, CampaignWriter $writer)
    {
        $data = $request->validate(CampaignWriter::rules(), CampaignWriter::messages());
        $campaign = $writer->create($data, $request->user());

        return redirect()->route('admin.coupon-campaigns.index')
            ->with('success', "تم إنشاء الكوبون «{$campaign->title}». يظهر في محافظ العملاء المستهدفين.");
    }

    public function toggle(CouponCampaign $campaign)
    {
        $campaign->update(['status' => $campaign->status === 'active' ? 'paused' : 'active']);

        return back()->with('success', $campaign->status === 'paused'
            ? 'تم إيقاف الكوبون. لن يُستخدم في طلبات جديدة.'
            : 'تم تفعيل الكوبون.');
    }
}
