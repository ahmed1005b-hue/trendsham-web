<?php

namespace App\Returns;

use App\GiftCards\GiftCardService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRefund;
use App\Returns\Models\ReturnRequest;
use App\Services\ImageUploadService;
use App\Services\StaffActivityLog;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** نقطة الدخول الوحيدة لتغيير حالات المرتجعات. */
class ReturnService
{
    public const MAX_PHOTOS = 5;

    public function __construct(
        private readonly ReturnSettings $settings,
        private readonly RefundCalculator $calculator,
        private readonly ReturnNotifier $notifier,
        private readonly ImageUploadService $images,
        private readonly ReturnSettlement $settlement,
    ) {}

    /**
     * @param  array<int, int>  $lines  [order_item_id => quantity]
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(User $customer, Order $order, array $lines, string $reason, ?string $description, array $photos = []): ReturnRequest
    {
        if ($order->user_id !== $customer->id) {
            throw new NotFoundHttpException;
        }
        if (! in_array($reason, ReturnRequest::REASONS, true)) {
            throw new \DomainException('سبب الإرجاع غير صالح.');
        }
        if (count($photos) > self::MAX_PHOTOS) {
            throw new \DomainException(strtr('الحد الأقصى :max صور لكل طلب.', [':max' => self::MAX_PHOTOS]));
        }
        $lines = array_filter(array_map('intval', $lines), fn (int $qty) => $qty > 0);
        if ($lines === []) {
            throw new \DomainException('اختر منتجًا واحدًا على الأقل للإرجاع.');
        }

        $paths = [];
        try {
            $paths = $this->storePhotos($photos);

            $request = $this->persist($customer, $order, $lines, $reason, $description, $paths);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);

            throw $e;
        }

        $this->notify(fn () => $this->notifier->requested($request));

        return $request;
    }

    /** الكمية القابلة للإرجاع الآن لبند: الطلب مُسلَّم، والبند مُسوّى ضمن المهلة، والباقي بعد المحجوز. */
    public function returnableQuantity(OrderItem $item): int
    {
        if ($item->order?->status !== 'delivered' || $item->commission_settled_at === null) {
            return 0;
        }
        if (Carbon::parse($item->commission_settled_at)->addDays($this->settings->windowDays())->isPast()) {
            return 0;
        }

        return max(0, (int) $item->quantity - $item->heldReturnQuantity());
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string>
     */
    private function storePhotos(array $photos): array
    {
        $paths = [];
        try {
            foreach ($photos as $photo) {
                $path = $this->images->store($photo, 'returns');
                if ($path === null) {
                    throw new \DomainException('إحدى الصور غير صالحة.');
                }
                $paths[] = $path;
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);

            throw $e;
        }

        return $paths;
    }

    /**
     * @param  array<int, int>  $lines
     * @param  array<int, string>  $paths
     */
    private function persist(User $customer, Order $order, array $lines, string $reason, ?string $description, array $paths): ReturnRequest
    {
        return DB::transaction(function () use ($customer, $order, $lines, $reason, $description, $paths) {
            // صف الطلب أولاً ثم سطوره، بنفس ترتيب الأقفال في الإلغاء والاستلام، وحالته تُقرأ من الصف المقفول
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereIn('id', array_keys($lines))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($lines)) {
                throw new \DomainException('بعض المنتجات المختارة لا تخص هذا الطلب.');
            }
            if ($lockedOrder->status !== 'delivered' || $items->contains(fn (OrderItem $item) => $item->commission_settled_at === null)) {
                throw new \DomainException('لا يمكن إرجاع طلب لم يُسلَّم.');
            }
            $windowDays = $this->settings->windowDays();
            if ($items->contains(fn (OrderItem $item) => Carbon::parse($item->commission_settled_at)->addDays($windowDays)->isPast())) {
                throw new \DomainException('انتهت مدة الإرجاع لهذا الطلب.');
            }
            if ($items->pluck('vendor_id')->unique()->count() !== 1) {
                throw new \DomainException('طلب الإرجاع يجب أن يخص متجراً واحداً.');
            }
            foreach ($items as $item) {
                if ($lines[$item->id] > $this->returnableQuantity($item)) {
                    throw new \DomainException('الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.');
                }
            }

            $request = ReturnRequest::create([
                'number' => $this->generateNumber(),
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'seller_profile_id' => $items->first()->vendor_id,
                'status' => 'requested',
                'reason' => $reason,
                'description' => $description,
                'refund_amount' => 0,
                'shipping_refund' => 0,
                'respond_by' => now()->addDays($this->settings->sellerResponseDays()),
            ]);

            // أجرة التوصيل لا تُضاف هنا: تُحمَّل عند الاستلام على المرتجع الذي يُكمل إرجاع الطلب (ReturnSettlement)
            $sum = 0;
            foreach ($lines as $itemId => $qty) {
                $priced = $this->calculator->forLine($items[$itemId], $qty);
                $request->items()->create([
                    'order_item_id' => $itemId,
                    'quantity' => $qty,
                    'refund_amount' => $priced['refund'],
                    'seller_reversal' => $priced['seller_reversal'],
                    'commission_reversal' => $priced['commission_reversal'],
                ]);
                $sum += $priced['refund'];
            }

            $request->update(['refund_amount' => $sum]);

            $request->events()->create([
                'actor_id' => $customer->id,
                'actor_role' => 'customer',
                'type' => 'requested',
                'note' => $description,
                'photos' => $paths,
            ]);

            return $request;
        });
    }

    public function withdraw(User $customer, ReturnRequest $returnRequest): ReturnRequest
    {
        if ($returnRequest->user_id !== $customer->id) {
            throw new NotFoundHttpException;
        }

        return DB::transaction(function () use ($customer, $returnRequest) {
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['requested', 'approved'], true)) {
                throw new \DomainException('لا يمكن سحب طلب الإرجاع في حالته الحالية.');
            }

            $locked->update(['status' => 'closed', 'closed_at' => now()]);
            $locked->events()->create(['actor_id' => $customer->id, 'actor_role' => 'customer', 'type' => 'withdrawn']);

            return $locked;
        });
    }

    public function sellerApprove(SellerProfile $seller, ReturnRequest $returnRequest, ?string $note = null): ReturnRequest
    {
        $this->assertOwnedBy($seller, $returnRequest);

        $locked = $this->transition($returnRequest, ['requested'], function (ReturnRequest $r) use ($seller, $note) {
            $r->update(['status' => 'approved', 'ship_back_by' => now()->addDays($this->settings->shipBackDays())]);
            $this->event($r, $seller->user_id, 'seller', 'approved', $note);
        });
        $this->notify(fn () => $this->notifier->approved($locked));

        return $locked;
    }

    public function sellerReject(SellerProfile $seller, ReturnRequest $returnRequest, string $note): ReturnRequest
    {
        $this->assertOwnedBy($seller, $returnRequest);
        $this->requireNote($note);

        $locked = $this->transition($returnRequest, ['requested'], function (ReturnRequest $r) use ($seller, $note) {
            $r->update([
                'status' => 'rejected',
                'dispute_by' => now()->addDays($this->settings->disputeDays()),
                'decision_note' => $note,
            ]);
            $this->event($r, $seller->user_id, 'seller', 'rejected', $note);
        });
        $this->notify(fn () => $this->notifier->rejected($locked));

        return $locked;
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function dispute(User $customer, ReturnRequest $returnRequest, string $note, array $photos = []): ReturnRequest
    {
        if ($returnRequest->user_id !== $customer->id) {
            throw new NotFoundHttpException;
        }
        $this->requireNote($note);
        if (count($photos) > self::MAX_PHOTOS) {
            throw new \DomainException(strtr('الحد الأقصى :max صور لكل طلب.', [':max' => self::MAX_PHOTOS]));
        }

        $paths = [];
        try {
            $paths = $this->storePhotos($photos);

            $locked = $this->transition($returnRequest, ['rejected'], function (ReturnRequest $r) use ($customer, $note, $paths) {
                if ($r->dispute_by === null || $r->dispute_by->isPast()) {
                    throw new \DomainException('انتهت مهلة فتح النزاع.');
                }
                $this->assertOrderNotCancelled($r);
                $r->update(['status' => 'escalated']);
                $this->event($r, $customer->id, 'customer', 'disputed', $note, $paths);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);

            throw $e;
        }
        $this->notify(fn () => $this->notifier->disputed($locked));

        return $locked;
    }

    public function adminApprove(User $admin, ReturnRequest $returnRequest, ?string $note = null): ReturnRequest
    {
        $this->assertPlatformStaff($admin);

        $locked = $this->transition($returnRequest, ['escalated'], function (ReturnRequest $r) use ($admin, $note) {
            $this->assertOrderNotCancelled($r);
            $r->update([
                'status' => 'approved',
                'ship_back_by' => now()->addDays($this->settings->shipBackDays()),
                'decided_by' => $admin->id,
                'decision_note' => $note,
            ]);
            $this->event($r, $admin->id, 'admin', 'admin_approved', $note);
        });
        app(StaffActivityLog::class)->returnRequest($admin, 'return.approve', $locked);
        $this->notify(fn () => $this->notifier->adminDecided($locked));

        return $locked;
    }

    public function adminReject(User $admin, ReturnRequest $returnRequest, string $note): ReturnRequest
    {
        $this->assertPlatformStaff($admin);
        $this->requireNote($note);

        $locked = $this->transition($returnRequest, ['escalated'], function (ReturnRequest $r) use ($admin, $note) {
            $r->update(['status' => 'closed', 'closed_at' => now(), 'decided_by' => $admin->id, 'decision_note' => $note]);
            $this->event($r, $admin->id, 'admin', 'admin_rejected', $note);
        });
        app(StaffActivityLog::class)->returnRequest($admin, 'return.reject', $locked, ['note' => $note]);
        $this->notify(fn () => $this->notifier->adminDecided($locked));

        return $locked;
    }

    public function confirmReceived(User $actor, ReturnRequest $returnRequest): ReturnRequest
    {
        $isAdmin = $actor->isPlatformStaff();
        if (! $isAdmin && $actor->sellerProfile?->id !== $returnRequest->seller_profile_id) {
            throw new NotFoundHttpException;
        }

        $locked = $this->transition($returnRequest, ['approved'], function (ReturnRequest $r) use ($actor, $isAdmin) {
            $r->update(['status' => 'received', 'received_at' => now()]);
            $this->event($r, $actor->id, $isAdmin ? 'admin' : 'seller', 'received');
            $this->settlement->apply($r);
        });
        app(StaffActivityLog::class)->returnRequest($actor, 'return.received', $locked);
        $this->notify(fn () => $this->notifier->received($locked));

        return $locked;
    }

    /**
     * يسجّل المدير استرداداً دفعه خارج النظام بعد استلام المرتجع.
     *
     * لا قيد في الدفتر هنا: عكس جانب البائع سُجّل عند الاستلام، وهذا السجل يوثّق صرف المنصة للعميل فقط.
     * الشحن الإضافي لا عمود له: يُشتق من `amount - refund_amount` للطلب (حين يكون موجباً)،
     * ولذلك يُشترط أن يتجاوز المبلغ refund_amount عند طلب شحن إضافي، ويُسمح به مرة واحدة لكل طلب عبر مرتجعاته.
     */
    public function recordRefund(User $admin, ReturnRequest $returnRequest, int $amount, string $method, ?string $reference, ?string $note, int $extraShipping = 0): ReturnRefund
    {
        $this->assertAdmin($admin);
        if (! array_key_exists($method, ReturnRefund::METHOD_LABELS)) {
            throw new \DomainException('طريقة الاسترداد غير صالحة.');
        }
        if ($amount <= 0) {
            throw new \DomainException('مبلغ الاسترداد يجب أن يكون أكبر من صفر.');
        }
        if ($extraShipping < 0) {
            throw new \DomainException('الشحن الإضافي لا يمكن أن يكون سالباً.');
        }
        if ($extraShipping > 0 && trim((string) $note) === '') {
            throw new \DomainException('ملاحظة الشحن الإضافي مطلوبة.');
        }

        $refund = null;
        $locked = $this->transition($returnRequest, ['received'], function (ReturnRequest $r) use ($admin, $amount, $method, $reference, $note, $extraShipping, &$refund) {
            $order = Order::whereKey($r->order_id)->lockForUpdate()->firstOrFail();
            if ($amount > $r->refund_amount + $extraShipping) {
                throw new \DomainException('المبلغ أكبر من مبلغ الاسترداد المستحق.');
            }
            if ($extraShipping > 0) {
                if ($extraShipping > (int) $order->shipping_fee) {
                    throw new \DomainException('الشحن الإضافي أكبر من رسوم شحن الطلب.');
                }
                if ($amount <= $r->refund_amount) {
                    throw new \DomainException('الشحن الإضافي يستلزم مبلغاً أكبر من مبلغ الاسترداد المستحق.');
                }
                if ($this->calculator->orderShippingRefunded($order->id)) {
                    throw new \DomainException('سبق استرداد الشحن لهذا الطلب.');
                }
            }

            $refund = ReturnRefund::create([
                'return_request_id' => $r->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'recorded_by' => $admin->id,
            ]);
            if ($method === 'gift_balance') {
                app(GiftCardService::class)->creditForReturn($order, $amount, $refund->id);
            }
            $r->update(['status' => 'refunded']);
            $this->event($r, $admin->id, 'admin', 'refunded', $note);
        });
        app(StaffActivityLog::class)->returnRequest($admin, 'return.refund', $locked, ['amount' => number_format($amount / 100)]);
        $this->notify(fn () => $this->notifier->refunded($locked));

        return $refund;
    }

    /**
     * @return array{escalated:int, closed:int}
     */
    public function runDaily(): array
    {
        $counts = ['escalated' => 0, 'closed' => 0];

        $due = [
            ['requested', 'respond_by', 'escalated'],
            ['rejected', 'dispute_by', 'closed'],
            ['approved', 'ship_back_by', 'closed'],
        ];

        foreach ($due as [$status, $column, $outcome]) {
            $ids = ReturnRequest::where('status', $status)->where($column, '<', now())->pluck('id');
            foreach ($ids as $id) {
                $locked = DB::transaction(function () use ($id, $status, $column, $outcome) {
                    $r = ReturnRequest::whereKey($id)->lockForUpdate()->first();
                    if ($r === null || $r->status !== $status || $r->{$column} === null || ! $r->{$column}->isPast()) {
                        return null;
                    }
                    if ($outcome === 'escalated') {
                        $r->update(['status' => 'escalated']);
                        $this->event($r, null, 'system', 'escalated');
                    } else {
                        $r->update(['status' => 'closed', 'closed_at' => now()]);
                        $this->event($r, null, 'system', 'closed');
                    }

                    return $r;
                });
                if ($locked === null) {
                    continue;
                }
                $counts[$outcome]++;
                $this->notify(fn () => $outcome === 'escalated' ? $this->notifier->escalated($locked) : $this->notifier->closed($locked));
            }
        }

        return $counts;
    }

    /**
     * يقفل الصف ويعيد فحص الحالة ثم ينفّذ الانتقال داخل المعاملة.
     *
     * @param  array<int, string>  $allowed
     */
    private function transition(ReturnRequest $returnRequest, array $allowed, callable $apply): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $allowed, $apply) {
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $allowed, true)) {
                throw new \DomainException('لا يمكن تنفيذ هذا الإجراء على طلب الإرجاع في حالته الحالية.');
            }
            $apply($locked);

            return $locked;
        });
    }

    /**
     * الإشعار بعد اكتمال المعاملة: فشله يُسجَّل ولا يُلغي الإجراء المنفَّذ ولا يوقف المهمة اليومية.
     */
    private function notify(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** الطلب الملغى عُكس كله؛ لا نزاع ولا موافقة على إرجاع منه. يقفل صف الطلب ليتسلسل مع الإلغاء. */
    private function assertOrderNotCancelled(ReturnRequest $r): void
    {
        if (Order::whereKey($r->order_id)->lockForUpdate()->value('status') === 'cancelled') {
            throw new \DomainException('لا يمكن متابعة طلب الإرجاع لأن الطلب ملغى.');
        }
    }

    /**
     * @param  array<int, string>|null  $photos
     */
    private function event(ReturnRequest $r, ?int $actorId, string $role, string $type, ?string $note = null, ?array $photos = null): void
    {
        $r->events()->create(['actor_id' => $actorId, 'actor_role' => $role, 'type' => $type, 'note' => $note, 'photos' => $photos]);
    }

    private function assertOwnedBy(SellerProfile $seller, ReturnRequest $r): void
    {
        if ($seller->id !== $r->seller_profile_id) {
            throw new NotFoundHttpException;
        }
    }

    private function assertPlatformStaff(User $user): void
    {
        if (! $user->isPlatformStaff()) {
            throw new AccessDeniedHttpException;
        }
    }

    private function assertAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            throw new AccessDeniedHttpException;
        }
    }

    private function requireNote(string $note): void
    {
        if (trim($note) === '') {
            throw new \DomainException('السبب مطلوب.');
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'RT-'.strtoupper(Str::random(8));
        } while (ReturnRequest::where('number', $number)->exists());

        return $number;
    }
}
