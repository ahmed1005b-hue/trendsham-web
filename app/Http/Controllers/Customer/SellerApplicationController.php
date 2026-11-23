<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SellerIdentityReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SellerApplicationController extends Controller
{
    public function create(Request $request)
    {
        abort_if($request->user()?->role === 'admin', 403, 'حساب المدير مخصص لمراجعة طلبات البائعين واعتمادها، وليس لتقديم طلب تحقق كبائع.');
        $profile = $request->user()->sellerProfile;
        // فتح متجر جديد بدعوة من الإدارة فقط؛ هذه الصفحة لتوثيق هوية متجر موجود
        abort_unless($profile || $request->user()->role === 'seller', 403, __('فتح المتاجر على تريندشام بدعوة من الإدارة فقط.'));
        if ($profile?->status === 'approved' && filled($profile->identity_verified_at)) {
            return redirect()->route('seller.dashboard')->with('success', 'تم اعتماد هوية متجرك بالفعل.');
        }

        return view('customer.seller-application', ['sellerProfile' => $profile]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()?->role === 'admin', 403, 'حساب المدير مخصص لمراجعة طلبات البائعين واعتمادها، وليس لتقديم طلب تحقق كبائع.');
        $profile = $request->user()->sellerProfile;
        abort_unless($profile || $request->user()->role === 'seller', 403, __('فتح المتاجر على تريندشام بدعوة من الإدارة فقط.'));
        $previousStatus = $profile?->status;
        $approvedLegacy = $profile?->status === 'approved' && ! filled($profile->identity_verified_at);
        if ($profile?->status === 'approved' && filled($profile->identity_verified_at)) {
            return redirect()->route('seller.dashboard')->with('success', 'متجرك معتمد بالفعل.');
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'commercial_register' => ['nullable', 'string', 'max:255'],
            'identity_type' => ['nullable', 'in:national_id,passport,residence,commercial_register'],
            'identity_number' => ['nullable', 'string', 'max:64'],
            'identity_document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'identity_document_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'identity_document.required' => 'يرجى رفع وثيقة الهوية (الوجه الأمامي).',
            'identity_document.mimes' => 'الوثيقة يجب أن تكون صورة JPG/PNG/WEBP أو ملف PDF.',
            'identity_document_back.mimes' => 'الوجه الخلفي يجب أن يكون صورة JPG/PNG/WEBP أو ملف PDF.',
        ]);

        $disk = Storage::disk('identity_documents');
        $oldFront = $profile?->identity_document_path;
        $oldBack = $profile?->identity_document_back_path;
        $frontPath = $request->file('identity_document')->store('seller-identities', 'identity_documents');
        $backPath = $request->hasFile('identity_document_back')
            ? $request->file('identity_document_back')->store('seller-identities', 'identity_documents')
            : $oldBack;

        try {
            DB::transaction(function () use ($request, $profile, $validated, $frontPath, $backPath, $approvedLegacy): void {
                $attributes = [
                    'store_name' => $validated['store_name'],
                    'store_description' => $validated['store_description'] ?? $profile?->store_description,
                    'commercial_register' => $validated['commercial_register'] ?? $profile?->commercial_register,
                    'identity_type' => $validated['identity_type'] ?? null,
                    'identity_number' => $validated['identity_number'] ?? null,
                    'identity_document_path' => $frontPath,
                    'identity_document_back_path' => $backPath,
                    'identity_document_submitted_at' => now(),
                    'identity_verified_at' => null,
                    'status' => $approvedLegacy ? 'approved' : 'pending',
                    'rejection_reason' => null,
                ];

                if ($profile) {
                    $profile->update($attributes);
                } else {
                    $request->user()->sellerProfile()->create($attributes);
                }

                if ($request->user()->role !== 'seller') {
                    $request->user()->update(['role' => 'seller']);
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($frontPath);
            if ($request->hasFile('identity_document_back') && $backPath) {
                $disk->delete($backPath);
            }
            throw $exception;
        }

        if ($oldFront && $oldFront !== $frontPath) {
            $disk->delete($oldFront);
        }
        if ($request->hasFile('identity_document_back') && $oldBack && $oldBack !== $backPath) {
            $disk->delete($oldBack);
        }

        $profile = $request->user()->fresh()->sellerProfile;
        if ($profile) {
            SellerIdentityReview::record($profile, $approvedLegacy ? 'legacy_resubmitted' : 'submitted', [
                'previous_status' => $previousStatus,
                'reason' => null,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        }

        $message = $approvedLegacy
            ? 'تم استلام وثيقة الهوية. سيبقى متجرك نشطاً أثناء المراجعة، وسنبلغك بعد اعتماد الهوية.'
            : 'تم إرسال طلب التحقق للمراجعة. سنبلغك عند الاعتماد أو إذا لزم إعادة رفع وثيقة أوضح.';

        return redirect()->route('seller.dashboard')->with('success', $message);
    }
}
