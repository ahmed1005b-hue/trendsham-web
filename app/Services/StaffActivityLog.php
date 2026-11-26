<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffActivity;
use App\Models\StaffConversation;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * يكتب سطراً في سجل نشاط فريق المنصة. لا يسجّل إلا المدير والموظف المعتمد
 * (البائع الذي يؤكد استلام مرتجعه مثلاً لا يُسجَّل هنا).
 */
class StaffActivityLog
{
    /** @var array<class-string<Model>, string> */
    private const SUBJECT_TYPES = [
        Order::class => 'order',
        Product::class => 'product',
        StaffConversation::class => 'support',
        ChatConversation::class => 'chat',
        ReturnRequest::class => 'return',
    ];

    /** @param  array<string, scalar|null>  $properties */
    public function record(?User $actor, string $action, ?Model $subject = null, array $properties = []): ?StaffActivity
    {
        if ($actor === null || ! $actor->isPlatformStaff()) {
            return null;
        }

        return StaffActivity::create([
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'action' => $action,
            'subject_type' => $subject ? (self::SUBJECT_TYPES[$subject::class] ?? null) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** نفس السجل لكل عمليات الطلب: رقمه يظهر في الوصف. */
    public function order(?User $actor, string $action, Order $order, array $properties = []): void
    {
        $this->record($actor, $action, $order, ['order' => $order->order_number] + $properties);
    }

    public function product(?User $actor, string $action, Product $product, array $properties = []): void
    {
        $this->record($actor, $action, $product, ['name' => $product->name] + $properties);
    }

    public function support(?User $actor, string $action, StaffConversation $conversation): void
    {
        $this->record($actor, $action, $conversation, ['subject' => $conversation->subject]);
    }

    public function chat(?User $actor, string $action, ChatConversation $conversation): void
    {
        $this->record($actor, $action, $conversation, ['customer' => $conversation->user?->name ?? __('زائر')]);
    }

    public function returnRequest(?User $actor, string $action, ReturnRequest $returnRequest, array $properties = []): void
    {
        $this->record($actor, $action, $returnRequest, ['number' => $returnRequest->number] + $properties);
    }
}
