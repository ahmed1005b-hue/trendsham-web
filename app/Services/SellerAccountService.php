<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * إيقاف/تفعيل/حذف متجر — لوحة الإدارة في الموقع والتطبيق تستخدم نفس القواعد.
 */
class SellerAccountService
{
    /** إيقاف متجر معتمد: يختفي عن الزوار ولا يُشترى منه. المحفظة والطلبات والقيود تبقى كما هي. */
    public function suspend(SellerProfile $seller, string $reason): void
    {
        if ($seller->status !== 'approved') {
            throw new \DomainException('يمكن إيقاف المتاجر المعتمدة فقط.');
        }

        $seller->update(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => $reason]);

        AppNotification::send(
            $seller->user_id,
            'store_suspended',
            'تم إيقاف متجرك مؤقتاً',
            "السبب: {$reason}. منتجاتك مخفية عن الزوار حتى إعادة التفعيل، ورصيدك وطلباتك محفوظة. تواصل مع الإدارة من صفحة الدعم.",
            route('seller.dashboard')
        );
    }

    public function unsuspend(SellerProfile $seller): void
    {
        if (! $seller->isSuspended()) {
            throw new \DomainException('هذا المتجر غير موقوف.');
        }

        $seller->update(['status' => 'approved', 'suspended_at' => null, 'suspension_reason' => null]);

        AppNotification::send(
            $seller->user_id,
            'store_unsuspended',
            'تمت إعادة تفعيل متجرك',
            'منتجاتك ظاهرة للزوار من جديد.',
            route('seller.dashboard')
        );
    }

    /** سبب منع الحذف، أو null إن كان الحذف آمناً. */
    public function deletionBlocker(SellerProfile $seller): ?string
    {
        return match (true) {
            (int) $seller->wallet()->value('balance') !== 0 => 'في محفظته رصيد',
            WithdrawalRequest::where('vendor_id', $seller->id)->exists() => 'لديه طلبات سحب أرباح',
            OrderItem::where('vendor_id', $seller->id)->exists() => 'لديه مبيعات مسجلة',
            DB::table('ledger_entries')->where('seller_id', $seller->user_id)->exists() => 'لديه قيود محاسبية',
            DB::table('showcases')->where('seller_id', $seller->id)->exists() => 'لديه حجوزات مساحات عرض',
            default => null,
        };
    }

    /**
     * حذف متجر بلا أي تاريخ مالي (كل شيء أو لا شيء).
     *
     * @throws \DomainException إن كان للمتجر سجل مالي — يُقترح الإيقاف بدلاً منه
     */
    public function delete(SellerProfile $seller): void
    {
        if ($reason = $this->deletionBlocker($seller)) {
            throw new \DomainException(__('لا يمكن حذف هذا المتجر: :reason. أوقف المتجر بدل حذفه للحفاظ على السجلات المالية.', ['reason' => __($reason)]));
        }

        $user = $seller->user;
        $documents = [$seller->identity_document_path, $seller->identity_document_back_path];

        DB::transaction(function () use ($seller, $user) {
            $seller->delete();

            if ($user && $user->role === 'seller') {
                $user->orders()->exists() ? $user->update(['role' => 'customer']) : $user->delete();
            }
        });

        // الملفات بعد نجاح الحذف فقط
        foreach ($documents as $path) {
            if ($path && Storage::disk('identity_documents')->exists($path)) {
                Storage::disk('identity_documents')->delete($path);
            }
        }
    }
}
