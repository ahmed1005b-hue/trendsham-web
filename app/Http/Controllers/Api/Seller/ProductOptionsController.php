<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Support\ProductOptions;
use Illuminate\Http\JsonResponse;

class ProductOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'colors' => array_map(fn (array $color): array => [
                'name' => $color['name'],
                'label' => __($color['name']),
                'hex' => $color['hex'],
            ], ProductOptions::colors()),
            'size_systems' => array_map(fn (array $system): array => [
                'key' => $system['key'],
                'label' => __($system['name']),
                'sizes' => $system['sizes'],
            ], ProductOptions::sizeSystems()),
        ]);
    }
}
