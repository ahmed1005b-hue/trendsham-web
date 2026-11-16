<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = $user->orders();
        $stats = [
            'orders_count' => $orders->count(),
            'total_spent' => $orders->where('status', '!=', 'cancelled')->sum('total') / 100,
            'pending_orders' => $orders->whereIn('status', ['pending', 'processing'])->count(),
            'favorites_count' => $user->favorites()->count(),
            'followed_stores_count' => $user->followedStores()->where('status', 'approved')->count(),
        ];

        $recentOrders = $user->orders()->latest()->take(5)->get();

        $followedStores = $user->followedStores()
            ->where('status', 'approved')
            ->withCount('products')
            ->latest('store_followers.created_at')
            ->take(4)
            ->get();

        return view('customer.dashboard', compact('user', 'stats', 'recentOrders', 'followedStores'));
    }
}
