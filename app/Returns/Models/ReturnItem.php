<?php

namespace App\Returns\Models;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** بند مرتجع: الكمية ومبالغها المحسوبة مرة واحدة عند الإنشاء. settled_at = حارس التنفيذ مرة واحدة. */
class ReturnItem extends Model
{
    protected $fillable = [
        'return_request_id', 'order_item_id', 'quantity', 'refund_amount',
        'seller_reversal', 'commission_reversal', 'settled_at',
    ];

    protected function casts(): array
    {
        return ['settled_at' => 'datetime'];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
