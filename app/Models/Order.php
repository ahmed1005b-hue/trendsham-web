<?php

namespace App\Models;

use App\Coupons\CouponRedeemer;
use App\Coupons\SellerShippingCharge;
use App\GiftCards\GiftCardService;
use App\Loyalty\LoyaltyLedger;
use App\Referrals\ReferralService;
use App\Returns\Models\ReturnRequest;
use App\Shipping\ParcelService;
use App\Shipping\ShippingDebt;
use Illuminate\Database\Eloquent\Builder;
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
        'shipping_governorate',
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
        return $this->belongsTo(Coupon::class)->withTrashed();
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

    /** مهلة إتمام الدفع بالبطاقة قبل إلغاء الطلب تلقائياً (بالدقائق). */
    public const ONLINE_PAYMENT_TIMEOUT_MINUTES = 30;

    /** أقصى عدد طلبات بطاقة غير مدفوعة مفتوحة للعميل: تمنع حجز مخزون المتاجر بطلبات لا تُدفع. */
    public const MAX_OPEN_CARD_ORDERS = 3;

    public static function openCardOrdersCount(int $userId): int
    {
        return static::awaitingOnlinePayment()->where('user_id', $userId)->where('status', 'pending')->count();
    }

    /** هل انتهى وقت بدء دفع جديد لطلب البطاقة؟ (لا يُمدَّد حجز المخزون بمحاولات متكررة) */
    public function onlinePaymentWindowClosed(): bool
    {
        return $this->created_at->lte(now()->subMinutes(self::ONLINE_PAYMENT_TIMEOUT_MINUTES))
            && $this->isAwaitingOnlinePayment();
    }

    /**
     * طلب بطاقة لم يؤكّد البنك دفعه بعد: لا يراه البائع ولا يجهّزه.
     * الدفع عند الاستلام، والطلب المدفوع (ولو برصيد الهدايا)، ظاهران للبائع فوراً.
     */
    public function scopeAwaitingOnlinePayment(Builder $query): Builder
    {
        // إن نقلت الإدارة الطلب للتجهيز (دفع أكّدته بنفسها) فهو للبائع؛ والملغى قبل الدفع لا يصله أبداً
        return $query->whereIn($query->qualifyColumn('status'), ['pending', 'cancelled'])
            ->where($query->qualifyColumn('payment_status'), '!=', 'paid')
            ->whereHas('payments', fn (Builder $payments) => $payments->where('method', 'card'))
            ->whereDoesntHave('payments', fn (Builder $payments) => $payments
                ->where('method', 'cash_on_delivery')->orWhere('status', 'paid'));
    }

    /** الطلبات التي يراها البائع ويجهّزها (كل طلب إلا ما ينتظر الدفع بالبطاقة). */
    public function scopeReleasedToSellers(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $awaiting) => $awaiting->awaitingOnlinePayment());
    }

    public function isAwaitingOnlinePayment(): bool
    {
        return static::whereKey($this->id)->awaitingOnlinePayment()->exists();
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
     * الدفع عند الاستلام: تسليم الطلب يعني أن المندوب قبض المبلغ، فيصير الطلب «مدفوعاً»
     * (كان يبقى «غير مدفوع» حتى يضغط المدير الزر بيده، فتختل التقارير والمرتجعات).
     */
    private function collectCashOnDelivery(): void
    {
        if ($this->payment_status === 'paid') {
            return;
        }

        $cash = $this->payments()->where('method', 'cash_on_delivery')->where('status', 'pending')->latest('id')->first();
        if (! $cash) {
            return;
        }

        // المبلغ المقبوض فعلاً = إجمالي الطلب الآن (قد يكون أقل من وقت الطلب إن أُلغي طرد منه)
        $cash->update([
            'status' => 'paid', 'paid_at' => now(), 'amount' => (int) $this->total,
            'transaction_ref' => $cash->transaction_ref ?: 'cod-delivered',
        ]);
        $this->update(['payment_status' => 'paid']);
    }

    /**
     * تسوية مالية عند تغيير الحالة (ويب المدير + تطبيق المدير + الموظف — نفس القاعدة):
     *  - أول تسليم: تُضاف أرباح البائع لمحفظته وتُسجَّل قيود البيع والعمولة.
     *  - إلغاء بعد التسليم (استرجاع): تُخصم الأرباح من المحفظة وتُعكس القيود.
     */
    public function settleForStatusChange(string $previousStatus): void
    {
        // أرباح البائعين وإرجاع الكميات تخص كل طرد وحده (ParcelService)؛ هنا آثار الطلب كاملاً
        if ($this->status === 'delivered' && $previousStatus !== 'delivered') {
            $this->collectCashOnDelivery();
            app(LoyaltyLedger::class)->earnForOrder($this);   // نقاط الولاء (مرة واحدة لكل طلب)
            app(ReferralService::class)->rewardForDeliveredOrder($this); // مكافأة «دعوة صديق»
            app(SellerShippingCharge::class)->charge($this);  // شحن مجاني من كوبون البائع على البائع
        }

        // أول إلغاء من أي حالة: نعكس أرباحاً سُوّيت سابقاً (حتى لو أُرجع الطلب للخلف قبل الإلغاء)
        // ونرجع الكميات للمخزون. reverseSettlement لا يفعل شيئاً لبند لم يُسوَّ.
        if ($this->status === 'cancelled' && $previousStatus !== 'cancelled') {
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
                // كل طرود الطلب المفتوحة تنتقل معه (أرباح كل بائع، إرجاع الكميات)
                app(ParcelService::class)->cascade($locked, $to);
                $locked->update(['status' => $to]);
                $locked->settleForStatusChange($from);
            }

            $this->setRawAttributes($locked->getAttributes(), true);
            $this->unsetRelation('items');

            return $from;
        });
    }

    /** يعيد كميات بنود (طرد ملغى) للمخزون. */
    public function restock(iterable $items): void
    {
        // نفس ترتيب القفل في الدفع (المنتج ثم اللون/المقاس) حتى لا يتعطل إلغاء ودفع متزامنان.
        // الوحدات التي أعادتها المرتجعات المستلمة رجعت للمخزون سابقاً فلا تُعاد مرتين.
        foreach ($items as $item) {
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
