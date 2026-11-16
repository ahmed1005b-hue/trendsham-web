<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->relationLoaded('sellerProfile')
            ? $this->sellerProfile
            : $this->sellerProfile()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'country_code' => $this->country_code,
            'national_number' => $this->national_number,
            'birth_date' => $this->birth_date?->toDateString(),
            'city' => $this->city,
            'gender' => $this->gender,
            'avatar' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'role' => $this->role,
            // توثيق حقيقي (شارة "موثّق") بدل عرضها لكل مستخدم
            'phone_verified' => $this->phone_verified_at !== null,
            // عناوين الشحن: تدخل في نسبة إكمال الملف وتملأ عنوان التوصيل
            'addresses_count' => $this->addresses()->count(),
            'default_address' => $this->defaultAddress()->first()?->toApiArray(),
            'seller_profile' => $profile ? [
                'id' => $profile->id,
                'store_name' => $profile->store_name,
                'status' => $profile->status,
                'status_label' => method_exists($profile, 'identityStatusLabel') ? $profile->identityStatusLabel() : $profile->status,
                'rejection_reason' => $profile->rejection_reason,
                'has_identity_document' => filled($profile->identity_document_path),
                'has_identity_document_back' => filled($profile->identity_document_back_path ?? null),
                'identity_type' => $profile->identity_type ?? null,
                'is_identity_verified' => $profile->status === 'approved' && filled($profile->identity_verified_at ?? null),
                'identity_review_pending' => $profile->status === 'approved' && ! filled($profile->identity_verified_at ?? null) && filled($profile->identity_document_path),
                'identity_verified_at' => optional($profile->identity_verified_at ?? null)->toIso8601String(),
                'identity_document_submitted_at' => optional($profile->identity_document_submitted_at)->toIso8601String(),
            ] : null,
        ];
    }
}
