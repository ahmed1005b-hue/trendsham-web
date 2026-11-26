<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\StorefrontVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontAttribution
{
    public const TOKEN_HEADER = 'X-Store-Visitor-Token';

    public function tokenFor(Request $request): string
    {
        $token = $request->header(self::TOKEN_HEADER) ?? $request->cookie('trendsham_store_visitor');

        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token)
            ? strtolower($token)
            : bin2hex(random_bytes(32));
    }

    public function recordVisit(SellerProfile $store, string $token): StorefrontVisit
    {
        $hash = $this->visitorHash($token, $store->id);
        $today = now()->toDateString();

        DB::table('storefront_visits')->insertOrIgnore([
            'seller_profile_id' => $store->id,
            'visitor_hash' => $hash,
            'visited_on' => $today,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StorefrontVisit::query()
            ->where('seller_profile_id', $store->id)
            ->where('visitor_hash', $hash)
            ->whereDate('visited_on', $today)
            ->firstOrFail();
    }

    public function resolveVisit(?string $token, int $sellerProfileId): ?StorefrontVisit
    {
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
            return null;
        }

        return StorefrontVisit::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->where('visitor_hash', $this->visitorHash(strtolower($token), $sellerProfileId))
            ->latest('visited_on')
            ->first();
    }

    public function dashboardMetrics(int $sellerProfileId): array
    {
        $visits = StorefrontVisit::query()->where('seller_profile_id', $sellerProfileId);
        $convertingVisits = StorefrontVisit::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->whereHas('orderItems', fn ($query) => $query
                ->where('vendor_id', $sellerProfileId)
                ->whereHas(
                    'order',
                    fn ($orders) => $orders->where('status', '!=', 'cancelled')->releasedToSellers()
                ));

        $visitCount = (int) $visits->count();
        $convertedCount = (int) $convertingVisits->count();
        $attributedOrders = (int) OrderItem::query()->releasedToSellers()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $sellerProfileId)
            ->whereNotNull('order_items.storefront_visit_id')
            ->where('orders.status', '!=', 'cancelled')
            ->distinct()
            ->count('orders.id');

        return [
            'total_views' => $visitCount,
            'attributed_converting_visits' => $convertedCount,
            'attributed_orders' => $attributedOrders,
            'conversion_rate' => $visitCount > 0 ? round($convertedCount / $visitCount * 100, 2) : 0,
        ];
    }

    private function visitorHash(string $token, int $sellerProfileId): string
    {
        return hash_hmac('sha256', $sellerProfileId.':'.$token, (string) config('app.key'));
    }
}
