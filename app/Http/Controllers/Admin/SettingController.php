<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Returns\ReturnSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** أجور الشحن مع كرم، تُكتب بالليرة وتُحفظ بالوحدة الصغرى. */
    private const SHIPPING_FEES = ['shipping_parcel_fee', 'shipping_heavy_parcel_fee', 'shipping_home_extra'];

    public function edit()
    {
        return view('admin.settings.edit', ['setting' => PlatformSetting::current()]);
    }

    public function update(Request $request)
    {
        // الرقم يُكتب كيفما كان (+963 9xx أو 09xx) ويُحفظ أرقاماً دولية فقط
        if ($request->filled('social_whatsapp')) {
            $digits = preg_replace('/\D/', '', (string) $request->input('social_whatsapp'));
            $digits = match (true) {
                str_starts_with($digits, '00') => substr($digits, 2),
                str_starts_with($digits, '0') => '963'.substr($digits, 1),
                default => $digits,
            };
            $request->merge(['social_whatsapp' => $digits]);
        }

        $socialRules = [];
        foreach (PlatformSetting::SOCIAL as $field => $meta) {
            $socialRules[$field] = ['bail', 'sometimes', 'nullable', 'string', 'max:255', 'url:https', $this->hostRule($meta['host'], $meta['label'])];
        }

        $validated = $request->validate([
            'commission_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'story_fee' => ['required', 'numeric', 'min:0'],
            ...array_fill_keys(self::SHIPPING_FEES, ['sometimes', 'required', 'numeric', 'min:0', 'max:100000000']),
            ...array_fill_keys(array_keys(ReturnSettings::DEFAULTS), ['sometimes', 'required', 'integer', 'min:1', 'max:365']),
            ...$socialRules,
            'social_whatsapp' => ['sometimes', 'nullable', 'digits_between:8,15'],
        ], [
            'url' => 'اكتب الرابط كاملاً ويبدأ بـ https://',
            'social_whatsapp.digits_between' => 'اكتب رقم واتساب مع رمز الدولة، مثل 963912345678.',
        ]);

        PlatformSetting::current()->update([
            'commission_rate' => $validated['commission_rate'],
            'story_fee' => (int) round($validated['story_fee'] * 100),
            ...array_map(fn ($lira) => (int) round($lira * 100), array_intersect_key($validated, array_flip(self::SHIPPING_FEES))),
            ...array_intersect_key($validated, ReturnSettings::DEFAULTS),
            ...array_intersect_key($validated, PlatformSetting::SOCIAL + ['social_whatsapp' => true]),
        ]);

        return back()->with('success', 'تم تحديث الإعدادات.');
    }

    /** الرابط يجب أن يكون لنفس الموقع (مثلاً رابط تيك توك في خانة تيك توك). */
    private function hostRule(string $host, string $label): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($host, $label) {
            $actual = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $allowed = $host === 'x.com' ? ['x.com', 'twitter.com'] : [$host];
            foreach ($allowed as $domain) {
                if ($actual === $domain || str_ends_with($actual, '.'.$domain)) {
                    return;
                }
            }
            $fail(__('هذا ليس رابط :site.', ['site' => __($label)]));
        };
    }
}
