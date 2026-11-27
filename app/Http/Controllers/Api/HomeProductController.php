<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Support\AppCache;
use App\Support\HomeProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeProductController extends Controller
{
    /** GET /api/v1/home/products: «وصل حديثاً» و«العروض» كما في رئيسية الموقع. */
    public function index(Request $request): JsonResponse
    {
        $sections = Cache::remember(AppCache::HOME_PRODUCTS, AppCache::HOME_TTL, fn () => [
            'new_arrivals' => HomeProducts::newArrivals(),
            'deals' => HomeProducts::deals(),
        ]);

        return response()->json([
            'new_arrivals' => ProductResource::collection($sections['new_arrivals'])->resolve($request),
            'deals' => ProductResource::collection($sections['deals'])->resolve($request),
        ]);
    }
}
