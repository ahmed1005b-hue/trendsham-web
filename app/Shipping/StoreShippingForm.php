<?php

namespace App\Shipping;

use App\Models\SellerProfile;
use App\Models\StoreShippingRate;
use App\Models\StoreShippingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * نموذج إعدادات شحن المتجر، مشترك بين لوحة البائع في الموقع والتطبيق:
 * نفس القواعد ونفس الحفظ. المبالغ تُكتب بالليرة وتُخزَّن ×100.
 */
class StoreShippingForm
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(StoreShippingSetting::MODES)],
            'rates' => ['nullable', 'array'],
            'rates.*.enabled' => ['nullable', 'boolean'],
            'rates.*.fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'free_over' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'prep_days' => ['required', 'integer', 'min:0', 'max:14'],
            'delivery_days' => ['required', 'integer', 'min:0', 'max:14'],
            'allow_pickup' => ['nullable', 'boolean'],
            'pickup_address' => ['nullable', 'required_if_accepted:allow_pickup', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'pickup_address.required_if_accepted' => __('اكتب عنوان محلك حتى يعرف الزبون أين يستلم طلبه.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  بيانات مُتحقق منها بـ rules()
     *
     * @throws ValidationException التوصيل الخاص بلا أي محافظة
     */
    public function save(SellerProfile $seller, array $data): void
    {
        $rates = collect($data['rates'] ?? [])
            ->only(Governorates::ALL)
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->map(fn ($row) => (int) round(((float) ($row['fee'] ?? 0)) * 100));

        if ($data['mode'] === 'self' && $rates->isEmpty()) {
            throw ValidationException::withMessages(['rates' => __('اختر محافظة واحدة على الأقل توصل إليها بنفسك، واكتب سعرها.')]);
        }

        DB::transaction(function () use ($seller, $data, $rates) {
            StoreShippingSetting::updateOrCreate(['seller_profile_id' => $seller->id], [
                'mode' => $data['mode'],
                'free_over' => isset($data['free_over']) && (float) $data['free_over'] > 0 ? (int) round($data['free_over'] * 100) : null,
                'prep_days' => (int) $data['prep_days'],
                'delivery_days' => (int) $data['delivery_days'],
                'allow_pickup' => (bool) ($data['allow_pickup'] ?? false),
                'pickup_address' => ($data['allow_pickup'] ?? false) ? trim((string) $data['pickup_address']) : null,
            ]);

            StoreShippingRate::where('seller_profile_id', $seller->id)->whereNotIn('governorate', $rates->keys())->delete();
            foreach ($rates as $governorate => $fee) {
                StoreShippingRate::updateOrCreate(['seller_profile_id' => $seller->id, 'governorate' => $governorate], ['fee' => $fee]);
            }
        });
    }
}
