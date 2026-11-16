<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;

class StoriesController extends Controller
{
    public function index()
    {
        $stories = Story::visible()
            ->with('vendor')
            ->orderByRaw('vendor_id IS NOT NULL')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $stories->map(fn (Story $story) => [
                'id' => $story->id,
                'image' => asset('storage/'.$story->image),
                'caption' => $story->caption,
                'is_from_platform' => $story->isFromPlatform(),
                'vendor' => $story->vendor ? [
                    'id' => $story->vendor->id,
                    'store_name' => $story->vendor->store_name,
                    'store_logo' => $story->vendor->store_logo
                        ? asset('storage/'.$story->vendor->store_logo)
                        : null,
                ] : null,
                'expires_at' => $story->expires_at,
            ]),
        ]);
    }
}
