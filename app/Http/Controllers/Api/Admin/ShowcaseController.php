<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Services\ShowcaseService;
use App\Showcases\ShowcasePresenter;
use Illuminate\Http\Request;

/**
 * مراجعة مساحات العرض المؤجّرة (موافقة/رفض/إزالة) وإدارة المساحات نفسها من التطبيق.
 * العمليات المالية (الاسترجاع للمحفظة) تتم داخل ShowcaseService كما في الويب.
 */
class ShowcaseController extends Controller
{
    public function __construct(private ShowcaseService $svc) {}

    public function index(Request $r)
    {
        $showcases = Showcase::with(['slot', 'seller.user', 'products'])
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->status))
            ->latest()->paginate(20);

        return response()->json([
            'data' => collect($showcases->items())->map(fn ($s) => $this->present($s)),
            'current_page' => $showcases->currentPage(),
            'last_page' => $showcases->lastPage(),
        ]);
    }

    public function approve(Request $r, Showcase $showcase)
    {
        $this->svc->approve($showcase, $r->user()->id);

        return response()->json(['data' => $this->present($showcase->fresh(['slot', 'seller.user', 'products']))]);
    }

    public function reject(Request $r, Showcase $showcase)
    {
        $data = $r->validate(['reason' => 'required|string|max:200']);
        $this->svc->reject($showcase, $r->user()->id, $data['reason']);

        return response()->json(['data' => $this->present($showcase->fresh(['slot', 'seller.user', 'products']))]);
    }

    public function remove(Request $r, Showcase $showcase)
    {
        $data = $r->validate(['reason' => 'required|string|max:200']);
        $this->svc->removeByAdmin($showcase, $r->user()->id, $data['reason']);

        return response()->json(['data' => $this->present($showcase->fresh(['slot', 'seller.user', 'products']))]);
    }

    public function slots()
    {
        return response()->json(['data' => ShowcaseSlot::orderBy('position')->get()->map(fn ($s) => $this->slot($s))]);
    }

    public function storeSlot(Request $r)
    {
        $data = $this->validateSlot($r);

        return response()->json(['data' => $this->slot(ShowcaseSlot::create($data))], 201);
    }

    public function updateSlot(Request $r, ShowcaseSlot $showcaseSlot)
    {
        $showcaseSlot->update($this->validateSlot($r, $showcaseSlot));

        return response()->json(['data' => $this->slot($showcaseSlot)]);
    }

    public function destroySlot(ShowcaseSlot $showcaseSlot)
    {
        // لا نسمح بالحذف إذا كانت هناك حجوزات - نعطل فقط
        abort_if($showcaseSlot->showcases()->exists(), 409,
            'لا يمكن حذف هذه المساحة لوجود حجوزات مرتبطة بها. يمكنك تعطيلها فقط.');
        $showcaseSlot->delete();

        return response()->json(['message' => 'تم حذف المساحة.']);
    }

    private function validateSlot(Request $r, ?ShowcaseSlot $slot = null): array
    {
        $data = $r->validate([
            'key' => 'required|string|max:40|unique:showcase_slots,key'.($slot ? ','.$slot->id : ''),
            'name' => 'required|string|max:100',
            'position' => 'required|integer|min:0',
            'price_per_day' => 'required|numeric|min:0',
            'min_days' => 'required|integer|min:1',
            'max_days' => 'required|integer|min:1|gte:min_days',
            'capacity' => 'required|integer|min:1',
            'min_products' => 'required|integer|min:1',
            'max_products' => 'required|integer|min:1|gte:min_products',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $r->boolean('is_active');

        return $data;
    }

    private function present(Showcase $s): array
    {
        return [
            'id' => $s->id,
            'status' => $s->status,
            'slot' => $s->slot?->name,
            'store_name' => $s->seller?->store_name,
            'title' => $s->title,
            'start_date' => $s->start_date?->toDateString(),
            'end_date' => $s->end_date?->toDateString(),
            'amount' => (float) $s->amount,
            'rejection_reason' => $s->rejection_reason,
            'impressions' => (int) $s->impressions,
            'clicks' => (int) $s->clicks,
            'products_count' => $s->products->count(),
            'preview' => $s->slot && $s->seller ? ShowcasePresenter::card($s) : null,
        ];
    }

    private function slot(ShowcaseSlot $s): array
    {
        return [
            'id' => $s->id,
            'key' => $s->key,
            'name' => $s->name,
            'position' => $s->position,
            'price_per_day' => (float) $s->price_per_day,
            'min_days' => $s->min_days,
            'max_days' => $s->max_days,
            'capacity' => $s->capacity,
            'min_products' => $s->min_products,
            'max_products' => $s->max_products,
            'is_active' => (bool) $s->is_active,
        ];
    }
}
