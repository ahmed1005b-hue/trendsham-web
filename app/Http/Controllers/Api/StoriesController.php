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
            'data' => $stories->map(fn (Story $story) => $story->toViewerArray()),
        ]);
    }
}
