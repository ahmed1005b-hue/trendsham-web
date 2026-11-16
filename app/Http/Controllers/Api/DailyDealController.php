<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyDeal;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class DailyDealController extends Controller
{
    public function index(Request $request)
    {
        // عروض اليوم النشطة - قائمة للصفحة الرئيسية
        if ($request->boolean('all')) {
            $deals = DailyDeal::with('product.primaryImage', 'product.images', 'product.vendor')
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->where(function ($q) {
                    $q->where('max_quantity', 0)
                        ->orWhereColumn('sold_quantity', '<', 'max_quantity');
                })
                ->whereHas('product', fn ($p) => $p->publiclyVisible())
                ->latest()
                ->limit(12)
                ->get()
                ->filter(fn ($d) => $d->product);

            return response()->json([
                'deals' => $deals->map(fn ($deal) => [
                    'id' => $deal->id,
                    'product' => new ProductResource($deal->product->loadMissing(['primaryImage', 'images'])),
                    'discount_percentage' => $deal->discount_percentage,
                    'special_price' => $deal->special_price,
                    'discounted_price' => round($deal->discounted_price / 100, 2),
                    'ends_at' => $deal->ends_at,
                    'remaining_time' => $deal->remaining_time,
                    'max_quantity' => $deal->max_quantity,
                    'sold_quantity' => $deal->sold_quantity,
                    'remaining_quantity' => $deal->max_quantity > 0 ? $deal->max_quantity - $deal->sold_quantity : null,
                ]),
            ]);
        }
        $deal = DailyDeal::with('product.primaryImage', 'product.images')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($q) {
                $q->where('max_quantity', 0)
                    ->orWhereColumn('sold_quantity', '<', 'max_quantity');
            })
            ->first();

        if (!$deal) {
            return response()->json([
                'message' => 'لا يوجد عرض حالياً',
                'deal' => null,
            ]);
        }

        return response()->json([
            'deal' => [
                'id' => $deal->id,
                'product' => new ProductResource($deal->product->loadMissing(['primaryImage', 'images'])),
                'discount_percentage' => $deal->discount_percentage,
                'special_price' => $deal->special_price,
                'discounted_price' => round($deal->discounted_price / 100, 2),
                'starts_at' => $deal->starts_at,
                'ends_at' => $deal->ends_at,
                'remaining_time' => $deal->remaining_time,
                'is_expired' => $deal->is_expired,
                'max_quantity' => $deal->max_quantity,
                'sold_quantity' => $deal->sold_quantity,
                'remaining_quantity' => $deal->max_quantity > 0 ? $deal->max_quantity - $deal->sold_quantity : null,
            ],
        ]);
    }
}
