<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingCenter;
use Illuminate\Http\JsonResponse;

/** مراكز شركة الشحن الفعّالة، مجمّعة بالمحافظة: يختار منها الزبون مركز الاستلام والبائع مركز التسليم. */
class ShippingCenterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ShippingCenter::groupedForSelect()->map(fn ($centers, $governorate) => [
                'governorate' => $governorate,
                'governorate_label' => $governorate,
                'centers' => $centers->map(fn (ShippingCenter $center) => [
                    'id' => $center->id,
                    'code' => $center->company_code,
                    'name' => $center->name,
                    'address' => $center->address,
                    'phone' => $center->phone,
                    'hours' => $center->hours,
                ])->values(),
            ])->values(),
        ]);
    }
}
