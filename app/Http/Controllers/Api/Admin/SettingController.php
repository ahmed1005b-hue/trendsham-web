<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Returns\ReturnSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** مبالغ بالليرة تُخزَّن ×100 (نفس حقول صفحة الإعدادات في الموقع) */
    private const MONEY = ['shipping_parcel_fee', 'shipping_heavy_parcel_fee', 'shipping_home_extra', 'seller_debt_limit'];

    public function show()
    {
        $setting = PlatformSetting::current();

        return response()->json([
            'commission_rate' => $setting->commission_rate,
            'story_fee' => $setting->story_fee / 100,
            ...collect(self::MONEY)->mapWithKeys(fn (string $key) => [$key => (int) $setting->getAttribute($key) / 100])->all(),
            'self_delivery_min_orders' => (int) ($setting->self_delivery_min_orders ?? 5),
            ...collect(ReturnSettings::DEFAULTS)->map(fn (int $default, string $key) => (int) ($setting->getAttribute($key) ?? $default))->all(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'commission_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'story_fee' => ['required', 'numeric', 'min:0'],
            ...array_fill_keys(array_keys(ReturnSettings::DEFAULTS), ['sometimes', 'required', 'integer', 'min:1', 'max:365']),
            ...array_fill_keys(self::MONEY, ['sometimes', 'required', 'numeric', 'min:0', 'max:100000000']),
            'self_delivery_min_orders' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000'],
        ]);

        PlatformSetting::current()->update([
            'commission_rate' => $validated['commission_rate'],
            'story_fee' => (int) round($validated['story_fee'] * 100),
            ...array_intersect_key($validated, ReturnSettings::DEFAULTS),
            ...array_map(fn ($lira) => (int) round($lira * 100), array_intersect_key($validated, array_flip(self::MONEY))),
            ...array_intersect_key($validated, ['self_delivery_min_orders' => true]),
        ]);

        return response()->json(['message' => 'تم تحديث الإعدادات.']);
    }
}
