<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\TrashBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** المحذوفات في التطبيق (نفس صفحة الموقع): القوائم حسب النوع والاسترجاع. */
class TrashController extends Controller
{
    public function __construct(private readonly TrashBin $trash) {}

    public function index(Request $request): JsonResponse
    {
        $counts = $this->trash->counts();
        $current = $this->trash->current($request->query('type'), $counts);
        $page = $this->trash->page($current);

        return response()->json([
            'current' => $current,
            'types' => $this->trash->labels()->map(fn (string $label, string $key) => [
                'value' => $key, 'label' => __($label), 'count' => $counts[$key],
            ])->values(),
            'data' => collect($page->items())->map(fn (array $item) => [
                ...$item,
                'deleted_at' => $item['deleted_at']?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function restore(string $type, int $id): JsonResponse
    {
        try {
            $this->trash->restore($type, $id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('تم الاسترجاع.')]);
    }
}
