<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffConversation;
use App\Returns\Models\ReturnRequest;

/** شغل اليوم في الصفحة الرئيسية للموظف (الموقع والتطبيق): ما ينتظر تصرّفاً الآن. */
class EmployeeWorkload
{
    /** طلب لم يُشحن بعد هذه المدة يُعدّ متأخراً. */
    public const LATE_AFTER_DAYS = 3;

    /**
     * @return array{new_orders: int, late_orders: int, pending_products: int, seller_support: int, customer_chats: int, returns: int}
     */
    public function counts(): array
    {
        $workOrders = fn () => Order::query()->releasedToSellers()->whereNull('archived_at');

        return [
            'new_orders' => $workOrders()->whereIn('status', ['pending', 'paid'])->count(),
            'late_orders' => $workOrders()
                ->whereIn('status', ['pending', 'paid', 'processing'])
                ->where('created_at', '<=', now()->subDays(self::LATE_AFTER_DAYS))
                ->count(),
            'pending_products' => Product::query()->pendingReview()->count(),
            // محادثات بائعين فيها رسالة منهم لم يقرأها أحد من فريق المنصة
            'seller_support' => StaffConversation::query()->open()
                ->whereHas('messages', fn ($messages) => $messages->whereNull('read_at')
                    ->whereHas('sender', fn ($sender) => $sender->where('role', 'seller')))
                ->count(),
            'customer_chats' => ChatConversation::query()->where('status', 'escalated')->whereNotNull('escalated_at')->count(),
            'returns' => ReturnRequest::query()->where('status', 'escalated')->count(),
        ];
    }
}
