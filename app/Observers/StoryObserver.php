<?php

namespace App\Observers;

use App\Models\AppNotification;
use App\Models\Story;

class StoryObserver
{
    public function created(Story $story): void
    {
        if (! $story->is_active || $story->isExpired() || ! $story->vendor?->isApproved()) {
            return;
        }

        $link = route('stores.show', $story->vendor).'#offer-'.$story->id;
        $story->vendor->followers()
            ->whereHas('user', fn ($query) => $query->where('role', 'customer')->where('id', '!=', $story->vendor->user_id))
            ->get()
            ->each(function ($follower) use ($story, $link) {
                AppNotification::send(
                    $follower->user_id,
                    'store_new_offer',
                    'عرض جديد من '.$story->vendor->store_name,
                    'نشر المتجر عرضاً جديداً.',
                    $link,
                );
            });
    }
}
