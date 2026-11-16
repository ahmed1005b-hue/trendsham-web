<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShowcaseTrackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.showcase_id' => ['required', 'integer', 'exists:showcases,id'],
            'events.*.type' => ['required', 'in:impression,click'],
        ]);

        $liveIds = Showcase::live()->pluck('id')->all();
        $live = array_flip($liveIds);
        $accepted = [];

        foreach ($validated['events'] as $event) {
            if (! isset($live[$event['showcase_id']])) {
                continue;
            }
            $accepted[] = [
                'showcase_id' => $event['showcase_id'],
                'type' => $event['type'],
                'at' => now()->toIso8601String(),
            ];
        }

        if ($accepted) {
            Log::info('showcase.track', ['events' => $accepted]);
        }

        return response()->json(['ok' => true, 'count' => count($accepted)]);
    }
}