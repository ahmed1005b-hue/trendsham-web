<?php

namespace App\Models;

use App\Coupons\CouponRedeemer;
use App\Coupons\SellerShippingCharge;
use App\GiftCards\GiftCardService;
use App\Loyalty\LoyaltyLedger;
use App\Referrals\ReferralService;
use App\Returns\Models\ReturnRequest;
use App\Services\DailyDealQuota;
use App\Shipping\ShippingDebt;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'coupon_id',
        'customer_coupon_id',
        'status',
        'payment_status',
        'archived_at',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'points_redeemed',
        'points_discount',
        'gift_balance_used',
        'total',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'delivery_method',
        'pickup_center_id',
        'shipping_cost',
        'shipping_debt_charged',
        'refused_at',
    ];

    public const STATUS_LABELS = [
        'pending' => 'قيد المراجعة',
        'paid' => 'مدفوع',
        'processing' => 'قيد التجهيز',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغي',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'refused_at' => 'datetime'];
    }

    public function pickupCenter()
    {
        return $this->belongsTo(ShippingCenter::class, 'pickup_center_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** آخر محاولة دفع للطلب (قد توجد محاولات سابقة فاشلة/ملغاة) */
    public function payment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total / 100).' ل.س';
    }

    public static function generateOrderNumber(): string
    {
        return 'TS-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
    }

    /**
     * تسوية مالية عند تغيير الحالة (ويب المدير + تطبيق المدير + الموظف — نفس القاعدة):
     *  - أول تسليم: تُضاف أرباح البائع لمحفظته وتُسجَّل قيود البيع والعمولة.
     *  - إلغاء بعد التسليم (استرجاع): تُخصم الأرباح من المحفظة وتُعكس القيود.
     */
    public function settleForStatusChange(string $previousStatus): void
    {
        if ($this->status === 'delivered' && $previousStatus !== 'delivered') {
            $this->items->each->settleCommission();
            app(LoyaltyLedger::class)->earnForOrder($this);   // نقاط الولاء (مرة واحدة لكل طلب)
            app(ReferralService::class)->rewardForDeliveredOrder($this); // مكافأة «دعوة صديق»
            app(SellerShippingCharge::class)->charge($this);  // شحن مجاني من كوبون البائع على البائع
        }

        // أول إلغاء من أي حالة: نعكس أرباحاً سُوّيت سابقاً (حتى لو أُرجع الطلب للخلف قبل الإلغاء)
        // ونرجع الكميات للمخزون. reverseSettlement لا يفعل شيئاً لبند لم يُسوَّ.
        if ($this->status === 'cancelled' && $previousStatus !== 'cancelled') {
            $this->items->each->reverseSettlement();
            $this->restockItems();
            app(DailyDealQuota::class)->release($this); // تعود قطع «عرض اليوم» للعرض
            app(CouponRedeemer::class)->reverse($this); // الكوبون يعود لمحفظة العميل
            app(SellerShippingCharge::class)->refund($this);  // تعود أجرة الشحن للبائع إن خُصمت

            // نقاط الولاء: تُسحب نقاط طلب مُسلَّم سابقاً (إرجاع)، وتعود النقاط المستبدلة فيه لدفعاتها
            $loyalty = app(LoyaltyLedger::class);
            $loyalty->reverseEarnForOrder($this);
            $loyalty->restoreRedeemedForOrder($this);
            app(ReferralService::class)->reverseForCancelledOrder($this);
            app(GiftCardService::class)->refundForOrder($this); // رصيد الهدايا يعود لصاحبه
            app(ShippingDebt::class)->restoreForCancelledOrder($this); // دين شحن سابق حُمّل على الطلب يعود على الزبون
        }
    }

    /**
     * المدخل الوحيد لتغيير حالة الطلب (المدير، الموظف، البائع، إلغاء العميل).
     * يقفل صف الطلب فلا يُنفَّذ الأثر المالي/المخزني مرتين عند طلبين متزامنين.
     *
     * @return string الحالة السابقة
     *
     * @throws \DomainException عند محاولة إعادة فتح طلب ملغى، أو إلغاء طلب عليه إرجاع مفتوح
     */
    public function changeStatus(string $to, ?callable $guard = null): string
    {
        return \DB::transaction(function () use ($to, $guard) {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === 'cancelled' && $to !== 'cancelled') {
                throw new \DomainException('لا يمكن إعادة فتح طلب ملغى، فقد أُعيدت كمياته للمخزون. أنشئ طلباً جديداً.');
            }

            // قواعد إضافية (مثل قواعد البائع) تُفحص على الحالة المقفولة لا على نسخة قديمة
            if ($guard) {
                $guard($locked);
            }

            // إرجاع مفتوح ينتظر قراراً أو استلاماً، أو مرفوض ما زالت مهلة النزاع عليه قائمة:
            // الإلغاء يعكس الطلب كله فيتعارض معه
            if ($to === 'cancelled' && $locked->returnRequests()->where(fn ($q) => $q->whereIn('status', ReturnRequest::OPEN)
                ->orWhere(fn ($disputable) => $disputable->where('status', 'rejected')->where('dispute_by', '>', now())))->exists()) {
                throw new \DomainException('لا يمكن إلغاء الطلب وعليه طلب إرجاع مفتوح.');
            }

            if ($from !== $to) {
                $locked->update(['status' => $to]);
                $locked->settleForStatusChange($from);
            }

            $this->setRawAttributes($locked->getAttributes(), true);
            $this->unsetRelation('items');

            return $from;
        });
    }

    private function restockItems(): void
    {
        // نفس ترتيب القفل في الدفع (المنتج ثم اللون/المقاس) حتى لا يتعطل إلغاء ودفع متزامنان.
        // الوحدات التي أعادتها المرتجعات المستلمة رجعت للمخزون سابقاً فلا تُعاد مرتين.
        foreach ($this->items as $item) {
            $quantity = (int) $item->quantity - $item->reversedQuantity();
            if ($quantity <= 0) {
                continue;
            }
            $item->product?->adjustStock($quantity, 'return', "إلغاء طلب #{$this->order_number}");
            if ($item->variant_id) {
                ProductVariant::whereKey($item->variant_id)->increment('stock_quantity', $quantity);
            }
        }
    }

    /** طلب كل منتجاته من بائع واحد؟ (البائع لا يغيّر حالة طلب مشترك مع متاجر أخرى) */
    public function isSingleVendor(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->pluck('vendor_id')->unique()->count() === 1;
        }

        return $this->items()->distinct()->count('vendor_id') === 1;
    }
}
