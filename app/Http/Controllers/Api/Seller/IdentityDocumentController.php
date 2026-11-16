<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerIdentityReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IdentityDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'identity_type' => ['nullable', 'in:national_id,passport,residence,commercial_register'],
            'identity_number' => ['nullable', 'string', 'max:64'],
            'identity_document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'identity_document_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'identity_document.required' => 'يرجى رفع وثيقة الهوية.',
            'identity_document.mimes' => 'وثيقة الهوية يجب أن تكون صورة (JPG/PNG/WEBP) أو PDF.',
        ]);

        $profile = $request->user()->sellerProfile;
        abort_unless($profile, 404);
        $previousStatus = $profile->status;
        $approvedLegacy = $profile->status === 'approved' && ! filled($profile->identity_verified_at);
        abort_if($profile->status === 'approved' && filled($profile->identity_verified_at), 409, 'تم اعتماد هوية متجرك بالفعل.');

        $disk = Storage::disk('identity_documents');
        $oldFront = $profile->identity_document_path;
        $oldBack = $profile->identity_document_back_path;
        $frontPath = $request->file('identity_document')->store('seller-identities', 'identity_documents');
        $backPath = $request->hasFile('identity_document_back')
            ? $request->file('identity_document_back')->store('seller-identities', 'identity_documents')
            : $oldBack;

        try {
            DB::transaction(function () use ($profile, $validated, $frontPath, $backPath, $approvedLegacy): void {
                $profile->update([
                    'identity_type' => $validated['identity_type'] ?? $profile->identity_type,
                    'identity_number' => $validated['identity_number'] ?? $profile->identity_number,
                    'identity_document_path' => $frontPath,
                    'identity_document_back_path' => $backPath,
                    'identity_document_submitted_at' => now(),
                    'identity_verified_at' => null,
                    'status' => $approvedLegacy ? 'approved' : 'pending',
                    'rejection_reason' => null,
                ]);
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

        SellerIdentityReview::record($profile->fresh(), $approvedLegacy ? 'legacy_resubmitted' : 'submitted', [
            'previous_status' => $previousStatus,
            'reason' => null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'message' => $approvedLegacy
                ? 'تم استلام وثيقة الهوية. سيبقى متجرك نشطاً أثناء المراجعة.'
                : 'تم إرسال وثيقة الهوية للمراجعة. سنبلغك بعد الاعتماد أو إذا لزم إعادة الإرسال.',
            'status' => $approvedLegacy ? 'approved' : 'pending',
            'identity_review_pending' => true,
        ]);
    }
}