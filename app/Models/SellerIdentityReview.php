<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerIdentityReview extends Model
{
    protected $fillable = [
        'seller_profile_id',
        'user_id',
        'admin_id',
        'action',
        'reason',
        'internal_note',
        'previous_status',
        'new_status',
        'identity_type',
        'identity_number',
        'identity_document_path',
        'identity_document_back_path',
        'identity_document_submitted_at',
        'identity_verified_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'identity_document_submitted_at' => 'datetime',
            'identity_verified_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(SellerProfile $profile, string $action, array $attributes = []): self
    {
        $profile->loadMissing('user');

        return static::create(array_merge([
            'seller_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'action' => $action,
            'previous_status' => $profile->getOriginal('status', $profile->status),
            'new_status' => $profile->status,
            'identity_type' => $profile->identity_type,
            'identity_number' => $profile->identity_number,
            'identity_document_path' => $profile->identity_document_path,
            'identity_document_back_path' => $profile->identity_document_back_path,
            'identity_document_submitted_at' => $profile->identity_document_submitted_at,
            'identity_verified_at' => $profile->identity_verified_at,
            'reason' => $profile->rejection_reason,
        ], $attributes));
    }
}
