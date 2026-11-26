<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProfileResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\AppNotification;
use App\Models\OrderItem;
use App\Models\SellerIdentityReview;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SellerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SellerController extends Controller
{
    public function employees()
    {
        return response()->json(
            User::where('role', 'employee')
                ->where('employee_status', 'pending')
                ->latest()
                ->paginate(20)
                ->through(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at,
                ])
        );
    }

    /** الموظفون المعتمدون وأقسام كل واحد (نفس صفحة الموقع). */
    public function team(): JsonResponse
    {
        return response()->json([
            'data' => User::where('role', 'employee')->where('employee_status', 'approved')->orderBy('first_name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'sections' => $user->employeeSections(),
                ])->values(),
            'sections' => collect(User::EMPLOYEE_SECTIONS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => __($label)])
                ->values(),
        ]);
    }

    public function updateEmployeePermissions(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isEmployee(), 404);
        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(array_keys(User::EMPLOYEE_SECTIONS))],
        ], ['sections.required' => __('اختر قسماً واحداً على الأقل.')]);

        $user->assignEmployeeSections($request->user(), $validated['sections']);

        return response()->json([
            'message' => __('تم حفظ أقسام الموظف :name.', ['name' => $user->name]),
            'sections' => $user->employeeSections(),
        ]);
    }

    public function approveEmployee(User $user)
    {
        abort_unless($user->role === 'employee' && $user->employee_status === 'pending', 404);
        $user->update(['employee_status' => 'approved', 'status' => 'active']);

        return response()->json(['message' => 'تمت الموافقة على حساب الموظف.']);
    }

    public function rejectEmployee(User $user)
    {
        abort_unless($user->role === 'employee' && $user->employee_status === 'pending', 404);
        $user->update(['employee_status' => 'rejected', 'status' => 'suspended']);

        return response()->json(['message' => 'تم رفض طلب الموظف.']);
    }

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $sellers = SellerProfile::with('user');
        if ($status === 'identity_review') {
            $sellers->whereNotNull('identity_document_path')->whereNull('identity_verified_at');
            $sellers->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(function ($inner) use ($term) {
                    $inner->where('store_name', 'like', "%{$term}%")
                        ->orWhere('identity_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where(function ($user) use ($term) {
                            $user->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$term}%"])
                                ->orWhere('phone', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        }));
                });
            });
            $sellers->when($request->filled('identity_type'), fn ($q) => $q->where('identity_type', $request->string('identity_type')));
            $sellers->when($request->string('has_back') === 'yes', fn ($q) => $q->whereNotNull('identity_document_back_path'));
            $sellers->when($request->string('has_back') === 'no', fn ($q) => $q->whereNull('identity_document_back_path'));
            $sellers->when($request->filled('submitted_from'), fn ($q) => $q->whereDate('identity_document_submitted_at', '>=', $request->string('submitted_from')));
            $sellers->when($request->filled('submitted_to'), fn ($q) => $q->whereDate('identity_document_submitted_at', '<=', $request->string('submitted_to')));
            $sellers->latest('identity_document_submitted_at');
        } else {
            $sellers->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest();
        }

        return SellerProfileResource::collection($sellers->paginate(20));
    }

    public function approve(SellerProfile $sellerProfile)
    {
        // البائعون المقبولون سابقاً (قبل نظام التحقق من الهوية) يمكن اعتمادهم دون وثيقة.
        $legacyApproved = $sellerProfile->status === 'approved' && ! filled($sellerProfile->identity_verified_at);
        $hasDocument = $sellerProfile->identity_document_path
            && Storage::disk('identity_documents')->exists($sellerProfile->identity_document_path);
        abort_unless(($legacyApproved || $hasDocument), 422, 'يجب استلام وثيقة هوية صالحة قبل اعتماد الهوية/المتجر.');

        $internalNote = request()->input('internal_note');
        $previousStatus = $sellerProfile->status;
        $sellerProfile->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'identity_verified_at' => now(),
        ]);

        SellerIdentityReview::record($sellerProfile, 'approved', [
            'admin_id' => request()->user()?->id,
            'previous_status' => $previousStatus,
            'reason' => null,
            'internal_note' => is_string($internalNote) ? mb_substr($internalNote, 0, 500) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);

        AppNotification::send(
            $sellerProfile->user_id,
            'seller_approved',
            'تم اعتماد هوية متجرك!',
            'تمت مراجعة الهوية واعتماد متجرك. يمكنك المتابعة بالبيع بثقة أكبر أمام العملاء.',
            null
        );

        return new SellerProfileResource($sellerProfile->fresh());
    }

    public function identityDocument(Request $request, SellerProfile $sellerProfile)
    {
        $side = $request->query('side', 'front');
        $path = $side === 'back' ? $sellerProfile->identity_document_back_path : $sellerProfile->identity_document_path;
        $disk = Storage::disk('identity_documents');
        abort_unless(in_array($side, ['front', 'back'], true) && $path && str_starts_with($path, 'seller-identities/') && $disk->exists($path), 404);
        $mimeType = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true), 404);
        $ext = $mimeType === 'application/pdf' ? 'pdf' : (pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');

        $response = response()->file($disk->path($path));
        $response->setPrivate();
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 'inline; filename="seller-identity-'.$side.'.'.$ext.'"');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function reject(Request $request, SellerProfile $sellerProfile)
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
        abort_if($reason === '', 422, 'اختر سبباً جاهزاً أو اكتب سبب طلب إعادة الإرسال.');

        $previousStatus = $sellerProfile->status;
        $legacyApproved = $sellerProfile->status === 'approved' && ! filled($sellerProfile->identity_verified_at);
        $sellerProfile->update([
            'status' => $legacyApproved ? 'approved' : 'rejected',
            'rejection_reason' => $reason,
            'identity_verified_at' => null,
        ]);

        SellerIdentityReview::record($sellerProfile, $legacyApproved ? 'reupload_requested' : 'rejected', [
            'admin_id' => $request->user()?->id,
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
            null
        );

        return new SellerProfileResource($sellerProfile->fresh());
    }

    public function updateCommission(Request $request, SellerProfile $sellerProfile)
    {
        $validated = $request->validate(['commission_rate' => ['nullable', 'integer', 'min:0', 'max:100']]);

        $sellerProfile->update(['commission_rate' => $validated['commission_rate'] ?? null]);

        return new SellerProfileResource($sellerProfile);
    }

    public function walletStatement(SellerProfile $sellerProfile)
    {
        $wallet = $sellerProfile->wallet;
        $transactions = WalletTransaction::query()
            ->when(
                $wallet,
                fn ($query) => $query->where('wallet_id', $wallet->id),
                fn ($query) => $query->whereKey(0)
            )
            ->latest()
            ->paginate(20);
        $transactionResponse = WalletTransactionResource::collection($transactions)
            ->response()
            ->getData(true);

        return response()->json([
            'balance' => ($wallet?->balance ?? 0) / 100,
            'pending_withdrawals' => $sellerProfile->withdrawalRequests()
                ->where('status', 'pending')
                ->sum('amount') / 100,
            'transactions' => $transactionResponse,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:processing,shipped,delivered,cancelled',
        ]);

        $orderItem = OrderItem::findOrFail($id);
        $orderItem->update([
            'order_status' => $request->status,
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => $orderItem,
        ]);
    }

    public function destroy(SellerProfile $sellerProfile)
    {
        try {
            app(SellerAccountService::class)->delete($sellerProfile);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'has_financial_history'], 422);
        }

        return response()->json(['message' => 'تم حذف البائع.']);
    }

    /** إيقاف المتجر: بديل آمن للحذف، تبقى المحفظة والطلبات والسجلات. */
    public function suspend(Request $request, SellerProfile $sellerProfile)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'اكتب سبب الإيقاف ليظهر للبائع.']);

        try {
            app(SellerAccountService::class)->suspend($sellerProfile, $validated['reason']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم إيقاف المتجر.', 'status' => 'suspended']);
    }

    public function unsuspend(SellerProfile $sellerProfile)
    {
        try {
            app(SellerAccountService::class)->unsuspend($sellerProfile);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تمت إعادة تفعيل المتجر.', 'status' => 'approved']);
    }
}
