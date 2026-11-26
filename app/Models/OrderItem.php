<?php

namespace App\Models;

use App\Returns\Models\ReturnItem;
use App\Returns\ReturnSettlement;
use App\Services\LedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'daily_deal_id',
        'vendor_id',
        'storefront_visit_id',
        'product_name',
        'variant_label',
        'unit_price',
        'quantity',
        'subtotal',
        'discount_amount',
        'discount_funder',
        'is_gift',
        'commission_amount',
        'vendor_earning',
        'commission_settled_at',
    ];

    protected $casts = ['is_gift' => 'boolean'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** بنود طلبات يراها البائع: طلب البطاقة يظهر بعد تأكيد الدفع فقط. */
    public function scopeReleasedToSellers(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $order) => $order->releasedToSellers());
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function storefrontVisit()
    {
        return $this->belongsTo(StorefrontVisit::class);
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }

    /** وحدات محجوزة لمرتجعات لم تُغلق (المرفوض يبقى حاجزًا حتى تغلقه المهمة اليومية) */
    public function heldReturnQuantity(): int
    {
        return (int) $this->returnItems()
            ->whereHas('returnRequest', fn ($q) => $q->where('status', '!=', 'closed'))
            ->sum('quantity');
    }

    /** وحدات عُكست آثارها فعلًا */
    public function reversedQuantity(): int
    {
        return (int) $this->returnItems()->whereNotNull('settled_at')->sum('quantity');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function sellerReviews()
    {
        return $this->hasMany(SellerReview::class);
    }

    public function canBeReviewed(): bool
    {
        return $this->order->status === 'delivered' && ! $this->review;
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal / 100).' ل.س';
    }

    /**
     * تسوية العمولة مرة واحدة فقط لكل عنصر طلب - إن استُدعيت مرتين (مثلاً المدير
     * غيّر حالة الطلب لـ delivered مرتين بالغلط) ما بتودع المبلغ بمحفظة البائع مرتين.
     */
    public function settleCommission(): void
    {
        if ($this->commission_settled_at) {
            return;
        }

        $commissionRate = $this->vendor->effective_commission_rate;
        $base = $this->settlementBase();

        // بند هدية أو مغطّى بكوبون البائع بالكامل: لا ربح ولا عمولة، ولا قيود بمبلغ صفر
        if ($base <= 0) {
            $this->update(['commission_amount' => 0, 'vendor_earning' => 0, 'commission_settled_at' => now()]);

            return;
        }
        $commissionAmount = (int) round($base * $commissionRate / 100);
        $vendorEarning = $base - $commissionAmount;

        $this->update([
            'commission_amount' => $commissionAmount,
            'vendor_earning' => $vendorEarning,
            'commission_settled_at' => now(),
        ]);

        $wallet = $this->vendor->walletOrCreate();
        $wallet->applyTransaction('credit', $vendorEarning, 'order_earning', [
            'order_item_id' => $this->id,
            'note' => "أرباح من طلب #{$this->order->order_number}",
        ]);

        // قيود الدفتر المحاسبي بنفس اللحظة وبنفس المبالغ: الدائن الإجمالي للمبيعات
        // والمدين للعمولة. المصدر order_item+id يضمن عدم التكرار عند إعادة الاستدعاء.
        $ledger = app(LedgerService::class);
        $sellerUserId = $this->vendor->user_id;
        $gross = round($base / 100, 2);

        $ledger->sale($sellerUserId, $gross, "مبيعات: طلب #{$this->order->order_number} - {$this->product_name}", 'order_item', $this->id);

        if ($commissionAmount > 0) {
            $ledger->commission($sellerUserId, round($commissionAmount / 100, 2), "عمولة: طلب #{$this->order->order_number} - {$this->product_name}", 'order_item', $this->id);
        }
    }

    /**
     * المبلغ الذي تُحسب منه العمولة والأرباح:
     * كوبون البائع يقلّل مبيعاته (السعر بعد الخصم)؛ كوبون المنصة لا يمسّه (المنصة تتحمّل الخصم).
     */
    public function settlementBase(): int
    {
        $discount = $this->discount_funder === 'seller' ? (int) $this->discount_amount : 0;

        return max(0, (int) $this->subtotal - $discount);
    }

    /**
     * قيد عكسي عند الاسترجاع (إلغاء الطلب): مدين بالمبيعات ودائن بإعادة العمولة.
     * يعكس الباقي فقط بعد طرح ما عكسته المرتجعات المستلمة لهذا البند؛ إن لم يبقَ شيء لا يفعل شيئاً.
     */
    public function reverseSettlement(): void
    {
        if (! $this->commission_settled_at || $this->settlementBase() <= 0) {
            return;
        }

        $returned = $this->returnItems()->whereNotNull('settled_at')
            ->get(['seller_reversal', 'commission_reversal']);
        $returnedGross = (int) $returned->sum('seller_reversal');
        $returnedCommission = (int) $returned->sum('commission_reversal');

        $grossMinor = $this->settlementBase() - $returnedGross;
        $commissionMinor = (int) $this->commission_amount - $returnedCommission;
        $earningMinor = (int) $this->vendor_earning - ($returnedGross - $returnedCommission);

        $ledger = app(LedgerService::class);
        $sellerUserId = $this->vendor->user_id;
        $desc = "استرجاع: طلب #{$this->order->order_number} - {$this->product_name}";

        DB::transaction(function () use ($ledger, $sellerUserId, $grossMinor, $commissionMinor, $earningMinor, $desc) {
            $wallet = $this->vendor->walletOrCreate();
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            // نخصم أرباح البائع من محفظته مرة واحدة فقط (المحفظة والدفتر يبقيان متطابقين).
            // قد يصبح الرصيد سالباً إن كان البائع سحب أرباحه؛ عندها لا يستطيع السحب حتى يُغطّى.
            // خصومات المرتجعات تحمل علامة سطرها فلا تُحسب خصماً كاملاً سابقاً.
            $alreadyReversed = $wallet->transactions()
                ->where('order_item_id', $this->id)->where('type', 'debit')
                ->where(fn ($q) => $q->whereNull('note')->orWhere('note', 'not like', '%'.ReturnSettlement::WALLET_MARKER_PREFIX.'%'))
                ->exists();
            if (! $alreadyReversed && $earningMinor > 0) {
                $wallet->applyTransaction('debit', $earningMinor, 'adjustment', [
                    'order_item_id' => $this->id,
                    'note' => $desc,
                ]);
            }

            $this->ledgerRefund($ledger, $sellerUserId, $grossMinor, $commissionMinor, $desc, 'order_item_refund', $this->id);
        });
    }

    /**
     * عكس أرباح الوحدات المرتجعة فقط (القاعدة 1): المحفظة تُخصم نصيب الأرباح
     * (seller_reversal − commission_reversal)، والدفتر قيد استرجاع وقيد إعادة عمولة بمصدر سطر الإرجاع.
     * آمن للتكرار: خصم المحفظة يحمل علامة السطر، وقيود الدفتر فريدة بالمصدر.
     */
    public function reverseSettlementQuantity(ReturnItem $line): void
    {
        if (! $this->commission_settled_at) {
            return;
        }

        $grossMinor = (int) $line->seller_reversal;
        $commissionMinor = (int) $line->commission_reversal;
        $earningMinor = $grossMinor - $commissionMinor;
        $marker = ReturnSettlement::walletMarker($line->id);
        $desc = "إرجاع #{$line->returnRequest->number}: طلب #{$this->order->order_number} - {$this->product_name}";

        DB::transaction(function () use ($line, $grossMinor, $commissionMinor, $earningMinor, $marker, $desc) {
            $wallet = $this->vendor->walletOrCreate();
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            $alreadyReversed = $wallet->transactions()
                ->where('order_item_id', $this->id)->where('type', 'debit')
                ->where('note', 'like', '%'.$marker)
                ->exists();
            if (! $alreadyReversed && $earningMinor > 0) {
                $wallet->applyTransaction('debit', $earningMinor, 'adjustment', [
                    'order_item_id' => $this->id,
                    'note' => mb_substr($desc, 0, 200).' '.$marker,
                ]);
            }

            $this->ledgerRefund(app(LedgerService::class), $this->vendor->user_id,
                $grossMinor, $commissionMinor, $desc, 'return_item', $line->id);
        });
    }

    /** المبالغ بالوحدة الصغرى وتُسجَّل في الدفتر بالليرة؛ لا قيد بمبلغ صفر. */
    private function ledgerRefund(LedgerService $ledger, int $sellerUserId, int $grossMinor, int $commissionMinor, string $desc, string $sourceType, int $sourceId): void
    {
        if ($grossMinor > 0) {
            $ledger->refund($sellerUserId, round($grossMinor / 100, 2), $desc, $sourceType, $sourceId);
        }
        if ($commissionMinor > 0) {
            $ledger->commissionReversal($sellerUserId, round($commissionMinor / 100, 2), $desc, $sourceType, $sourceId);
        }
    }
}
