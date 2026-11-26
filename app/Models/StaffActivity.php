<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سطر في سجل نشاط فريق المنصة. يُنشأ عبر StaffActivityLog فقط، ولا يُعدَّل بعد كتابته.
 *
 * @property array<string, mixed>|null $properties
 */
class StaffActivity extends Model
{
    public const UPDATED_AT = null;

    /**
     * وصف كل عملية بالعربية (مفاتيح قاموس lang/en.json و tr.json).
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'order.status' => 'غيّر حالة الطلب :order من «:from» إلى «:to»',
        'order.archive' => 'أرشف الطلب :order',
        'order.refused' => 'سجّل رفض استلام طرد في الطلب :order',
        'product.update' => 'عدّل بيانات المنتج «:name»',
        'product.archive' => 'أخفى المنتج «:name» من المتجر',
        'product.approve' => 'وافق على المنتج «:name»',
        'product.reject' => 'رفض المنتج «:name»: :note',
        'support.reply' => 'ردّ على البائع في محادثة «:subject»',
        'support.close' => 'أغلق محادثة الدعم «:subject»',
        'support.reopen' => 'أعاد فتح محادثة الدعم «:subject»',
        'chat.reply' => 'ردّ على العميل :customer',
        'chat.close' => 'أغلق محادثة العميل :customer',
        'return.approve' => 'وافق على الإرجاع :number',
        'return.reject' => 'رفض الإرجاع :number: :note',
        'return.received' => 'أكّد استلام المرتجع :number',
        'return.refund' => 'سجّل استرداد :amount ل.س للإرجاع :number',
    ];

    /** مجموعات التصفية في صفحة السجل. */
    public const GROUPS = [
        'order' => 'الطلبات',
        'product' => 'المنتجات',
        'support' => 'دعم البائعين',
        'chat' => 'محادثات العملاء',
        'return' => 'المرتجعات',
    ];

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_role',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * تصفية صفحة السجل (الموقع والتطبيق): الشخص، ونوع العمل، والفترة.
     *
     * @param  array{user_id?: mixed, group?: mixed, from?: mixed, to?: mixed}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $userId = filter_var($filters['user_id'] ?? null, FILTER_VALIDATE_INT);
        $group = $filters['group'] ?? null;

        return $query
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->when(is_string($group) && isset(self::GROUPS[$group]), fn (Builder $q) => $q->where('action', 'like', $group.'.%'))
            ->when($this->validDate($filters['from'] ?? null), fn (Builder $q, string $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($this->validDate($filters['to'] ?? null), fn (Builder $q, string $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at')
            ->latest('id');
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) && strtotime($value) !== false ? $value : null;
    }

    /** الوصف بلغة القارئ الحالية. */
    public function description(): string
    {
        $properties = collect($this->properties ?? [])
            ->map(fn ($value, $key) => in_array($key, ['from', 'to'], true)
                ? __(Order::STATUS_LABELS[$value] ?? (string) $value)
                : (string) $value)
            ->all();

        return __(self::ACTIONS[$this->action] ?? $this->action, $properties);
    }

    /** رابط الشيء الذي جرى عليه العمل في لوحة المدير، أو null إن لم يكن له صفحة. */
    public function subjectUrl(): ?string
    {
        if ($this->subject_id === null) {
            return null;
        }

        return match ($this->subject_type) {
            'order' => route('admin.orders.show', $this->subject_id),
            'support' => route('admin.support.show', $this->subject_id),
            'chat' => route('admin.assistant-chats.show', $this->subject_id),
            'return' => route('admin.returns.show', $this->subject_id),
            default => null,
        };
    }
}
