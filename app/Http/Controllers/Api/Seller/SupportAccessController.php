<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\StaffActivity;
use App\Services\SellerSupportAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** التطبيق: إذن البائع لفريق الدعم بفتح لوحة متجره، وما فعله الفريق فيها. */
class SupportAccessController extends Controller
{
    public function __construct(private SellerSupportAccess $access) {}

    /** GET /api/v1/seller/support-access */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($request->user()->sellerProfile)]);
    }

    /** POST /api/v1/seller/support-access */
    public function store(Request $request): JsonResponse
    {
        $store = $request->user()->sellerProfile;
        $this->access->grant($store, $request->user());

        return response()->json([
            'message' => __('سمحت لفريق الدعم بفتح لوحة متجرك. يبقى الإذن حتى تُنهيه.'),
            'data' => $this->state($store),
        ]);
    }

    /** DELETE /api/v1/seller/support-access */
    public function destroy(Request $request): JsonResponse
    {
        $store = $request->user()->sellerProfile;
        $this->access->revoke($store, $request->user());

        return response()->json([
            'message' => __('أنهيت الإذن. لم يعد فريق الدعم يستطيع فتح لوحة متجرك.'),
            'data' => $this->state($store),
        ]);
    }

    /**
     * @return array{active: bool, granted_at: string|null, actions: list<array{description: string, at: string|null}>}
     */
    private function state(SellerProfile $store): array
    {
        $grant = $this->access->activeGrant($store);

        return [
            'active' => $grant !== null,
            'granted_at' => $grant?->created_at?->toISOString(),
            'actions' => $this->access->recentActions($store)
                ->map(fn (StaffActivity $activity) => [
                    'description' => $this->access->describeForSeller($activity),
                    'at' => $activity->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
