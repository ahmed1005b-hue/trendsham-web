<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\DailyDealController as WebDailyDealController;
use App\Models\DailyDeal;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * إدارة عروض اليوم من التطبيق. التحقق والحفظ موروثان من متحكم الويب.
 */
class DailyDealController extends WebDailyDealController
{
    public function index()
    {
        $deals = DailyDeal::with('product')->latest()->paginate(30);

        return response()->json([
            'data' => collect($deals->items())->map(fn ($d) => $this->present($d)),
            'current_page' => $deals->currentPage(),
            'last_page' => $deals->lastPage(),
        ]);
    }

    /** المنتجات المعتمدة التي يمكن اختيارها لعرض جديد. */
    public function create()
    {
        return response()->json([
            'data' => Product::where('status', 'active')->whereNotNull('admin_approved_at')
                ->orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price / 100]),
        ]);
    }

    public function store(Request $request)
    {
        parent::store($request);

        return response()->json(['data' => $this->present(DailyDeal::with('product')->latest('id')->first())], 201);
    }

    public function update(Request $request, DailyDeal $deal)
    {
        parent::update($request, $deal);

        return response()->json(['data' => $this->present($deal->fresh('product'))]);
    }

    public function destroy(DailyDeal $deal)
    {
        parent::destroy($deal);

        return response()->json(['message' => 'تم حذف منتج اليوم.']);
    }

    public function toggleStatus(DailyDeal $deal)
    {
        parent::toggleStatus($deal);

        return response()->json(['data' => $this->present($deal->fresh('product'))]);
    }

    private function present(DailyDeal $d): array
    {
        return [
            'id' => $d->id,
            'product_id' => $d->product_id,
            'product_name' => $d->product?->name,
            'discount_percentage' => (float) $d->discount_percentage,
            'special_price' => $d->special_price !== null ? (float) $d->special_price : null,
            'starts_at' => $d->starts_at?->toISOString(),
            'ends_at' => $d->ends_at?->toISOString(),
            'max_quantity' => $d->max_quantity,
            'sold_quantity' => $d->sold_quantity,
            'is_active' => (bool) $d->is_active,
        ];
    }
}
