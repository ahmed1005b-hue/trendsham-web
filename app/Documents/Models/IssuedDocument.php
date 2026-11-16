<?php

namespace App\Documents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** نسخة PDF صادرة من الموقع، يتحقق منها أي أحد برمزها. */
class IssuedDocument extends Model
{
    public const TYPES = [
        'invoice' => 'فاتورة طلب',
        'seller_statement' => 'كشف حساب بائع',
        'admin_report' => 'تقرير مالي',
    ];

    protected $fillable = ['code', 'type', 'subject_id', 'fingerprint', 'owner_user_id', 'issued_by', 'summary', 'details', 'file_hash', 'file_path'];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'details' => 'array'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'مستند';
    }

    /** TS-XXXX-XXXX-XXXX */
    public function displayCode(): string
    {
        return 'TS-'.implode('-', str_split($this->code, 4));
    }

    public function canSeeDetails(?User $user): bool
    {
        return $user !== null && ($user->role === 'admin' || $user->id === $this->owner_user_id);
    }
}
