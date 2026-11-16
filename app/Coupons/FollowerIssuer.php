<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\SellerProfile;
use App\Models\StoreFollower;
use App\Models\User;

/**
 * كوبونات المتابعين: تصل لكل متابعي المتجر عند تفعيل الكوبون، ولكل من يتابع لاحقاً خلال مدته.
 * مرة واحدة لكل عميل — حتى لو ألغى المتابعة وتابع من جديد.
 */
class FollowerIssuer
{
    public function __construct(private readonly CouponIssuer $issuer) {}

    /**
     * عند أول دخول للحالة "فعّال": الإرسال للمتابعين الحاليين بعد إرسال الرد للبائع
     * (afterResponse لا يحتاج عامل طوابير، ولا يؤخّر صفحة البائع).
     */
    public function activate(CouponCampaign $campaign): void
    {
        if (! $campaign->reachesFollowers() || $campaign->followers_issued_at || $campaign->status !== 'active') {
            return;
        }

        $id = $campaign->id;
        dispatch(fn () => app(self::class)->issueToAll(CouponCampaign::findOrFail($id)))->afterResponse();
    }

    /** يرسل لكل المتابعين على دفعات، ويتوقف عند نفاد العدد. يُعيد عدد النسخ الصادرة. */
    public function issueToAll(CouponCampaign $campaign): int
    {
        if ($campaign->followers_issued_at || ! $campaign->reachesFollowers()) {
            return 0;
        }
        // نثبّت التاريخ أولاً: تشغيلان متزامنان لا يرسلان مرتين (issue() نفسه لا يكرر النسخ أيضاً)
        $claimed = CouponCampaign::whereKey($campaign->id)->whereNull('followers_issued_at')->update(['followers_issued_at' => now()]);
        if (! $claimed) {
            return 0;
        }

        $issued = 0;
        StoreFollower::where('seller_profile_id', $campaign->seller_profile_id)->with('user')
            ->chunkById(200, function ($followers) use ($campaign, &$issued) {
                foreach ($followers as $f) {
                    $fresh = $campaign->fresh();
                    if ($fresh->isSoldOut()) {
                        return false;
                    }
                    if ($f->user && $f->user->role === 'customer' && $this->issueOnce($fresh, $f->user)) {
                        $issued++;
                    }
                }
            });

        return $issued;
    }

    /** متابع جديد: كل كوبونات المتابعين الفعّالة للمتجر. يُعيد عدد ما وصله. */
    public function issueForNewFollower(SellerProfile $store, User $user): int
    {
        if ($user->role !== 'customer') {
            return 0;
        }

        return CouponCampaign::running()->ofSeller($store->id)
            ->where(fn ($q) => $q->where('audience', 'followers')->orWhere(fn ($w) => $w->where('audience', 'collectable')->where('auto_followers', true)))
            ->get()
            ->filter(fn (CouponCampaign $c) => $this->issueOnce($c, $user))
            ->count();
    }

    private function issueOnce(CouponCampaign $campaign, User $user): bool
    {
        $hadCopy = CustomerCoupon::where('campaign_id', $campaign->id)->where('user_id', $user->id)
            ->where('status', '!=', 'revoked')->exists();

        return ! $hadCopy && $this->issuer->issue($campaign, $user, 'followers') !== null;
    }
}
