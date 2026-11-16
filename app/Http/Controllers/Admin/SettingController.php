<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Returns\ReturnSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', ['setting' => PlatformSetting::current()]);
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

        return back()->with('success', 'تم تحديث الإعدادات.');
    }
}
