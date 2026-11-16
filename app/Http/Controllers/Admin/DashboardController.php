<?php

namespace App\Http\Controllers\Admin;

use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerIdentityReview;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\StaffConversation;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Returns\Models\ReturnRequest;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pendingSellerProfiles = SellerProfile::with('user')->where('status', 'pending')->latest()->get();

        $filters = $request->only(['q', 'identity_type', 'has_back', 'review_kind', 'submitted_from', 'submitted_to']);
        $query = SellerProfile::with('user')
            ->where('status', 'approved')
            ->whereNotNull('identity_document_path')
            ->whereNull('identity_verified_at');

        $query->when($filters['q'] ?? null, function ($q, $term) {
            $q->where(function ($inner) use ($term) {
                $inner->where('store_name', 'like', "%{$term}%")
                    ->orWhere('identity_number', 'like', "%{$term}%")
                    ->orWhere('commercial_register', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($user) use ($term) {
                        $user->where(function ($u) use ($term) {
                            $u->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$term}%"])
                                ->orWhere('phone', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                    });
            });
        });
        $query->when($filters['identity_type'] ?? null, fn ($q, $type) => $q->where('identity_type', $type));
        $query->when(($filters['has_back'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('identity_document_back_path'));
        $query->when(($filters['has_back'] ?? null) === 'no', fn ($q) => $q->whereNull('identity_document_back_path'));
        $query->when(($filters['review_kind'] ?? null) === 'legacy', fn ($q) => $q->whereNotNull('created_at')->whereColumn('identity_document_submitted_at', '>', 'created_at'));
        $query->when(($filters['review_kind'] ?? null) === 'new', fn ($q) => $q->where(function ($inner) {
            $inner->whereNull('identity_document_submitted_at')->orWhereColumn('identity_document_submitted_at', '<=', 'created_at');
        }));
        $query->when($filters['submitted_from'] ?? null, fn ($q, $date) => $q->whereDate('identity_document_submitted_at', '>=', $date));
        $query->when($filters['submitted_to'] ?? null, fn ($q, $date) => $q->whereDate('identity_document_submitted_at', '<=', $date));

        $identityReviewProfiles = $query->latest('identity_document_submitted_at')->get();

        return view('admin.dashboard', [
            'sellersCount' => User::sellers()->count(),
            'customersCount' => User::customers()->count(),
            'pendingSellers' => $pendingSellerProfiles->count(),
            'pendingSellerProfiles' => $pendingSellerProfiles,
            'identityReviewProfiles' => $identityReviewProfiles,
            'identityReviewCount' => $identityReviewProfiles->count(),
            'identityReviewFilters' => $filters,
            'pendingProductsCount' => Product::pendingReview()->count(),

            'totalSales' => Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total'),
            'totalCommission' => OrderItem::whereNotNull('commission_settled_at')->sum('commission_amount'),
            'pendingWithdrawalsCount' => WithdrawalRequest::where('status', 'pending')->count(),
            'pendingWithdrawalsAmount' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
            'returnsNeedingActionCount' => ReturnRequest::whereIn('status', ['escalated', 'received'])->count(),
            'openSupportTickets' => StaffConversation::where('status', 'open')->count(),
            'pendingSellerCoupons' => CouponCampaign::where('funder', 'seller')->where('status', 'pending_review')->count(),
            'pendingShowcasesCount' => Showcase::where('status', 'pending_review')->count(),
        ]);
    }

    /** صفحة التحليلات الشاملة للمنصة. */
    public function analytics(Request $request, AdminAnalyticsService $analytics)
    {
        $days = AdminAnalyticsService::normalizeDays($request->query('days'));

        return view('admin.analytics', [
            'days' => $days,
            'data' => $analytics->summary($days),
        ]);
    }

    public function approveSeller(Request $request, SellerProfile $sellerProfile)
    {
        $hasDocument = $sellerProfile->identity_document_path
            && Storage::disk('identity_documents')->exists($sellerProfile->identity_document_path);
        abort_if(! $hasDocument, 422, 'يجب استلام وثيقة هوية صالحة قبل اعتماد المتجر/الهوية.');

        $validated = $request->validate([
            'internal_note' => ['nullable', 'string', 'max:500'],
        ]);

        $previousStatus = $sellerProfile->status;
        $sellerProfile->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'identity_verified_at' => now(),
        ]);

        SellerIdentityReview::record($sellerProfile, 'approved', [
            'admin_id' => $request->user()->id,
            'previous_status' => $previousStatus,
            'reason' => null,
            'internal_note' => $validated['internal_note'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        AppNotification::send(
            $sellerProfile->user_id,
            'seller_approved',
            'تم اعتماد هوية متجرك!',
            'تمت مراجعة الهوية واعتماد متجرك. يمكنك المتابعة بالبيع بثقة أكبر أمام العملاء.',
            route('seller.dashboard')
        );

        return back()->with('success', 'تم اعتماد الهوية/المتجر وإرسال إشعار للبائع.');
    }

    public function rejectSeller(Request $request, SellerProfile $sellerProfile)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_code' => ['nullable', 'string', 'max:64'],
            'internal_note' => ['nullable', 'string', 'max:500'],
        ]);
        $reasons = [
            'unclear' => 'الوثيقة غير واضحة أو مقصوصة.',
            'incomplete' => 'الوثيقة ناقصة أو الوجه الخلفي مطلوب.',
            'expired' => 'الوثيقة منتهية أو غير صالحة.',
            'mismatch' => 'بيانات الهوية لا تطابق بيانات المتجر/المالك.',
            'unreadable' => 'الملف غير قابل للقراءة؛ يرجى رفع صورة أو PDF أوضح.',
            'other' => '',
        ];
        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '' && isset($reasons[$validated['reason_code'] ?? ''])) {
            $reason = $reasons[$validated['reason_code']];
        }
        if ($reason === '') {
            return back()->withErrors(['reason' => 'اختر سبباً جاهزاً أو اكتب سبب طلب إعادة الإرسال.']);
        }

        $previousStatus = $sellerProfile->status;
        $legacyApproved = $sellerProfile->status === 'approved' && ! filled($sellerProfile->identity_verified_at);
        $sellerProfile->update([
            'status' => $legacyApproved ? 'approved' : 'rejected',
            'rejection_reason' => $reason,
            'identity_verified_at' => null,
        ]);

        SellerIdentityReview::record($sellerProfile, $legacyApproved ? 'reupload_requested' : 'rejected', [
            'admin_id' => $request->user()->id,
            'previous_status' => $previousStatus,
            'reason' => $reason,
            'internal_note' => $validated['internal_note'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        AppNotification::send(
            $sellerProfile->user_id,
            'seller_rejected',
            'مطلوب إعادة إرسال وثيقة هوية المتجر',
            $reason.' يرجى رفع وثيقة واضحة من صفحة التحقق.',
            route('seller.identity-verification.create')
        );

        return back()->with('success', $legacyApproved
            ? 'تم طلب إعادة إرسال هوية من البائع القديم مع إبقاء المتجر نشطاً.'
            : 'تم رفض الطلب وإرسال إشعار للبائع.');
    }
}
