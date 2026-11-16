<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    public function forProduct(Product $product, int $perSection = 6): array
    {
        $similar = Product::publiclyVisible()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with(['primaryImage', 'category', 'vendor'])
            ->withSum([
                'orderItems as sales_quantity' => fn (Builder $query) => $query->whereHas(
                    'order',
                    fn (Builder $orders) => $orders->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                ),
            ], 'quantity')
            ->orderByDesc('sales_quantity')
            ->latest()
            ->limit($perSection)
            ->get();

        $excludeIds = $similar->pluck('id')->push($product->id)->all();
        $popular = Product::publiclyVisible()
            ->whereNotIn('id', $excludeIds)
            ->with(['primaryImage', 'category', 'vendor'])
            ->withSum([
                'orderItems as sales_quantity' => fn (Builder $query) => $query->whereHas(
                    'order',
                    fn (Builder $orders) => $orders->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                ),
            ], 'quantity')
            ->orderByDesc('sales_quantity')
            ->latest()
            ->limit($perSection)
            ->get();

        return [
            'similar' => $similar,
            'popular' => $popular,
        ];
    }
}
