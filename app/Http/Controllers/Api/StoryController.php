<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    /**
     * عرض الستوريات النشطة للمستخدمين (Flutter App)
     */
    public function index()
    {
        $stories = Story::with('vendor')
            ->visible()
            ->latest()
            ->get();

        return response()->json([
            'data' => $stories->map(fn($story) => [
                'id' => $story->id,
                'image' => asset('storage/' . $story->image),
                'caption' => $story->caption,
                'vendor' => $story->vendor ? [
                    'id' => $story->vendor->id,
                    'store_name' => $story->vendor->store_name,
                    'logo' => $story->vendor->logo ? asset('storage/' . $story->vendor->logo) : null,
                ] : [
                    'store_name' => 'TrendSham',
                    'logo' => asset('assets/logo.png'), // شعار الافتراضي للمنصة
                ],
                'created_at' => $story->created_at,
            ])
        ]);
    }
}
