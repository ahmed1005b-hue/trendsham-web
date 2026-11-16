<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductReviewController extends Controller
{
    /**
     * حفظ تقييم لمنتج بواسطة المستخدم.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::create([
            'product_id' => $product->id,
            'user_id'    => $request->user()->id,
            'rating'     => $validated['rating'],
            'comment'    => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم إضافة التقييم بنجاح',
            'data'    => $review,
        ], 201);
    }
}
