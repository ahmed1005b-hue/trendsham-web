<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Returns\ReturnSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show()
    {
        $setting = PlatformSetting::current();

        return response()->json([
            'commission_rate' => $setting->commission_rate,
            'story_fee' => $setting->story_fee / 100,
            ...collect(ReturnSettings::DEFAULTS)->map(fn (int $default, string $key) => (int) ($setting->getAttribute($key) ?? $default))->all(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'commission_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'story_fee' => ['required', 'numeric', 'min:0'],
            ...array_fill_keys(array_keys(ReturnSettings::DEFAULTS), ['sometimes', 'required', 'integer', 'min:1', 'max:365']),
        ]);

        PlatformSetting::current()->update([
            'commission_rate' => $validated['commission_rate'],
            'story_fee' => (int) round($validated['story_fee'] * 100),
            ...array_intersect_key($validated, ReturnSettings::DEFAULTS),
        ]);

        return response()->json(['message' => 'تم تحديث الإعدادات.']);
    }
}
