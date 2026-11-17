<?php

namespace App\Shipping;

use App\GiftCards\GiftCardService;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Shipment;
use App\Returns\Models\ReturnRequest;
use App\Services\DailyDealQuota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * طرد لكل متجر داخل الطلب. هنا وحده يتغيّر طرد ويُحسب أثره:
 *  - تسليم الطرد: تُضاف أرباح بنوده لمحفظة بائعه.
 *  - إلغاء الطرد: تعود بنوده للمخزون وتُعكس أرباحها، ويُطرح من الطلب ما يخصه؛
 *    ما دُفع مسبقاً عنه يعود لرصيد الهدايا (ومن يريده على بطاقته يطلبه من الإدارة).
 *  - حالة الطلب الكاملة تُحسب من حالات طروده، ومعها آثار الطلب (النقاط، الدعوة، الكوبون...).
 * المبالغ بالوحدة الصغرى ×100.
 */
class ParcelService
{
    /** ترتيب التقدّم؛ «في الطريق» بمنزلة «تم الشحن» */
    public const RANK = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'in_transit' => 2, 'delivered' => 3];

    public function __construct(
        private readonly GiftCardService $gifts,
        private readonly DailyDealQuota $deals,
    ) {}

    /**
     * يتأكد أن لكل متجر في الطلب طرداً (طلبات قديمة أو أُنشئت دون طرود)، ويعيدها مفهرسة بالمتجر.
     *
     * @return Collection<int, Shipment>
     */
    public function ensureFor(Order $order): Collection
    {
        $parcels = $order->shipments()->get()->keyBy('seller_profile_id');
        $vendors = $order->items()->whereNotNull('vendor_id')->distinct()->orderBy('vendor_id')->pluck('vendor_id');

        foreach ($vendors as $vendorId) {
            if (! $parcels->has($vendorId)) {
                $first = $parcels->isEmpty();
                $parcels[$vendorId] = Shipment::firstOrCreate(
                    ['order_id' => $order->id, 'seller_profile_id' => $vendorId],
                    [
                        'status' => $this->statusFromOrder($order->status),
                        // طلب بلا طرود: أجرته كلها على أول طرد
                        'shipping_fee' => $first ? (int) $order->shipping_fee : 0,
                        'shipping_cost' => $first ? (int) max($order->shipping_cost, $order->shipping_fee) : 0,
                    ],
                );
            }
        }

        return $parcels;
    }

    /**
     * عند إنشاء الطلب: طرد لكل متجر بأجرته. خصم الشحن (كوبون شحن مجاني) يُطرح من الطرود بالترتيب.
     *
     * @param  list<array{vendor_id: int, fee: int}>  $parcels  من ShippingQuote
     */
    public function createForCheckout(Order $order, array $parcels, int $shippingDiscount): void
    {
        $left = max(0, $shippingDiscount);
        foreach ($parcels as $parcel) {
            $off = min($left, (int) $parcel['fee']);
            $left -= $off;
            Shipment::create([
                'order_id' => $order->id,
                'seller_profile_id' => $parcel['vendor_id'],
                'status' => 'pending',
                'shipping_fee' => (int) $parcel['fee'] - $off,
                'shipping_cost' => (int) $parcel['fee'],
            ]);
        }
    }

    /**
     * يغيّر طرداً واحداً (البائع أو الإدارة) ثم يحسب حالة الطلب.
     *
     * @param  callable(Shipment, Order): void|null  $guard  قواعد إضافية تُفحص على الحالة المقفولة
     *
     * @throws \DomainException
     */
    public function change(Shipment $parcel, string $to, ?callable $guard = null): Shipment
    {
        $result = DB::transaction(function () use ($parcel, $to, $guard) {
            $order = Order::whereKey($parcel->order_id)->lockForUpdate()->firstOrFail();
            $locked = Shipment::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (in_array($from, Shipment::CLOSED, true) && $to !== $from) {
                throw new \DomainException('لا يمكن إعادة فتح طرد ملغى، فقد أُعيدت كمياته للمخزون.');
            }
            if ($guard) {
                $guard($locked, $order);
            }
            if ($from === $to) {
                return [$locked, false];
            }
            if ($to === 'cancelled') {
                $this->assertNoOpenReturn($order);
            }

            $this->move($order, $locked, $to, partial: true);
            $this->syncOrderStatus($order);

            return [$locked, true];
        });

        [$parcel, $changed] = $result;
        if ($changed) {
            $parcel->loadMissing('seller', 'order');
            AppNotification::send($parcel->order->user_id, 'shipment_updated',
                'تحديث على طلبك #'.$parcel->order->order_number,
                'طرد '.($parcel->seller?->store_name ?? 'المتجر').': '.$parcel->statusLabel()
                    .($parcel->tracking_number ? ' - رقم التتبع: '.$parcel->tracking_number : ''),
                route('customer.orders.show', $parcel->order_id));
        }

        return $parcel;
    }

    /**
     * ينقل كل طرود الطلب المفتوحة إلى حالة الطلب الجديدة (تغيير الطلب كاملاً من الإدارة أو العميل).
     * يُستدعى من Order::changeStatus تحت قفل الطلب؛ آثار الطلب نفسه تبقى هناك.
     */
    public function cascade(Order $order, string $to): void
    {
        $status = $this->statusFromOrder($to);
        foreach ($this->ensureFor($order) as $parcel) {
            if ($parcel->isOpen() && $parcel->status !== $status) {
                $this->move($order, Shipment::whereKey($parcel->id)->lockForUpdate()->first(), $status, partial: false);
            }
        }
    }

    /** آثار الطرد نفسه. partial=false عند إلغاء الطلب كله: آثار المال تبقى لإلغاء الطلب كما كانت. */
    private function move(Order $order, Shipment $parcel, string $to, bool $partial): void
    {
        $from = $parcel->status;
        $parcel->fill(['status' => $to]);
        if (in_array($to, ['shipped', 'in_transit', 'delivered'], true) && ! $parcel->shipped_at) {
            $parcel->shipped_at = now();
        }
        if ($to === 'delivered' && ! $parcel->delivered_at) {
            $parcel->delivered_at = now();
        }
        if ($to === 'cancelled') {
            $parcel->cancelled_at = now();
        }
        $parcel->save();

        $items = $order->items()->where('vendor_id', $parcel->seller_profile_id)->get();

        if ($to === 'delivered' && $from !== 'delivered') {
            $items->each->settleCommission();
        }

        if ($to === 'cancelled' && $from !== 'cancelled') {
            $items->each->reverseSettlement();
            $order->restock($items);
            $this->deals->releaseItems($items);

            // إلغاء جزء من الطلب وبقية الطرود ماضية: يُطرح ما يخصه من الطلب ويُعاد ما دُفع عنه
            $othersOpen = $order->shipments()->open()->whereKeyNot($parcel->id)->exists();
            if ($partial && $othersOpen) {
                $this->settlePartialCancel($order, $parcel, $items);
            }
        }
    }

    /**
     * قيمة الطرد = بنوده بعد خصم الكوبون + أجرة شحنه.
     * غير مدفوع: يُطرح من المبلغ المستحق، وما زاد (إن كان رصيد هدايا قد غطّاه) يعود للرصيد.
     * مدفوع: يعود كله لرصيد الهدايا، بحد ما دُفع فعلاً.
     */
    private function settlePartialCancel(Order $order, Shipment $parcel, Collection $items): void
    {
        $goods = (int) $items->sum(fn ($i) => (int) $i->subtotal - (int) $i->discount_amount);
        $value = $goods + (int) $parcel->shipping_fee;

        if ($order->payment_status === 'paid') {
            $alreadyReturned = (int) $order->shipments()->sum('refund_amount');
            $credit = max(0, min($value, (int) $order->total + (int) $order->gift_balance_used - $alreadyReturned));
            $reduce = 0;
        } else {
            $reduce = min((int) $order->total, $value);
            $credit = min($value - $reduce, $this->gifts->refundableForOrder($order));
        }

        $order->update([
            'subtotal' => max(0, (int) $order->subtotal - (int) $items->sum('subtotal')),
            'discount_amount' => max(0, (int) $order->discount_amount - (int) $items->sum('discount_amount')),
            'shipping_fee' => max(0, (int) $order->shipping_fee - (int) $parcel->shipping_fee),
            'total' => max(0, (int) $order->total - $reduce - $credit),
        ]);
        if ($credit > 0) {
            $this->gifts->creditForCancelledParcel($order, $credit, $parcel->id);
        }
        $parcel->update(['refund_amount' => $reduce + $credit]);
    }

    /** حالة الطلب من طروده، ثم آثار الطلب إن تغيّرت (تحت قفل الطلب). */
    private function syncOrderStatus(Order $order): void
    {
        $open = $order->shipments()->open()->pluck('status');
        $from = $order->status;

        $to = match (true) {
            $open->isEmpty() => 'cancelled',
            $open->every(fn ($s) => $s === 'delivered') => 'delivered',
            $open->contains(fn ($s) => self::RANK[$s] >= 2) => 'shipped',
            $open->contains('processing') => 'processing',
            default => in_array($from, ['pending', 'paid'], true) ? $from : 'pending',
        };

        if ($to !== $from) {
            $order->update(['status' => $to]);
            $order->settleForStatusChange($from);
        }
    }

    /** هل وصلت بعض الطرود ولم تصل أخرى بعد؟ (للعرض: «شُحن جزئياً») */
    public static function isPartiallyShipped(Order $order): bool
    {
        $open = $order->shipments->filter->isOpen();

        return $open->count() > 1
            && $open->contains(fn ($p) => (self::RANK[$p->status] ?? 0) >= 2)
            && $open->contains(fn ($p) => (self::RANK[$p->status] ?? 0) < 2);
    }

    private function statusFromOrder(string $orderStatus): string
    {
        return match ($orderStatus) {
            'processing', 'shipped', 'delivered', 'cancelled' => $orderStatus,
            default => 'pending',
        };
    }

    private function assertNoOpenReturn(Order $order): void
    {
        $open = $order->returnRequests()->where(fn ($q) => $q->whereIn('status', ReturnRequest::OPEN)
            ->orWhere(fn ($disputable) => $disputable->where('status', 'rejected')->where('dispute_by', '>', now())))->exists();
        if ($open) {
            throw new \DomainException('لا يمكن إلغاء الطلب وعليه طلب إرجاع مفتوح.');
        }
    }
}
