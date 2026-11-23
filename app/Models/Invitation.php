<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * دعوة انضمام كبائع أو موظف. الرابط يُستعمل مرة واحدة وله تاريخ انتهاء،
 * ويمكن ربطه برقم هاتف أو بريد فلا يقبله غير صاحبه. الإدارة تستطيع إلغاءه.
 */
class Invitation extends Model
{
    public const ROLES = ['seller', 'employee'];

    public const DEFAULT_DAYS = 7;

    protected $fillable = ['role', 'token_hash', 'token', 'name', 'phone', 'email', 'store_name', 'created_by', 'expires_at', 'accepted_at', 'accepted_by', 'revoked_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * ينشئ دعوة جديدة برمز عشوائي طويل.
     *
     * @param  array{role: string, name?: ?string, phone?: ?string, email?: ?string, store_name?: ?string, days?: int}  $data
     */
    public static function issue(array $data, ?User $by = null): self
    {
        $token = Str::random(48);

        return static::create([
            'role' => $data['role'],
            'token_hash' => hash('sha256', $token),
            'token' => $token,
            'name' => $data['name'] ?? null,
            'phone' => Phone::normalize($data['phone'] ?? null),
            'email' => isset($data['email']) && $data['email'] !== '' ? mb_strtolower(trim($data['email'])) : null,
            'store_name' => $data['store_name'] ?? null,
            'created_by' => $by?->id,
            'expires_at' => now()->addDays((int) ($data['days'] ?? self::DEFAULT_DAYS)),
        ]);
    }

    /** الدعوة من رمز الرابط (أي حالة كانت). */
    public static function findByToken(?string $token): ?self
    {
        $token = trim((string) $token);
        // يقبل الرابط كاملاً أو الرمز وحده (من خانة «رمز الدعوة» في التطبيق)
        if (str_contains($token, '/join/')) {
            $token = Str::of($token)->after('/join/')->before('?')->before('#')->trim('/')->toString();
        }
        if ($token === '' || strlen($token) > 100) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))->first();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** سبب عدم صلاحية الدعوة، أو null إن كانت صالحة. */
    public function problem(): ?string
    {
        return match (true) {
            $this->revoked_at !== null => __('هذه الدعوة أُلغيت. اطلب دعوة جديدة من إدارة تريندشام.'),
            $this->accepted_at !== null => __('هذه الدعوة استُعملت من قبل. كل دعوة تُستعمل مرة واحدة.'),
            $this->expires_at->isPast() => __('انتهت صلاحية هذه الدعوة. اطلب دعوة جديدة من إدارة تريندشام.'),
            default => null,
        };
    }

    /** هل يحق لهذا الهاتف/البريد قبول الدعوة؟ (إن رُبطت الدعوة بأحدهما) */
    public function allows(?string $phone, ?string $email): bool
    {
        if ($this->phone !== null && Phone::normalize($phone) !== $this->phone) {
            return false;
        }

        return $this->email === null || mb_strtolower(trim((string) $email)) === $this->email;
    }

    public function url(): string
    {
        return route('invitations.show', $this->token);
    }

    public function roleLabel(): string
    {
        return $this->role === 'seller' ? __('بائع') : __('موظف');
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->revoked_at !== null => __('ملغاة'),
            $this->accepted_at !== null => __('مقبولة'),
            $this->expires_at->isPast() => __('منتهية'),
            default => __('بانتظار القبول'),
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
