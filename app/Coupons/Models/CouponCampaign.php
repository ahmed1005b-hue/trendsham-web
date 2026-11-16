<?php

namespace App\Coupons\Models;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تعريف الكوبون: نوعه وقيمته وشروطه ومن يموّله ومن يستهدف. */
class CouponCampaign extends Model
{
    public const TYPES = ['percentage' => 'نسبة مئوية', 'fixed' => 'مبلغ ثابت', 'free_shipping' => 'شحن مجاني', 'gift' => 'منتج هدية'];

    public const AUDIENCES = ['new_customers' => 'العملاء الجدد', 'all_customers' => 'كل العملاء', 'specific' => 'عميل معيّن',
        'collectable' => 'للجمع من المتجر', 'followers' => 'متابعو المتجر', 'automatic' => 'تلقائي'];

    protected $fillable = [
        'funder', 'seller_profile_id', 'title', 'type', 'value', 'max_discount', 'min_order_amount', 'scope',
        'audience', 'trigger', 'starts_at', 'ends_at', 'valid_days', 'per_customer_limit', 'total_limit',
        'issued_count', 'redeemed_count', 'status', 'created_by',
        'auto_followers', 'buy_product_id', 'gift_product_id', 'review_note', 'reviewed_by', 'reviewed_at', 'followers_issued_at',
    ];

    public const STATUSES = ['pending_review' => 'بانتظار موافقة الإدارة', 'active' => 'فعّال', 'paused' => 'موقوف مؤقتاً',
        'rejected' => 'مرفوض', 'suspended' => 'أوقفته الإدارة'];

    /** نسبة الخصم التي يحتاج فوقها كوبون البائع موافقة المدير */
    public const REVIEW_ABOVE_PERCENT = 50;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'value' => 'integer', 'max_discount' => 'integer', 'min_order_amount' => 'integer',
            'per_customer_limit' => 'integer', 'total_limit' => 'integer',
            'issued_count' => 'integer', 'redeemed_count' => 'integer', 'valid_days' => 'integer',
            'auto_followers' => 'boolean', 'reviewed_at' => 'datetime', 'followers_issued_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function buyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'buy_product_id');
    }

    public function giftProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'campaign_id');
    }

    public function scopeOfSeller($q, int $sellerProfileId)
    {
        return $q->where('funder', 'seller')->where('seller_profile_id', $sellerProfileId);
    }

    /** بعد أول نسخة في محفظة عميل لا تتغير قيمته ولا نطاقه ولا يُحذف. */
    public function isLocked(): bool
    {
        return $this->issued_count > 0;
    }

    /** لكوبونات البائع: العدد الإجمالي = عدد النسخ التي يمكن جمعها. */
    public function isSoldOut(): bool
    {
        return $this->total_limit !== null && $this->issued_count >= $this->total_limit;
    }

    /** يصل للمتابعين (كوبون متابعين، أو قابل للجمع مع خيار الإرسال للمتابعين). */
    public function reachesFollowers(): bool
    {
        return $this->audience === 'followers' || ($this->audience === 'collectable' && $this->auto_followers);
    }

    /** ما يراه العميل في محفظته حين لا يكون الكوبون فعّالاً بسبب المتجر أو الإدارة */
    public function campaignStateLabel(): ?string
    {
        return match ($this->status) {
            'paused' => $this->funder === 'seller' ? 'أوقفه المتجر مؤقتاً' : 'متوقف مؤقتاً',
            'suspended', 'rejected' => 'لم يعد متاحاً',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CouponCampaignTarget::class, 'campaign_id');
    }

    public function customerCoupons(): HasMany
    {
        return $this->hasMany(CustomerCoupon::class, 'campaign_id');
    }

    public function targetIds(string $type): array
    {
        return $this->targets->where('target_type', $type)->pluck('target_id')->map(fn ($id) => (int) $id)->all();
    }

    /** فعّال الآن: غير موقوف، ضمن الفترة، لم يبلغ العدد الإجمالي، ومتجره (إن وُجد) غير موقوف. */
    public function isRunning(): bool
    {
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gt(now()))
            && ($this->total_limit === null || $this->redeemed_count < $this->total_limit)
            && ($this->funder !== 'seller' || $this->seller?->status === 'approved');
    }

    public function scopeRunning($q)
    {
        return $q->where('status', 'active')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('total_limit')->orWhereColumn('redeemed_count', '<', 'total_limit'));
    }

    /** وصف القيمة للعرض: "خصم 20%" / "خصم 25,000 ل.س" / "شحن مجاني" */
    public function valueLabel(?string $locale = null): string
    {
        return match ($this->type) {
            'percentage' => $this->max_discount
                ? __('خصم :value% حتى :max ل.س', ['value' => $this->value, 'max' => number_format($this->max_discount / 100)], $locale)
                : __('خصم :value%', ['value' => $this->value], $locale),
            'fixed' => __('خصم :amount ل.س', ['amount' => number_format($this->value / 100)], $locale),
            'free_shipping' => __('شحن مجاني', [], $locale),
            'gift' => __('هدية: :product', ['product' => $this->giftProduct?->name ?? __('منتج', [], $locale)], $locale),
        };
    }

    public function sourceLabel(): string
    {
        if ($this->trigger === 'loyalty_points') {
            return __('من نقاط الولاء');
        }

        return $this->funder === 'platform' ? __('من تريندشام') : __('من متجر :store', ['store' => $this->seller?->store_name ?? '']);
    }
}
