<?php

namespace App\Shipping;

use App\Models\PlatformSetting;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;

/**
 * أجرة الشحن: كل متجر يشحن طرداً واحداً، وكل متجر يقرر طريقة شحن طرده:
 *  - platform: عبر شركة المنصة (كرم)؛ أجرة ثابتة بين كل المراكز وترتفع إذا بلغ الوزن 10 كغ،
 *    والتوصيل للبيت قد يضيف مبلغاً لكل طرد.
 *  - self: المتجر يوصل بنفسه إلى البيت بسعره لمحافظة الزبون (ومجاناً فوق مبلغ يحدده).
 *    محافظة لم يحدد لها سعراً، أو استلام من مركز شحن، أو توصيل خاص متوقف ← عبر شركة المنصة.
 *  - pickup: الزبون يستلم طرد المتجر من محله، بلا أجرة.
 * المبالغ بالوحدة الصغرى ×100.
 */
class ShippingQuote
{
    public const HEAVY_FROM_GRAMS = 10000;

    public const METHODS = ['home', 'center'];

    /** مدة وصول طرد شركة المنصة بعد تجهيزه (أيام). */
    public const PLATFORM_DAYS = [2, 4];

    public function __construct(private readonly SelfDelivery $selfDelivery) {}

    /**
     * @param  Collection<int, object>  $items  بنود السلة: لكل بند product و quantity (و subtotal)
     * @param  list<int>  $pickupVendorIds  متاجر اختار الزبون أن يستلم طردها من محلها
     * @return array{fee: int, parcels: list<array{vendor_id: int, store_name: ?string, weight_grams: int, heavy: bool, fee: int, mode: string, free: bool, eta_min: int, eta_max: int, can_pickup: bool, pickup_address: ?string}>}
     */
    public function quote(Collection $items, string $method = 'home', ?string $governorate = null, array $pickupVendorIds = []): array
    {
        $settings = PlatformSetting::current();
        $light = (int) ($settings->shipping_parcel_fee ?? 15000);
        $heavy = (int) ($settings->shipping_heavy_parcel_fee ?? 30000);
        $homeExtra = $method === 'home' ? (int) ($settings->shipping_home_extra ?? 0) : 0;
        $governorate = Governorates::isValid($governorate) ? $governorate : null;
        $pickupVendorIds = array_map('intval', $pickupVendorIds);

        $groups = $items->groupBy(fn ($item) => $item->product->vendor_id);
        $vendors = SellerProfile::with(['shippingSetting', 'shippingRates', 'wallet'])
            ->whereIn('id', $groups->keys())->get()->keyBy('id');

        $parcels = $groups->map(function (Collection $vendorItems, $vendorId) use ($light, $heavy, $homeExtra, $method, $governorate, $pickupVendorIds, $vendors) {
            $vendor = $vendors->get($vendorId);
            $setting = $vendor?->shippingSetting;
            $grams = (int) $vendorItems->sum(fn ($item) => (int) $item->product->weight_grams * (int) $item->quantity);
            $isHeavy = $grams >= self::HEAVY_FROM_GRAMS;
            $goods = (int) $vendorItems->sum(fn ($item) => (int) ($item->subtotal ?? 0));
            $prep = (int) ($setting?->prep_days ?? 1);
            $canPickup = (bool) $setting?->offersPickup();

            $parcel = [
                'vendor_id' => (int) $vendorId,
                'store_name' => $vendor?->store_name,
                'weight_grams' => $grams,
                'heavy' => $isHeavy,
                'fee' => ($isHeavy ? $heavy : $light) + $homeExtra,
                'mode' => 'platform',
                'free' => false,
                'eta_min' => $prep + self::PLATFORM_DAYS[0],
                'eta_max' => $prep + self::PLATFORM_DAYS[1],
                'can_pickup' => $canPickup,
                'pickup_address' => $canPickup ? $setting->pickup_address : null,
            ];

            if ($canPickup && in_array((int) $vendorId, $pickupVendorIds, true)) {
                return [...$parcel, 'fee' => 0, 'mode' => 'pickup', 'free' => true, 'eta_min' => $prep, 'eta_max' => $prep];
            }

            $rate = $governorate ? $vendor?->shippingRates->firstWhere('governorate', $governorate) : null;
            if ($method === 'home' && $rate && $vendor && $this->selfDelivery->status($vendor)['active']) {
                $free = $setting->free_over !== null && $setting->free_over > 0 && $goods >= $setting->free_over;
                $days = $prep + max(0, (int) $setting->delivery_days);

                return [...$parcel, 'fee' => $free ? 0 : $rate->fee, 'mode' => 'self', 'free' => $free || $rate->fee === 0, 'eta_min' => $days, 'eta_max' => $days + 1];
            }

            return $parcel;
        })->values()->all();

        return ['fee' => (int) array_sum(array_column($parcels, 'fee')), 'parcels' => $parcels];
    }

    public static function method(?string $value): string
    {
        return in_array($value, self::METHODS, true) ? $value : 'home';
    }

    /** نص مدة الوصول للعرض: «يصل خلال 3 إلى 5 أيام» أو «جاهز للاستلام خلال يوم». */
    public static function etaLabel(array $parcel): string
    {
        $min = max(0, (int) $parcel['eta_min']);
        $max = max($min, (int) $parcel['eta_max']);

        if ($parcel['mode'] === 'pickup') {
            return $min === 0 ? __('جاهز للاستلام في نفس اليوم') : __('جاهز للاستلام خلال :days يوم', ['days' => $min]);
        }
        if ($max <= 1) {
            return __('يصل خلال يوم');
        }

        return $min === $max || $min === 0
            ? __('يصل خلال :days أيام', ['days' => $max])
            : __('يصل خلال :min إلى :max أيام', ['min' => $min, 'max' => $max]);
    }

    /** اسم طريقة شحن الطرد للعرض. */
    public static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'self' => __('توصيل من المتجر نفسه'),
            'pickup' => __('استلام من محل المتجر'),
            default => __('عبر شركة الشحن'),
        };
    }
}
