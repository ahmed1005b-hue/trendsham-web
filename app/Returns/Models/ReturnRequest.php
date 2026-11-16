<?php

namespace App\Returns\Models;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** طلب إرجاع: بنود بائع واحد من طلب واحد. المبالغ بالوحدة الصغرى ×100. */
class ReturnRequest extends Model
{
    public const STATUSES = ['requested', 'approved', 'rejected', 'escalated', 'received', 'refunded', 'closed'];

    public const FINAL = ['refunded', 'closed'];

    public const OPEN = ['requested', 'approved', 'escalated'];

    /** حالات نُفِّذ فيها أثر الاستلام (المال والمخزون). */
    public const SETTLED = ['received', 'refunded'];

    public const REASONS = ['damaged', 'not_as_described', 'wrong_size_or_color', 'changed_mind', 'other'];

    public const REASON_LABELS = [
        'damaged' => 'المنتج تالف',
        'not_as_described' => 'لا يطابق الوصف',
        'wrong_size_or_color' => 'مقاس أو لون خاطئ',
        'changed_mind' => 'غيّرت رأيي',
        'other' => 'سبب آخر',
    ];

    public const EVENT_LABELS = [
        'requested' => 'تم إرسال الطلب',
        'withdrawn' => 'سحب الزبون الطلب',
        'approved' => 'وافق البائع',
        'rejected' => 'رفض البائع',
        'disputed' => 'رفع الزبون نزاعاً',
        'escalated' => 'تحويل للإدارة',
        'admin_approved' => 'وافقت الإدارة',
        'admin_rejected' => 'رفضت الإدارة',
        'received' => 'تم استلام المنتجات',
        'refunded' => 'تم الاسترداد',
        'closed' => 'تم إغلاق الطلب',
    ];

    public const SELLER_FAULT = ['damaged', 'not_as_described'];

    public const STATUS_LABELS = [
        'requested' => 'بانتظار رد البائع',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'escalated' => 'تحت مراجعة الإدارة',
        'received' => 'تم الاستلام',
        'refunded' => 'تم الاسترداد',
        'closed' => 'مغلق',
    ];

    protected $fillable = [
        'number', 'order_id', 'user_id', 'seller_profile_id', 'status', 'reason', 'description',
        'refund_amount', 'shipping_refund', 'respond_by', 'dispute_by', 'ship_back_by',
        'decided_by', 'decision_note', 'received_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'respond_by' => 'datetime',
            'dispute_by' => 'datetime',
            'ship_back_by' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnEvent::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(ReturnRefund::class);
    }
}
