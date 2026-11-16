<?php

namespace App\Http\Resources;

use App\Returns\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * طلب إرجاع للتطبيق. المبالغ بالوحدة الكبيرة (÷100) مثل OrderResource.
 * actions: أزرار مسموحة للمستخدم الحالي = السياسة + قاعدة الحالة.
 *
 * @mixin ReturnRequest
 */
class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = $this->status;
        $disputeOpen = $status === 'rejected' && $this->dispute_by !== null && ! $this->dispute_by->isPast();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $status,
            'status_label' => __(ReturnRequest::STATUS_LABELS[$status] ?? $status),
            'reason' => $this->reason,
            'reason_label' => __(ReturnRequest::REASON_LABELS[$this->reason] ?? $this->reason),
            'description' => $this->description,
            'decision_note' => $this->decision_note,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'store_name' => $this->whenLoaded('seller', fn () => $this->seller?->store_name),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'refund_amount' => $this->refund_amount / 100,
            'shipping_refund' => $this->shipping_refund / 100,
            'respond_by' => $this->respond_by?->toIso8601String(),
            'dispute_by' => $this->dispute_by?->toIso8601String(),
            'ship_back_by' => $this->ship_back_by?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($line) => [
                'id' => $line->id,
                'order_item_id' => $line->order_item_id,
                'product_name' => $line->orderItem?->product_name,
                'variant_label' => $line->orderItem?->variant_label,
                'quantity' => $line->quantity,
                'refund_amount' => $line->refund_amount / 100,
            ])->values()),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'label' => __(ReturnRequest::EVENT_LABELS[$event->type] ?? $event->type),
                'actor_role' => $event->actor_role,
                'note' => $event->note,
                'photos' => collect($event->photos ?? [])->map(fn ($path) => asset('storage/'.$path))->values(),
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()),
            'refund' => $this->whenLoaded('refund', fn () => $this->refund ? [
                'amount' => $this->refund->amount / 100,
                'method' => $this->refund->method,
                'method_label' => __($this->refund->methodLabel()),
                'reference' => $this->refund->reference,
            ] : null),
            'actions' => [
                'can_withdraw' => in_array($status, ['requested', 'approved'], true) && (bool) $user?->can('withdraw', $this->resource),
                'can_dispute' => $disputeOpen && (bool) $user?->can('dispute', $this->resource),
                'can_approve' => $status === 'requested' && (bool) $user?->can('decide', $this->resource),
                'can_reject' => $status === 'requested' && (bool) $user?->can('decide', $this->resource),
                'can_confirm_received' => $status === 'approved' && (bool) $user?->can('confirmReceived', $this->resource),
            ],
        ];
    }
}
