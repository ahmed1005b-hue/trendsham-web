<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponCampaignTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء حملة كوبونات المنصة من لوحة المدير (الموقع والتطبيق).
 * المدخلات بالليرة، وتُحفظ بالوحدة الصغرى (×100) مثل باقي المبالغ.
 */
class CampaignWriter
{
    public function __construct(private readonly CouponIssuer $issuer) {}

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['percentage', 'fixed', 'free_shipping'])],
            'value' => ['required_unless:type,free_shipping', 'nullable', 'numeric', 'min:1',
                Rule::when(fn ($in) => $in->type === 'percentage', ['max:100'])],
            'max_discount' => ['nullable', 'numeric', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'scope' => ['required', Rule::in(['all', 'categories', 'stores'])],
            'category_ids' => ['required_if:scope,categories', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id,deleted_at,NULL'],
            'store_ids' => ['required_if:scope,stores', 'array'],
            'store_ids.*' => ['integer', 'exists:seller_profiles,id'],
            'audience' => ['required', Rule::in(['new_customers', 'all_customers', 'specific'])],
            'customer_contact' => ['required_if:audience,specific', 'nullable', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:now', 'after:starts_at'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'per_customer_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'total_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public static function messages(): array
    {
        return [
            'value.max' => 'النسبة المئوية بين 1 و100.',
            'value.required_unless' => 'أدخل قيمة الخصم.',
            'customer_contact.required_if' => 'اكتب هاتف العميل أو بريده.',
            'category_ids.required_if' => 'اختر قسماً واحداً على الأقل.',
            'store_ids.required_if' => 'اختر متجراً واحداً على الأقل.',
            'ends_at.after' => 'تاريخ النهاية يجب أن يكون بعد البداية وفي المستقبل.',
        ];
    }

    /** @param array $data بيانات مُتحقَّق منها بـ rules() */
    public function create(array $data, User $admin): CouponCampaign
    {
        $target = null;
        if ($data['audience'] === 'specific') {
            $contact = trim((string) $data['customer_contact']);
            // الهواتف محفوظة بصيغة دولية موحّدة (963…)، فنبحث بنفس قاعدة تسجيل الدخول
            $target = str_contains($contact, '@')
                ? User::where('role', 'customer')->where('email', $contact)->first()
                : User::where('role', 'customer')->phoneIs($contact)->first();
            if (! $target) {
                throw ValidationException::withMessages(['customer_contact' => 'لا يوجد عميل بهذا الهاتف أو البريد.']);
            }
        }

        $minor = fn ($v) => $v === null || $v === '' ? null : (int) round(((float) $v) * 100);

        $campaign = DB::transaction(function () use ($data, $admin, $minor) {
            $campaign = CouponCampaign::create([
                'funder' => 'platform',
                'title' => $data['title'],
                'type' => $data['type'],
                'value' => match ($data['type']) {
                    'percentage' => (int) $data['value'],
                    'fixed' => $minor($data['value']),
                    default => 0,
                },
                'max_discount' => $data['type'] === 'percentage' ? $minor($data['max_discount'] ?? null) : null,
                'min_order_amount' => $minor($data['min_order_amount'] ?? 0) ?? 0,
                'scope' => $data['scope'],
                'audience' => $data['audience'],
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'valid_days' => $data['valid_days'] ?? null,
                'per_customer_limit' => (int) $data['per_customer_limit'],
                'total_limit' => $data['total_limit'] ?? null,
                'status' => 'active',
                'created_by' => $admin->id,
            ]);

            foreach ([['category', $data['category_ids'] ?? []], ['store', $data['store_ids'] ?? []]] as [$type, $ids]) {
                if (($type === 'category' && $data['scope'] === 'categories') || ($type === 'store' && $data['scope'] === 'stores')) {
                    foreach (array_unique($ids) as $id) {
                        CouponCampaignTarget::create(['campaign_id' => $campaign->id, 'target_type' => $type, 'target_id' => (int) $id]);
                    }
                }
            }

            return $campaign;
        });

        if ($target) {
            CouponCampaignTarget::create(['campaign_id' => $campaign->id, 'target_type' => 'user', 'target_id' => $target->id]);
            $this->issuer->issueToSpecific($campaign, [$target->id]);
        }

        return $campaign;
    }

    /** عدد من سيصلهم الكوبون (للتأكيد قبل الحفظ). */
    public static function audienceSize(string $audience): int
    {
        $q = User::where('role', 'customer');

        return match ($audience) {
            'new_customers' => $q->whereNotNull('phone_verified_at')
                ->whereDoesntHave('orders', fn ($o) => $o->where('status', '!=', 'cancelled'))->count(),
            'all_customers' => $q->count(),
            default => 1,
        };
    }
}
