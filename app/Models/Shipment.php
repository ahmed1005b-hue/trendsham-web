<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «طرد متجر»: جزء الطلب الخاص بمتجر واحد، بحالته ورقم تتبعه وأجرة شحنه.
 * الطلب الذي فيه أكثر من متجر له طرد لكل متجر، وحالة الطلب تُحسب من حالات طروده.
 */
class Shipment extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'قيد المراجعة',
        'processing' => 'قيد التجهيز',
        'shipped' => 'تم الشحن',
        'in_transit' => 'في الطريق',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغي',
        'returned' => 'مرتجع',
    ];

    /** طرود انتهت دون تسليم: لا تدخل في حساب حالة الطلب */
    public const CLOSED = ['cancelled', 'returned'];

    protected $fillable = [
        'order_id',
        'seller_profile_id',
        'shipping_company_id',
        'tracking_number',
        'status',
        'shipping_fee',
        'shipping_cost',
        'refund_amount',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'refused_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refused_at' => 'datetime',
            'shipping_fee' => 'integer',
            'shipping_cost' => 'integer',
            'refund_amount' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function seller()
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function shippingCompany()
    {
        return $this->belongsTo(ShippingCompany::class)->withTrashed();
    }

    /** بنود الطلب التي تخص متجر هذا الطرد. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id')
            ->where('order_items.vendor_id', $this->seller_profile_id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
