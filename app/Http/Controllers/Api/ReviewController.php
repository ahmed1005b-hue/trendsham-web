<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function storeForProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = DB::transaction(function () use ($product, $request, $validated) {
            $orderItem = OrderItem::query()
                ->where('product_id', $product->id)
                ->whereHas('order', fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'delivered'))
                ->whereDoesntHave('review')
                ->latest()
                ->lockForUpdate()
                ->first();

            abort_unless($orderItem, 422, 'يمكنك تقييم المنتج بعد شرائه واستلامه، ولمرة واحدة لكل عملية شراء.');

            return Review::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'order_item_id' => $orderItem->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
        });

        return (new ReviewResource($review->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function store(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = DB::transaction(function () use ($orderItem, $request, $validated) {
            $orderItem = OrderItem::query()
                ->whereKey($orderItem->id)
                ->lockForUpdate()
                ->with('order', 'review')
                ->firstOrFail();

            Gate::authorize('review', $orderItem);
            abort_unless($orderItem->canBeReviewed(), 422, 'لا يمكن تقييم هذا المنتج.');

            return Review::create([
                'product_id' => $orderItem->product_id,
                'user_id' => $request->user()->id,
                'order_item_id' => $orderItem->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
        });

        return (new ReviewResource($review->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
