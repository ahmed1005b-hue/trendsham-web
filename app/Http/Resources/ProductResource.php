<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeDeal = $this->whenLoaded('activeDailyDeal') ? $this->activeDailyDeal : null;
        $effectiveMinor = $activeDeal ? (int) round($activeDeal->discounted_price) : (int) $this->price;

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'sku' => $this->sku,
            'price' => $this->price / 100,
            'compare_at_price' => $this->compare_at_price ? $this->compare_at_price / 100 : null,
            'discount_percentage' => $this->discount_percentage,
            'effective_price' => $effectiveMinor / 100,
            'formatted_effective_price' => number_format($effectiveMinor / 100, 0, '.', ',').' '.__('ل.س'),
            'deal_discount_percentage' => $activeDeal ? (int) round($activeDeal->discount_percentage) : null,
            'deal_label' => $activeDeal ? __('تخفيض اليوم') : null,
            'active_daily_deal' => $activeDeal ? [
                'id' => $activeDeal->id,
                'discounted_price' => round($activeDeal->discounted_price / 100, 2),
                'discount_percentage' => (float) $activeDeal->discount_percentage,
                'special_price' => $activeDeal->special_price !== null ? (float) $activeDeal->special_price : null,
                'ends_at' => $activeDeal->ends_at,
            ] : null,
            'currency' => 'SYP',
            'stock_quantity' => $this->stock_quantity,
            'weight_grams' => $this->weight_grams,
            'status' => $this->status,
            // للبائع صاحب المنتج والمدير فقط: هل وافقت الإدارة، ولماذا رُفض
            'admin_approved' => $this->when($this->visibleToOwner($request), fn () => $this->admin_approved_at !== null),
            'rejection_note' => $this->when($this->visibleToOwner($request), fn () => $this->rejection_note),
            'in_stock' => $this->stock_quantity > 0,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'is_favorited' => $this->when(isset($this->is_favorited), (bool) ($this->is_favorited ?? false)),
            'will_earn_points' => $this->when(isset($this->will_earn_points), (int) ($this->will_earn_points ?? 0)),
            'store_coupons' => $this->when(isset($this->store_coupons), fn () => $this->store_coupons),
            'created_at' => $this->created_at,
        ];

        if ($this->relationLoaded('availableCoupons')) {
            $data['available_coupons'] = $this->when(
                $this->relationLoaded('availableCoupons'),
                AvailableCouponResource::collection($this->availableCoupons)
            );
        }

        return $data;
    }

    private function visibleToOwner(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->role === 'admin' || ($user->role === 'seller' && $user->sellerProfile?->id === $this->vendor_id));
    }
}
