<?php

namespace App\GiftCards\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftBalanceTransaction extends Model
{
    public const REDEEM = 'redeem';

    public const SPEND = 'spend';

    public const REFUND = 'refund';

    protected $fillable = ['user_id', 'type', 'amount', 'balance_after', 'gift_card_id', 'order_id', 'once_key'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        $order = $this->order?->order_number;

        return match ($this->type) {
            self::REDEEM => __('إضافة بطاقة هدية'),
            self::SPEND => __('دفع الطلب :order', ['order' => $order]),
            self::REFUND => str_starts_with((string) $this->once_key, 'return_refund:')
                ? __('استرداد منتجات مرتجعة من الطلب :order', ['order' => $order])
                : __('إرجاع رصيد الطلب الملغى :order', ['order' => $order]),
            default => __('إرجاع رصيد الطلب الملغى :order', ['order' => $order]),
        };
    }
}
