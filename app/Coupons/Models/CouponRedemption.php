<?php

namespace App\Coupons\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** استخدام كوبون في طلب: كم خصم ومن دفع. مصدر تقارير التكلفة والعكس عند الإلغاء. */
class CouponRedemption extends Model
{
    protected $fillable = ['customer_coupon_id', 'campaign_id', 'order_id', 'user_id', 'funder', 'seller_profile_id',
        'discount_amount', 'shipping_discount', 'status'];

    protected function casts(): array
    {
        return ['discount_amount' => 'integer', 'shipping_discount' => 'integer'];
    }

    public function customerCoupon(): BelongsTo
    {
        return $this->belongsTo(CustomerCoupon::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
