<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Models\User;
use App\Models\Wallet;
use App\Showcases\InsufficientBalanceException;
use App\Showcases\ShowcaseWallet;
use App\Support\ColorContrast;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ShowcaseService
{
    public const CACHE_KEY = 'home:showcases:v1';

    public function __construct(private ShowcaseWallet $wallet) {}

    /** يحسب عدد الأيام والمبلغ ويتحقق من حدود المساحة. */
    public function quote(ShowcaseSlot $slot, Carbon $start, Carbon $end): array
    {
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), true) + 1;

        if ($days < $slot->min_days || $days > $slot->max_days) {
            throw ValidationException::withMessages([
                'end_date' => __('المدة المسموحة لهذه المساحة من :min إلى :max يوماً.', ['min' => $slot->min_days, 'max' => $slot->max_days]),
            ]);
        }

        return ['days' => $days, 'amount' => round($days * (float) $slot->price_per_day, 2)];
    }

    public function usedCapacity(ShowcaseSlot $slot, string $start, string $end, ?int $ignoreId = null): int
    {
        return Showcase::where('slot_id', $slot->id)
            ->whereIn('status', ['pending_review', 'approved'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->count();
    }

    public function isAvailable(ShowcaseSlot $slot, string $start, string $end): bool
    {
        return $slot->is_active && $this->usedCapacity($slot, $start, $end) < $slot->capacity;
    }

    /** ينشئ الحجز ويخصم المبلغ. يعيد العرض بحالة pending_review. */
    public function create(User $seller, array $d): Showcase
    {
        return DB::transaction(function () use ($seller, $d) {
            // قفل المساحة لمنع الحجز المتزامن
            $slot = ShowcaseSlot::whereKey($d['slot_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

            $start = Carbon::parse($d['start_date'])->startOfDay();
            $end   = Carbon::parse($d['end_date'])->startOfDay();
            $q     = $this->quote($slot, $start, $end);

            if ($this->usedCapacity($slot, $start->toDateString(), $end->toDateString()) >= $slot->capacity) {
                throw ValidationException::withMessages(['slot_id' => 'هذه المساحة محجوزة في التواريخ المختارة.']);
            }

            // المنتجات
            $ids = array_values(array_unique(array_map('intval', $d['product_ids'])));
            if (count($ids) < $slot->min_products || count($ids) > $slot->max_products) {
                throw ValidationException::withMessages([
                    'product_ids' => __('اختر من :min إلى :max منتجات.', ['min' => $slot->min_products, 'max' => $slot->max_products]),
                ]);
            }

            $valid = Product::whereIn('id', $ids)
                ->where('vendor_id', $seller->sellerProfile->id)   // البائع يملك المنتج
                ->where('status', 'active')          // منتج فعّال
                ->where('stock_quantity', '>', 0)    // متوفر في المخزون
                ->pluck('id')->all();

            if (count($valid) !== count($ids)) {
                throw ValidationException::withMessages([
                    'product_ids' => 'بعض المنتجات غير متاحة أو لا تخص متجرك.',
                ]);
            }

            // الألوان: لون الخط يختاره البائع (والتباين الضعيف تحذير فقط، والمدير يراجع قبل النشر)
            $colors = array_values($d['gradient_colors']);
            $hex = isset($d['text_color_hex']) && $d['text_color_hex'] !== '' ? strtoupper($d['text_color_hex']) : null;
            $text = $hex ? ColorContrast::tone($hex) : ColorContrast::pick($colors)[0];

            // صورة الخلفية (اختيارية): تُحفظ بعد نجاح كل التحققات، وتُحذف إن فشل الحجز لاحقاً
            $bg = ($d['background_image'] ?? null) instanceof UploadedFile
                ? $d['background_image']->store('showcases', 'public') : null;
            if ($bg) {
                DB::afterRollBack(fn () => Storage::disk('public')->delete($bg));
            }

            // إنشاء الحجز أولاً للحصول على معرّفه (يلزم كمصدر لقيد الدفتر)
            $s = Showcase::create([
                'slot_id'            => $slot->id,
                'seller_id'          => $seller->sellerProfile->id,
                'title'              => $d['title'],
                'subtitle'           => $d['subtitle'] ?? null,
                'gradient_colors'    => $colors,
                'gradient_direction' => $d['gradient_direction'],
                'text_color'         => $text,
                'text_hex'           => $hex,
                'background_image'   => $bg,
                'start_date'         => $start,
                'end_date'           => $end,
                'amount'             => $q['amount'],
                'status'             => 'pending_review',
            ]);

            // الدفع من المحفظة
            try {
                $this->wallet->debit($seller, $q['amount'], 'showcase_booking', ['slot' => $slot->key, 'showcase_id' => $s->id]);
            } catch (InsufficientBalanceException) {
                $s->delete(); // فشل الدفع: أزل الحجز القائم
                throw ValidationException::withMessages(['amount' => 'رصيد محفظتك غير كافٍ لإتمام الحجز.']);
            }

            foreach ($ids as $i => $pid) {
                $s->products()->attach($pid, ['sort_order' => $i]);
            }

            return $s;
        });
    }

    public function approve(Showcase $s, int $adminId): void
    {
        abort_unless($s->status === 'pending_review', 422, 'لا يمكن الموافقة على هذا العرض.');
        $s->update(['status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'rejection_reason' => null]);
        Cache::forget(self::CACHE_KEY);
        // TODO: أرسل إشعاراً للبائع (تمت الموافقة)
        $this->notifySeller($s, 'approved', 'تمت الموافقة على عرضك المؤجر وسيظهر في الفترة المحددة.');
    }

    public function reject(Showcase $s, int $adminId, string $reason): void
    {
        abort_unless($s->status === 'pending_review', 422, 'لا يمكن رفض هذا العرض.');
        DB::transaction(function () use ($s, $adminId, $reason) {
            // إعادة القراءة مع قفل: طلبان متزامنان (رفض + إلغاء) لا يستردّان المبلغ مرتين
            abort_unless($this->lockFresh($s)->status === 'pending_review', 422, 'لا يمكن رفض هذا العرض.');
            $s->update(['status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            $this->wallet->credit($s->seller->user, (float) $s->amount, 'showcase_refund', ['showcase' => $s->id]);
        });
        Cache::forget(self::CACHE_KEY);
        // TODO: أرسل إشعاراً للبائع (تم الرفض مع السبب)
        $this->notifySeller($s, 'rejected', "تم رفض عرضك المؤجر: {$reason}. تم استرجاع المبلغ إلى محفظتك.");
    }

    /** إلغاء من البائع: مسموح قبل بدء الفترة فقط، مع استرجاع كامل. */
    public function cancelBySeller(Showcase $s, User $seller): void
    {
        abort_unless($s->seller_id === $seller->sellerProfile->id, 403);
        abort_unless(in_array($s->status, ['pending_review', 'approved'], true) && $s->start_date->gt(today()), 422,
            'لا يمكن إلغاء هذا الحجز بعد بدء فترته. تواصل مع الدعم.');

        DB::transaction(function () use ($s) {
            abort_unless(in_array($this->lockFresh($s)->status, ['pending_review', 'approved'], true), 422,
                'تم التعامل مع هذا الحجز مسبقاً.');
            $s->update(['status' => 'cancelled']);
            $this->wallet->credit($s->seller->user, (float) $s->amount, 'showcase_refund', ['showcase' => $s->id]);
        });
        Cache::forget(self::CACHE_KEY);
    }

    /** إزالة من المدير لعرض جارٍ أو مجدول: استرجاع بنسبة الأيام المتبقية. */
    public function removeByAdmin(Showcase $s, int $adminId, string $reason): void
    {
        abort_unless(in_array($s->status, ['pending_review', 'approved'], true), 422);

        $total = (int) $s->start_date->diffInDays($s->end_date, true) + 1;
        $remaining = $s->start_date->gt(today())
            ? $total
            : max(0, (int) today()->diffInDays($s->end_date, false) + 1);

        $refund = round((float) $s->amount * ($remaining / max(1, $total)), 2);

        DB::transaction(function () use ($s, $adminId, $reason, $refund) {
            abort_unless(in_array($this->lockFresh($s)->status, ['pending_review', 'approved'], true), 422,
                'تم التعامل مع هذا العرض مسبقاً.');
            $s->update(['status' => 'cancelled', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            if ($refund > 0) {
                $this->wallet->credit($s->seller->user, $refund, 'showcase_refund', ['showcase' => $s->id]);
            }
        });
        Cache::forget(self::CACHE_KEY);
    }

    /** يقفل صف العرض داخل المعاملة ويحدّث النسخة في الذاكرة بالحالة الفعلية. */
    private function lockFresh(Showcase $s): Showcase
    {
        $locked = Showcase::whereKey($s->id)->lockForUpdate()->firstOrFail();
        $s->setRawAttributes($locked->getAttributes(), true);

        return $s;
    }

    private function notifySeller(Showcase $s, string $type, string $message): void
    {
        if ($s->seller && $s->seller->user) {
            \App\Models\AppNotification::send(
                $s->seller->user->id,
                'showcase_' . $type,
                $type === 'approved' ? 'تمت الموافقة على عرضك' : 'تم رفض عرضك',
                $message,
                null
            );
        }
    }
}