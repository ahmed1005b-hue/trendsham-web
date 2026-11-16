<?php

namespace App\Coupons\Models;

use Illuminate\Database\Eloquent\Model;

/** هدف الحملة: منتج أو قسم أو متجر (نطاق) أو عميل (جمهور "عميل معيّن"). */
class CouponCampaignTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'target_type', 'target_id'];
}
