<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/** حسابات تريندشام على مواقع التواصل (نفس أيقونات أسفل الموقع) لقسم «تابعنا» في التطبيق. */
class SocialLinkController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $links = array_map(fn (array $link) => [
            'key' => Str::after($link['icon'], 'bi-'), // instagram | facebook | tiktok | twitter-x | whatsapp
            'label' => __($link['label']),
            'url' => $link['url'],
        ], PlatformSetting::current()->socialLinks());

        return response()->json(['data' => $links]);
    }
}
