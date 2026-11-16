<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show()
    {
        return response()->json([
            'sellers_count' => User::where('role', 'seller')->count(),
            'customers_count' => User::where('role', 'customer')->count(),
            'pending_sellers_count' => SellerProfile::where('status', 'pending')->count(),
            'pending_products_count' => Product::pendingReview()->count(),
            'pending_withdrawals_count' => WithdrawalRequest::where('status', 'pending')->count(),
            'total_sales' => Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total') / 100,
            'total_commission' => OrderItem::whereNotNull('commission_settled_at')->sum('commission_amount') / 100,
        ]);
    }

    /** تحليلات المنصة الشاملة (نفس أرقام صفحة الويب). */
    public function analytics(Request $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->summary(AdminAnalyticsService::normalizeDays($request->query('days'))));
    }
}
