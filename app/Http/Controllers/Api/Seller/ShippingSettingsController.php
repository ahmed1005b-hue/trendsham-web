<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Shipping\Governorates;
use App\Shipping\SelfDelivery;
use App\Shipping\StoreShippingForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إعدادات شحن المتجر في التطبيق (نفس صفحة «إعدادات الشحن» في الموقع).
 * المبالغ بالليرة.
 */
class ShippingSettingsController extends Controller
{
    public function show(Request $request, SelfDelivery $selfDelivery): JsonResponse
    {
        return response()->json($this->present($request->user()->sellerProfile, $selfDelivery));
    }

    public function update(Request $request, StoreShippingForm $form, SelfDelivery $selfDelivery): JsonResponse
    {
        $seller = $request->user()->sellerProfile;
        $form->save($seller, $request->validate(StoreShippingForm::rules(), StoreShippingForm::messages()));

        return response()->json([
            'message' => __('تم حفظ إعدادات الشحن.'),
            ...$this->present($seller->fresh(), new SelfDelivery),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(SellerProfile $seller, SelfDelivery $selfDelivery): array
    {
        $seller->load('shippingSetting', 'shippingRates', 'wallet');
        $setting = $seller->shippingSetting;
        $fees = $seller->shippingRates->pluck('fee', 'governorate');
        $status = $selfDelivery->status($seller);

        return [
            'mode' => $setting->mode ?? 'platform',
            'free_over' => $setting?->free_over ? $setting->free_over / 100 : null,
            'prep_days' => $setting->prep_days ?? 1,
            'delivery_days' => $setting->delivery_days ?? 2,
            'allow_pickup' => (bool) ($setting?->allow_pickup),
            'pickup_address' => $setting?->pickup_address,
            'rates' => collect(Governorates::ALL)->map(fn (string $g) => [
                'governorate' => $g,
                'label' => __($g),
                'enabled' => $fees->has($g),
                'fee' => $fees->has($g) ? $fees[$g] / 100 : null,
            ])->values(),
            'status' => [
                'active' => $status['active'],
                'reason' => $status['reason'],
                'debt' => $status['debt'] / 100,
                'limit' => $status['limit'] / 100,
                'delivered' => $status['delivered'],
                'min_orders' => $status['min_orders'],
            ],
        ];
    }
}
