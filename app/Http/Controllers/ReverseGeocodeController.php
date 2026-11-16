<?php

namespace App\Http\Controllers;

use App\Services\ReverseGeocoder;
use Illuminate\Http\Request;

/** اقتراح مدينة وتفاصيل من الإحداثيات (الويب والتطبيق). data = null إن لم يُعرف العنوان. */
class ReverseGeocodeController extends Controller
{
    public function __invoke(Request $request, ReverseGeocoder $geocoder)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'data' => $geocoder->lookup((float) $data['latitude'], (float) $data['longitude']),
        ]);
    }
}
