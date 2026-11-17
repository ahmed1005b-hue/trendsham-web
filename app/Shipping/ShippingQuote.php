<?php

namespace App\Shipping;

use App\Models\PlatformSetting;
use Illuminate\Support\Collection;

/**
 * أجرة الشحن مع شركة كرم: كل بائع يشحن طرداً واحداً، وأجرة الطرد ثابتة بين كل المراكز
 * وترتفع إذا بلغ وزنه 10 كغ. التوصيل للبيت قد يضيف مبلغاً لكل طرد. المبالغ بالوحدة الصغرى ×100.
 */
class ShippingQuote
{
    public const HEAVY_FROM_GRAMS = 10000;

    public const METHODS = ['home', 'center'];

    /**
     * @param  Collection<int, object>  $items  بنود السلة: لكل بند product و quantity
     * @return array{fee: int, parcels: list<array{vendor_id: int, store_name: ?string, weight_grams: int, heavy: bool, fee: int}>}
     */
    public function quote(Collection $items, string $method = 'home'): array
    {
        $settings = PlatformSetting::current();
        $light = (int) ($settings->shipping_parcel_fee ?? 15000);
        $heavy = (int) ($settings->shipping_heavy_parcel_fee ?? 30000);
        $homeExtra = $method === 'home' ? (int) ($settings->shipping_home_extra ?? 0) : 0;

        $parcels = $items->groupBy(fn ($item) => $item->product->vendor_id)
            ->map(function (Collection $vendorItems, $vendorId) use ($light, $heavy, $homeExtra) {
                $grams = (int) $vendorItems->sum(fn ($item) => (int) $item->product->weight_grams * (int) $item->quantity);
                $isHeavy = $grams >= self::HEAVY_FROM_GRAMS;

                return [
                    'vendor_id' => (int) $vendorId,
                    'store_name' => $vendorItems->first()->product->relationLoaded('vendor') ? $vendorItems->first()->product->vendor?->store_name : null,
                    'weight_grams' => $grams,
                    'heavy' => $isHeavy,
                    'fee' => ($isHeavy ? $heavy : $light) + $homeExtra,
                ];
            })->values()->all();

        return ['fee' => (int) array_sum(array_column($parcels, 'fee')), 'parcels' => $parcels];
    }

    public static function method(?string $value): string
    {
        return in_array($value, self::METHODS, true) ? $value : 'home';
    }
}
