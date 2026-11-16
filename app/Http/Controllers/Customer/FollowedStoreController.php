<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FollowedStoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = $request->user()->followedStores()
            ->where('status', 'approved')
            ->withCount('products')
            ->withCount('followers')
            ->withAvg('sellerReviews', 'rating')
            ->latest('store_followers.created_at')
            ->paginate(20);

        return view('customer.followed-stores.index', [
            'stores' => $stores,
        ]);
    }
}
