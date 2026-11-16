<?php

namespace App\Observers;

use App\Models\AppNotification;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->notifyFollowers($product);
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(['status', 'admin_approved_at'])) {
            $this->notifyFollowers($product);
        }
    }

    private function notifyFollowers(Product $product): void
    {
        if (! $product->isLive() || ! $product->vendor?->isApproved()) {
            return;
        }

        $link = route('products.show', $product);
        $product->vendor->followers()
            ->whereHas('user', fn ($query) => $query->where('role', 'customer')->where('id', '!=', $product->vendor->user_id))
            ->with('user')
            ->get()
            ->each(function ($follower) use ($product, $link) {
                $exists = AppNotification::where('user_id', $follower->user_id)
                    ->where('type', 'store_new_product')
                    ->where('link', $link)
                    ->exists();
                if (! $exists) {
                    AppNotification::send(
                        $follower->user_id,
                        'store_new_product',
                        'منتج جديد من '.$product->vendor->store_name,
                        'أضاف المتجر منتجاً جديداً. اكتشفه الآن.',
                        $link,
                    );
                }
            });
    }
}
