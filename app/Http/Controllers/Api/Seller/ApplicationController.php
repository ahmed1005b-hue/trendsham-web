<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProfileResource;
use App\Models\SellerIdentityReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ApplicationController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;
        $previousStatus = $profile?->status;
        $approvedLegacy = $profile?->status === 'approved' && ! filled($profile->identity_verified_at);
        if ($profile?->status === 'approved' && filled($profile->identity_verified_at)) {
            return response()->json(['message' => 'تم اعتماد هوية متجرك بالفعل.'], 409);
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
            'identity_document.required' => 'يرجى رفع وثيقة الهوية.',
            'identity_document.mimes' => 'وثيقة الهوية يجب أن تكون صورة (JPG/PNG/WEBP) أو PDF.',
            'identity_document_back.mimes' => 'الوجه الخلفي يجب أن يكون صورة (JPG/PNG/WEBP) أو PDF.',
        ]);

        $disk = Storage::disk('identity_documents');
        $oldFront = $profile?->identity_document_path;
        $oldBack = $profile?->identity_document_back_path;
        $frontPath = $request->file('identity_document')->store('seller-identities', 'identity_documents');
        $backPath = $request->hasFile('identity_document_back')
            ? $request->file('identity_document_back')->store('seller-identities', 'identity_documents')
            : $oldBack;

        try {
            DB::transaction(function () use ($user, $profile, $validated, $frontPath, $backPath, $approvedLegacy): void {
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
                    $user->sellerProfile()->create($attributes);
                }

                if (! in_array($user->role, ['seller', 'admin', 'employee'], true)) {
                    $user->update(['role' => 'seller']);
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

        $profile = $user->fresh()->sellerProfile()->with('user')->first();
        if ($profile) {
            SellerIdentityReview::record($profile, $approvedLegacy ? 'legacy_resubmitted' : 'submitted', [
                'previous_status' => $previousStatus,
                'reason' => null,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        }

        return response()->json([
            'message' => $approvedLegacy
                ? 'تم استلام وثيقة الهوية. سيبقى متجرك نشطاً أثناء المراجعة.'
                : 'تم إرسال طلب التحقق للمراجعة. سنبلغك بعد اعتماد الهوية أو إذا لزم إعادة الإرسال.',
            'data' => $profile ? new SellerProfileResource($profile) : null,
        ]);
    }
}