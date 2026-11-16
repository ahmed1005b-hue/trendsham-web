<?php

namespace App\Returns;

use App\Models\AppNotification;
use App\Models\User;
use App\Returns\Models\ReturnRequest;

/**
 * إشعارات المرتجعات (داخل التطبيق + الدفع) وروابط الويب.
 *
 * العناوين تُخزَّن بالعربية من مفاتيح قاموس فيها :number (lang/en.json و tr.json)،
 * فيترجمها MessageTranslator بلغة المستلم عند العرض وعند إرسال إشعار الهاتف.
 */
class ReturnNotifier
{
    public function requested(ReturnRequest $request): void
    {
        $this->toSeller($request, 'return_requested', $this->title('طلب إرجاع جديد :number', $request), 'لديك طلب إرجاع بانتظار ردك.');
    }

    public function approved(ReturnRequest $request): void
    {
        $this->toCustomer($request, 'return_approved', $this->title('تمت الموافقة على إرجاعك :number', $request), 'يرجى شحن المنتج إلى البائع خلال المهلة المحددة.');
    }

    public function rejected(ReturnRequest $request): void
    {
        $this->toCustomer($request, 'return_rejected', $this->title('رفض البائع طلب الإرجاع :number', $request), 'يمكنك فتح نزاع خلال المهلة المحددة.');
    }

    /** إحالة تلقائية بعد مهلة البائع: للطرفين والإدارة (القسم 8). */
    public function escalated(ReturnRequest $request): void
    {
        $title = $this->title('طلب الإرجاع :number تحت مراجعة الإدارة', $request);
        $this->toCustomer($request, 'return_escalated', $title);
        $this->toSeller($request, 'return_escalated', $title);
        $this->toAdmins($request, 'return_escalated', $this->title('طلب إرجاع :number أُحيل للإدارة لعدم رد البائع', $request), 'يحتاج قراراً من الإدارة.');
    }

    /** نزاع من الزبون: للبائع والإدارة (القسم 8). */
    public function disputed(ReturnRequest $request): void
    {
        $title = $this->title('فتح الزبون نزاعًا على الإرجاع :number', $request);
        $this->toSeller($request, 'return_disputed', $title);
        $this->toAdmins($request, 'return_disputed', $title, 'يحتاج قراراً من الإدارة.');
    }

    public function adminDecided(ReturnRequest $request): void
    {
        $title = $this->title('قرار الإدارة في الإرجاع :number', $request);
        $this->toCustomer($request, 'return_admin_decided', $title, $request->decision_note);
        $this->toSeller($request, 'return_admin_decided', $title, $request->decision_note);
    }

    public function received(ReturnRequest $request): void
    {
        $this->toCustomer($request, 'return_received', $this->title('استلم البائع المنتج المرتجع :number', $request));
    }

    public function refunded(ReturnRequest $request): void
    {
        $this->toCustomer($request, 'return_refunded', $this->title('تم استرداد مبلغ الإرجاع :number', $request));
    }

    public function closed(ReturnRequest $request): void
    {
        $title = $this->title('أُغلق طلب الإرجاع :number', $request);
        $this->toCustomer($request, 'return_closed', $title);
        $this->toSeller($request, 'return_closed', $title);
    }

    /** مفتاح القاموس بعد وضع رقم المرتجع مكان :number. */
    private function title(string $key, ReturnRequest $request): string
    {
        return strtr($key, [':number' => $request->number]);
    }

    private function toSeller(ReturnRequest $request, string $type, string $title, ?string $body = null): void
    {
        $userId = $request->seller?->user_id;
        if ($userId) {
            AppNotification::send($userId, $type, $title, $body, url('/seller/returns/'.$request->id));
        }
    }

    private function toCustomer(ReturnRequest $request, string $type, string $title, ?string $body = null): void
    {
        AppNotification::send($request->user_id, $type, $title, $body, url('/customer/returns/'.$request->id));
    }

    private function toAdmins(ReturnRequest $request, string $type, string $title, ?string $body = null): void
    {
        User::where('role', 'admin')->pluck('id')->each(
            fn (int $adminId) => AppNotification::send($adminId, $type, $title, $body, url('/admin/returns/'.$request->id))
        );
    }
}
