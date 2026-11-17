<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->role === 'admin';

        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'store_logo' => $this->store_logo ? asset('storage/'.$this->store_logo) : null,
            'store_banner' => $this->store_banner ? asset('storage/'.$this->store_banner) : null,
            'store_contact_phone' => $this->store_contact_phone,
            'store_whatsapp' => $this->store_whatsapp,
            'store_instagram' => $this->store_instagram,
            'business_hours' => $this->business_hours,
            'shipping_policy' => $this->shipping_policy,
            // إعدادات الشحن مع كرم: لصاحب المتجر والمدير فقط
            'dropoff_center_id' => $this->when($isAdmin || $request->user()?->id === $this->user_id, $this->dropoff_center_id),
            'courier_pickup' => $this->when($isAdmin || $request->user()?->id === $this->user_id, (bool) $this->courier_pickup),
            'return_policy' => $this->return_policy,
            'commercial_register' => $isAdmin ? $this->commercial_register : null,
            'status' => $this->status,
            'status_label' => method_exists($this->resource, 'identityStatusLabel') ? $this->identityStatusLabel() : $this->status,
            'rejection_reason' => $this->rejection_reason,
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'is_identity_verified' => $this->status === 'approved' && filled($this->identity_verified_at ?? null),
            'identity_review_pending' => $this->status === 'approved' && ! filled($this->identity_verified_at ?? null) && filled($this->identity_document_path),
            'identity_type' => $this->identity_type ?? null,
            'identity_number' => $isAdmin ? ($this->identity_number ?? null) : null,
            'identity_number_masked' => filled($this->identity_number ?? null) ? ('****'.mb_substr((string) $this->identity_number, -4)) : null,
            'has_identity_document' => filled($this->identity_document_path),
            'has_identity_document_back' => filled($this->identity_document_back_path ?? null),
            'identity_document_submitted_at' => optional($this->identity_document_submitted_at)->toIso8601String(),
            'identity_verified_at' => optional($this->identity_verified_at ?? null)->toIso8601String(),
            'commission_rate' => $this->effective_commission_rate,
            'commission_rate_override' => $this->commission_rate,
            'owner_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'owner_phone' => $this->whenLoaded('user', fn () => $this->user->phone),
            'owner_email' => $isAdmin ? $this->whenLoaded('user', fn () => $this->user->email) : null,
        ];
    }
}
