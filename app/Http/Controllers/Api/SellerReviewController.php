<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerReviewResource;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SellerReviewController extends Controller
{
    public function index(Request $request, SellerProfile $sellerProfile)
    {
        $reviews = $sellerProfile->sellerReviews()
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => SellerReviewResource::collection($reviews->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'average_rating' => $sellerProfile->sellerReviews()->avg('rating'),
                'reviews_count' => $reviews->total(),
                'rating_breakdown' => [
                    '5' => $sellerProfile->sellerReviews()->where('rating', 5)->count(),
                    '4' => $sellerProfile->sellerReviews()->where('rating', 4)->count(),
                    '3' => $sellerProfile->sellerReviews()->where('rating', 3)->count(),
                    '2' => $sellerProfile->sellerReviews()->where('rating', 2)->count(),
                    '1' => $sellerProfile->sellerReviews()->where('rating', 1)->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request, OrderItem $orderItem)
    {
        $user = $request->user();
        // فقط مالك الطلب يستطيع تقييم البائع، وبعد التسليم
        Gate::authorize('review', $orderItem);
        abort_unless($orderItem->order->status === 'delivered', 422, 'يمكن التقييم بعد تسليم الطلب فقط.');
        // لا تقييم مكرر لنفس العنصر
        abort_if($orderItem->sellerReviews()->where('user_id', $user->id)->exists(), 422, 'لقد قيّمت هذا العنصر بالفعل.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $orderItem->sellerReviews()->create([
            'vendor_id' => $orderItem->vendor_id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'data' => new SellerReviewResource($review->load('user')),
        ], 201);
    }
}
