<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * يحوّل الإحداثيات إلى (مدينة، تفاصيل) لتعبئة نموذج العنوان مبدئياً.
 * مساعدة فقط: أي فشل يعيد null والمستخدم يكتب العنوان بنفسه.
 * يستخدم OpenStreetMap Nominatim مع كاش (سياسة الخدمة: طلب واحد/ثانية، User-Agent معرّف).
 */
class ReverseGeocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    /** @return array{city: string, details: string}|null */
    public function lookup(float $lat, float $lng): ?array
    {
        // ~11 متراً: نقاط متقاربة تشترك في نتيجة واحدة
        $key = sprintf('revgeo:%.4F,%.4F', $lat, $lng);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => Str::slug(config('app.name', 'app')).'/1.0 (address-autofill)',
                    'Accept-Language' => 'ar',
                ])
                ->get(self::ENDPOINT, [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::info('reverse geocode failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! is_array($address = $response->json('address'))) {
            return null;
        }

        $result = self::fromAddress($address);
        if ($result !== null) {
            Cache::put($key, $result, now()->addDays(30)); // لا نخزّن الفشل
        }

        return $result;
    }

    /** بادئات إدارية سورية لا تُكتب في عنوان الشحن */
    private const ADMIN_PREFIXES = ['محافظة ', 'منطقة ', 'ناحية ', 'بلدية '];

    /** @return array{city: string, details: string}|null */
    public static function fromAddress(array $a): ?array
    {
        $clean = function (?string $v): ?string {
            $v = is_string($v) ? trim($v) : '';
            foreach (self::ADMIN_PREFIXES as $p) {
                if (str_starts_with($v, $p)) {
                    $v = trim(substr($v, strlen($p)));
                }
            }

            return $v === '' ? null : $v;
        };
        $raw = fn (string $k): ?string => is_string($a[$k] ?? null) ? $a[$k] : null;
        // "بلدية المزة" حيّ داخل مدينة، لا مدينة بحد ذاتها
        $town = $raw('town');
        $townIsMunicipality = $town !== null && str_starts_with(trim($town), 'بلدية ');

        $city = null;
        foreach (['city', 'town', 'village', 'municipality', 'county', 'state_district', 'state'] as $k) {
            if ($k === 'town' && $townIsMunicipality) {
                continue;
            }
            if ($city = $clean($raw($k))) {
                break;
            }
        }
        $city ??= '';

        $parts = [];
        $groups = [['quarter', 'neighbourhood'], ['suburb', 'city_district'], $townIsMunicipality ? ['town'] : [], ['road'], ['house_number']];
        foreach ($groups as $keys) {
            foreach ($keys as $k) {
                $part = $clean($raw($k));
                if ($part !== null) {
                    if ($part !== $city && ! in_array($part, $parts, true)) {
                        $parts[] = $part;
                    }
                    break;
                }
            }
        }
        $details = implode('، ', $parts);

        if ($city === '' && $details === '') {
            return null;
        }

        return ['city' => Str::limit($city, 120, ''), 'details' => Str::limit($details, 500, '')];
    }
}
