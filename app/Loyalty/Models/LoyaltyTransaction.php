<?php

namespace App\Loyalty\Models;

use App\Coupons\Models\CustomerCoupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * حركة نقاط في السجل الدائم. حركات الإضافة (earn/restore/admin_add) هي "دفعات" لها صلاحية ورصيد متبقٍّ.
 */
class LoyaltyTransaction extends Model
{
    public const UPDATED_AT = null;

    public const EARN = 'earn';

    public const RESTORE = 'restore';

    public const ADMIN_ADD = 'admin_add';

    public const REDEEM_CHECKOUT = 'redeem_checkout';

    public const REDEEM_COUPON = 'redeem_coupon';

    public const REVERSE_EARN = 'reverse_earn';

    public const EXPIRE = 'expire';

    public const ADMIN_DEDUCT = 'admin_deduct';

    public const DEBT_SETTLE = 'debt_settle';

    public const REFERRAL = 'referral';

    public const REFERRAL_REVERSE = 'referral_reverse';

    /** الأنواع التي تنشئ دفعة قابلة للاستهلاك */
    public const LOT_TYPES = [self::EARN, self::RESTORE, self::ADMIN_ADD, self::REFERRAL];

    protected $fillable = [
        'user_id', 'type', 'points', 'remaining', 'expires_at', 'expiry_notified_at',
        'order_id', 'customer_coupon_id', 'admin_id', 'reason', 'once_key',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer', 'remaining' => 'integer',
            'expires_at' => 'datetime', 'expiry_notified_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function customerCoupon()
    {
        return $this->belongsTo(CustomerCoupon::class);
    }

    public function allocations()
    {
        return $this->hasMany(LoyaltyAllocation::class, 'debit_transaction_id');
    }

    /** دفعات لها رصيد متبقٍّ ولم تنتهِ — مرتبة الأقدم انتهاءً أولاً */
    public function scopeActiveLots($q)
    {
        return $q->whereIn('type', self::LOT_TYPES)->where('remaining', '>', 0)
            ->where('expires_at', '>', now())->orderBy('expires_at')->orderBy('id');
    }

    /** وصف عربي للحركة في السجل */
    public function label(): string
    {
        $order = $this->order?->order_number;

        return match ($this->type) {
            self::EARN => __('من الطلب :order', ['order' => $order]),
            self::RESTORE => $this->customer_coupon_id ? __('إرجاع نقاط كوبون لم يُستخدم') : __('إرجاع نقاط الطلب الملغى :order', ['order' => $order]),
            self::ADMIN_ADD => __('إضافة من الإدارة').($this->reason ? ': '.$this->reason : ''),
            self::REDEEM_CHECKOUT => __('خصم على الطلب :order', ['order' => $order]),
            self::REDEEM_COUPON => __('تحويل إلى كوبون'),
            self::REVERSE_EARN => __('سحب نقاط الطلب المرتجع :order', ['order' => $order]),
            self::EXPIRE => __('انتهاء صلاحية'),
            self::ADMIN_DEDUCT => __('خصم من الإدارة').($this->reason ? ': '.$this->reason : ''),
            self::DEBT_SETTLE => __('سداد نقاط مستحقة من طلب مرتجع'),
            self::REFERRAL => __('مكافأة دعوة صديق'),
            self::REFERRAL_REVERSE => __('سحب مكافأة دعوة بسبب إرجاع الطلب'),
            default => $this->type,
        };
    }
}
