<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function store(Request $request, OrderItem $orderItem)
    {
        Gate::authorize('review', $orderItem);
        abort_unless($orderItem->canBeReviewed(), 422, 'لا يمكن تقييم هذا المنتج (إما لم يُسلّم بعد أو تم تقييمه مسبقاً).');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::create([
            'product_id' => $orderItem->product_id,
            'user_id' => $request->user()->id,
            'order_item_id' => $orderItem->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'شكراً لتقييمك!');
    }
}
