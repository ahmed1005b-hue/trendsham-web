<?php

namespace App\Policies;

use App\Coupons\Models\CouponCampaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** حملة كوبون البائع: لا نكشف وجود حملات غيره (404 بلا رسالة، مطابق لـ abort(404) الحالي). */
class CouponCampaignPolicy
{
    public function manage(User $user, CouponCampaign $campaign): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null
            && $campaign->funder === 'seller'
            && (int) $campaign->seller_profile_id === $storeId
            ? Response::allow()
            : Response::denyAsNotFound('');
    }
}
