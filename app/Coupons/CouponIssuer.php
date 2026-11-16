<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * يضع الكوبونات في محافظ العملاء — بلا تكرار، وضمن الحدود.
 * per_customer_limit = عدد النسخ التي يمكن أن يحصل عليها العميل (كل نسخة تُستعمل مرة).
 */
class CouponIssuer
{
    /** يصدر نسخة إن سمحت الحدود ولم يكن لدى العميل نسخة متاحة من نفس الحملة. */
    public function issue(CouponCampaign $campaign, User $user, string $source): ?CustomerCoupon
    {
        // فحص سريع بلا قفل: الحالة الأكثر شيوعاً (لدى العميل نسخة أصلاً) لا تقفل سجل الحملة
        if (! $this->mayIssue($campaign->id, $user->id, $campaign->per_customer_limit)) {
            return null;
        }

        $coupon = DB::transaction(function () use ($campaign, $user, $source) {
            $locked = CouponCampaign::whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isRunning()) {
                return null;
            }
            // كوبون البائع: العدد الإجمالي = عدد النسخ (تحت القفل فلا يتجاوزه طلبان متزامنان)
            if ($locked->funder === 'seller' && $locked->isSoldOut()) {
                return null;
            }

            $copies = CustomerCoupon::where('campaign_id', $locked->id)->where('user_id', $user->id)
                ->where('status', '!=', 'revoked')->get();
            if ($copies->contains('status', 'available') || $copies->count() >= $locked->per_customer_limit) {
                return null;
            }

            $coupon = CustomerCoupon::create([
                'campaign_id' => $locked->id,
                'user_id' => $user->id,
                'status' => 'available',
                'source' => $source,
                'expires_at' => $locked->valid_days ? now()->addDays($locked->valid_days) : null,
            ]);
            $locked->increment('issued_count');

            return $coupon;
        });

        if ($coupon) {
            AppNotification::send(
                $user->id,
                'coupon_received',
                'وصلك كوبون: '.$campaign->valueLabel('ar'),
                $campaign->title.' — '.$campaign->sourceLabel(),
                Route::has('customer.coupons.index') ? route('customer.coupons.index') : null
            );
        }

        return $coupon;
    }

    /**
     * "احفظ" من صفحة المتجر/المنتج لكوبون بائع قابل للجمع.
     *
     * @throws CouponCollectException already | sold_out | unavailable
     */
    public function collect(CouponCampaign $campaign, User $user): CustomerCoupon
    {
        $campaign->loadMissing('seller');
        if ($campaign->funder !== 'seller' || $campaign->audience !== 'collectable' || ! $campaign->isRunning() || $user->role !== 'customer') {
            throw new CouponCollectException('unavailable', 'الكوبون غير متاح.');
        }
        if (CustomerCoupon::where('campaign_id', $campaign->id)->where('user_id', $user->id)->where('status', 'available')->exists()) {
            throw new CouponCollectException('already', 'الكوبون في محفظتك أصلاً.');
        }
        if ($campaign->isSoldOut()) {
            throw new CouponCollectException('sold_out', 'نفدت الكوبونات');
        }

        $coupon = $this->issue($campaign, $user, 'collect');
        if (! $coupon) {
            $fresh = $campaign->fresh('seller');
            throw match (true) {
                $fresh->isSoldOut() => new CouponCollectException('sold_out', 'نفدت الكوبونات'),
                ! $fresh->isRunning() => new CouponCollectException('unavailable', 'الكوبون غير متاح.'),
                default => new CouponCollectException('already', 'استخدمت هذا الكوبون بعدد المرات المسموح.'),
            };
        }

        return $coupon;
    }

    private function mayIssue(int $campaignId, int $userId, int $perCustomerLimit): bool
    {
        $copies = CustomerCoupon::where('campaign_id', $campaignId)->where('user_id', $userId)
            ->where('status', '!=', 'revoked')->pluck('status');

        return ! $copies->contains('available') && $copies->count() < $perCustomerLimit;
    }

    /** كوبونات المنصة للجمهور العام (كل العملاء / العملاء الجدد) تُصدَر عند فتح المحفظة أو السلة. */
    public function syncFor(User $user): void
    {
        if ($user->role !== 'customer') {
            return;
        }

        $isNew = $this->isNewCustomer($user);
        CouponCampaign::running()
            ->where('funder', 'platform')
            ->whereIn('audience', $isNew ? ['all_customers', 'new_customers'] : ['all_customers'])
            ->get()
            ->each(fn (CouponCampaign $c) => $this->issue($c, $user, $c->audience));
    }

    /** @param int[] $userIds */
    public function issueToSpecific(CouponCampaign $campaign, array $userIds): int
    {
        return User::whereIn('id', $userIds)->where('role', 'customer')->get()
            ->filter(fn (User $u) => $this->issue($campaign, $u, 'specific'))
            ->count();
    }

    /** عميل جديد = بلا طلبات غير ملغاة + هاتف متحقَّق منه (يمنع الحسابات الوهمية). */
    public function isNewCustomer(User $user): bool
    {
        return $user->phone_verified_at !== null
            && ! $user->orders()->where('status', '!=', 'cancelled')->exists();
    }
}
