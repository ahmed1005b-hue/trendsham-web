<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponCampaignTarget;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء وتعديل كوبونات البائع. البائع يتحمّل الخصم، فالقواعد أضيق من كوبونات المنصة:
 * نسبة حتى 70% (فوق 50% تنتظر موافقة المدير)، نطاق متجره فقط، عدد ونهاية إلزاميان.
 * المدخلات بالليرة، وتُحفظ بالوحدة الصغرى (×100).
 */
class SellerCampaignWriter
{
    public const MAX_PERCENT = 70;

    public static function rules(SellerProfile $seller): array
    {
        $own = fn () => Rule::exists('products', 'id')->where('vendor_id', $seller->id);

        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['percentage', 'fixed', 'free_shipping', 'gift'])],
            'value' => ['exclude_if:type,free_shipping', 'exclude_if:type,gift', 'required', 'numeric', 'min:1',
                Rule::when(fn ($in) => $in->type === 'percentage', ['integer', 'max:'.self::MAX_PERCENT])],
            'max_discount' => ['exclude_unless:type,percentage', 'required', 'numeric', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'buy_product_id' => ['exclude_unless:type,gift', 'required', 'integer', $own()],
            'gift_product_id' => ['exclude_unless:type,gift', 'required', 'integer', $own(),
                function ($attr, $value, $fail) {
                    if (Product::whereKey($value)->whereHas('variants')->exists()) {
                        $fail('اختر منتج هدية بلا ألوان أو مقاسات.');
                    }
                }],
            'scope' => ['required', Rule::in(['all', 'products'])],
            'product_ids' => ['exclude_unless:scope,products', 'required', 'array', 'min:1'],
            'product_ids.*' => ['integer', $own()],
            'audience' => ['required', Rule::in(['collectable', 'followers'])],
            'auto_followers' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'ends_at' => ['required', 'date', 'after:now', 'after_or_equal:starts_at', 'before_or_equal:'.now()->addYear()->toDateString()],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'total_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_customer_limit' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public static function messages(): array
    {
        return [
            'title.required' => 'اكتب اسماً للكوبون.',
            'value.required' => 'أدخل قيمة الخصم.',
            'value.min' => 'قيمة الخصم يجب أن تكون أكبر من صفر.',
            'value.max' => __('أعلى نسبة مسموحة :max%.', ['max' => self::MAX_PERCENT]),
            'max_discount.required' => 'حدد أعلى مبلغ للخصم بالنسبة المئوية.',
            'product_ids.required' => 'اختر منتجاً واحداً على الأقل من متجرك.',
            'product_ids.*.exists' => 'هذا المنتج ليس من متجرك.',
            'buy_product_id.required' => 'اختر المنتج الذي يشتريه العميل.',
            'buy_product_id.exists' => 'هذا المنتج ليس من متجرك.',
            'gift_product_id.required' => 'اختر منتج الهدية.',
            'gift_product_id.exists' => 'هذا المنتج ليس من متجرك.',
            'ends_at.required' => 'حدد تاريخ انتهاء الكوبون.',
            'ends_at.after' => 'تاريخ الانتهاء يجب أن يكون في المستقبل.',
            'ends_at.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد البداية.',
            'ends_at.before_or_equal' => 'أطول مدة للكوبون سنة واحدة.',
            'starts_at.after_or_equal' => 'تاريخ البداية لا يكون في الماضي.',
            'total_limit.required' => 'حدد عدد الكوبونات حتى تعرف أقصى تكلفة.',
            'total_limit.max' => 'أعلى عدد 100,000 كوبون.',
            'per_customer_limit.max' => 'أعلى عدد لكل عميل 5.',
        ];
    }

    private static function minor($v): ?int
    {
        return $v === null || $v === '' ? null : (int) round(((float) $v) * 100);
    }

    /** @param array $data بيانات مُتحقَّق منها بـ rules() */
    public function create(array $data, SellerProfile $seller, User $by): CouponCampaign
    {
        $campaign = DB::transaction(function () use ($data, $seller, $by) {
            $campaign = CouponCampaign::create($this->attributes($data) + [
                'funder' => 'seller',
                'seller_profile_id' => $seller->id,
                'status' => self::needsReview($data) ? 'pending_review' : 'active',
                'created_by' => $by->id,
            ]);
            $this->syncTargets($campaign, $data);

            return $campaign;
        });

        return $campaign;
    }

    /** قبل أول جمع: كل الحقول. بعده: تمديد النهاية وزيادة العدد فقط (ما حفظه العميل لا يتغير). */
    public function update(CouponCampaign $campaign, array $data): CouponCampaign
    {
        if ($campaign->isLocked()) {
            $errors = [];
            $ends = isset($data['ends_at']) ? \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay() : null;
            if ($ends && $campaign->ends_at && $ends->lt($campaign->ends_at)) {
                $errors['ends_at'] = 'بعد أن جمعه العملاء يمكنك تمديد المدة فقط، لا تقصيرها.';
            }
            if (isset($data['total_limit']) && (int) $data['total_limit'] < (int) $campaign->total_limit) {
                $errors['total_limit'] = 'بعد أن جمعه العملاء يمكنك زيادة العدد فقط.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $campaign->update(array_filter([
                'ends_at' => $ends,
                'total_limit' => isset($data['total_limit']) ? (int) $data['total_limit'] : null,
            ]));

            return $campaign;
        }

        DB::transaction(function () use ($campaign, $data) {
            $attrs = $this->attributes($data);
            // رفع النسبة فوق 50% يعيده للمراجعة؛ لا يخرج من مراجعة المدير بتعديل
            if (self::needsReview($data) && $campaign->status !== 'suspended') {
                $attrs['status'] = 'pending_review';
            }
            $campaign->update($attrs);
            $campaign->targets()->delete();
            $this->syncTargets($campaign, $data);
        });

        return $campaign->fresh();
    }

    public static function needsReview(array $data): bool
    {
        return ($data['type'] ?? null) === 'percentage' && (int) ($data['value'] ?? 0) > CouponCampaign::REVIEW_ABOVE_PERCENT;
    }

    private function attributes(array $data): array
    {
        $type = $data['type'];

        return [
            'title' => $data['title'],
            'type' => $type,
            'value' => match ($type) {
                'percentage' => (int) $data['value'],
                'fixed' => self::minor($data['value']),
                default => 0,
            },
            'max_discount' => $type === 'percentage' ? self::minor($data['max_discount']) : null,
            'min_order_amount' => self::minor($data['min_order_amount'] ?? 0) ?? 0,
            'buy_product_id' => $type === 'gift' ? (int) $data['buy_product_id'] : null,
            'gift_product_id' => $type === 'gift' ? (int) $data['gift_product_id'] : null,
            'scope' => $data['scope'],
            'audience' => $data['audience'],
            'auto_followers' => $data['audience'] === 'collectable' && ! empty($data['auto_followers']),
            'starts_at' => ! empty($data['starts_at']) ? $data['starts_at'] : now(),
            'ends_at' => \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay(),
            'valid_days' => $data['valid_days'] ?? null,
            'per_customer_limit' => (int) $data['per_customer_limit'],
            'total_limit' => (int) $data['total_limit'],
        ];
    }

    private function syncTargets(CouponCampaign $campaign, array $data): void
    {
        if ($data['scope'] !== 'products') {
            return;
        }
        foreach (array_unique(array_map('intval', $data['product_ids'])) as $id) {
            CouponCampaignTarget::create(['campaign_id' => $campaign->id, 'target_type' => 'product', 'target_id' => $id]);
        }
    }

    /**
     * معاينة لطلب نموذجي: كم الخصم، كم يدفع العميل، كم يصل البائع بعد العمولة (على السعر بعد الخصم).
     * @return array{discount:int, customer_pays:int, seller_gets:int}
     */
    public static function previewAmounts(string $type, int $value, ?int $maxDiscount, int $sampleTotal, float $commissionRate): array
    {
        $discount = match ($type) {
            'percentage' => (int) round($sampleTotal * $value / 100),
            'fixed' => $value,
            default => 0,
        };
        if ($type === 'percentage' && $maxDiscount) {
            $discount = min($discount, $maxDiscount);
        }
        $discount = max(0, min($discount, $sampleTotal));
        $base = $sampleTotal - $discount;

        return [
            'discount' => $discount,
            'customer_pays' => $base,
            'seller_gets' => $base - (int) round($base * $commissionRate / 100),
        ];
    }
}
