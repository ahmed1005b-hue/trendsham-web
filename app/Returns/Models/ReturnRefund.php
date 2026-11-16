<?php

namespace App\Returns\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تسجيل الاسترداد اليدوي من المدير (واحد لكل طلب إرجاع). */
class ReturnRefund extends Model
{
    public const METHOD_LABELS = [
        'bank_transfer' => 'حوالة بنكية',
        'cash' => 'نقداً',
        'other' => 'أخرى',
    ];

    protected $fillable = ['return_request_id', 'amount', 'method', 'reference', 'note', 'recorded_by'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** تسمية طريقة الاسترداد بالعربية (تُترجم عند العرض). */
    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method] ?? (string) $this->method;
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
