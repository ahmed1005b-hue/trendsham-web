<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/** قرارات المدير على كوبونات البائعين: موافقة (فوق 50%)، رفض، إيقاف — مع إشعار البائع. */
class CampaignReview
{
    public function __construct(private readonly FollowerIssuer $followers) {}

    public function approve(CouponCampaign $campaign, User $admin): void
    {
        $this->guard($campaign, ['pending_review'], 'يمكن الموافقة على كوبون بانتظار المراجعة فقط.');
        $this->decide($campaign, $admin, 'active', null);
        $this->followers->activate($campaign->fresh());
        $this->notify($campaign, 'وافقت الإدارة على كوبونك: '.$campaign->title, 'الكوبون يعمل الآن ويظهر في صفحة متجرك.');
    }

    public function reject(CouponCampaign $campaign, User $admin, string $reason): void
    {
        $this->guard($campaign, ['pending_review'], 'يمكن رفض كوبون بانتظار المراجعة فقط.');
        $this->decide($campaign, $admin, 'rejected', $reason);
        $this->notify($campaign, 'رُفض كوبونك: '.$campaign->title, 'السبب: '.$reason);
    }

    /** إيقاف نهائي: لا يستطيع البائع إعادة تشغيله، والنسخ في المحافظ تصبح غير صالحة. */
    public function suspend(CouponCampaign $campaign, User $admin, string $reason): void
    {
        $this->guard($campaign, ['active', 'paused', 'pending_review'], 'هذا الكوبون موقوف أو مرفوض أصلاً.');
        $this->decide($campaign, $admin, 'suspended', $reason);
        $this->notify($campaign, 'أوقفت الإدارة كوبونك: '.$campaign->title, 'السبب: '.$reason);
    }

    private function guard(CouponCampaign $campaign, array $from, string $message): void
    {
        if ($campaign->funder !== 'seller' || ! in_array($campaign->status, $from, true)) {
            throw new \DomainException($message);
        }
    }

    private function decide(CouponCampaign $campaign, User $admin, string $status, ?string $note): void
    {
        $campaign->update(['status' => $status, 'review_note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
    }

    private function notify(CouponCampaign $campaign, string $title, string $body): void
    {
        $userId = $campaign->seller?->user_id;
        if ($userId) {
            AppNotification::send($userId, 'seller_coupon_review', $title, $body,
                Route::has('seller.coupons.show') ? route('seller.coupons.show', $campaign) : null);
        }
    }
}
