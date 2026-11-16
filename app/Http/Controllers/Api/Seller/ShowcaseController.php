<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Services\ShowcaseService;
use App\Showcases\ShowcasePresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowcaseController extends Controller
{
    public function __construct(private ShowcaseService $svc) {}

    /** GET /api/v1/seller/showcase-slots?start_date=&end_date= */
    public function slots(Request $r)
    {
        $r->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date']);

        return ShowcaseSlot::where('is_active', true)->orderBy('position')->get()->map(function ($s) use ($r) {
            $row = [
                'id' => $s->id, 'key' => $s->key, 'name' => $s->name,
                'price_per_day' => (float) $s->price_per_day,
                'min_days' => $s->min_days, 'max_days' => $s->max_days,
                'min_products' => $s->min_products, 'max_products' => $s->max_products,
            ];

            if ($r->filled('start_date') && $r->filled('end_date')) {
                $row['available'] = $this->svc->isAvailable($s, $r->start_date, $r->end_date);
                $days = (int) Carbon::parse($r->start_date)->diffInDays(Carbon::parse($r->end_date), true) + 1;
                $row['days'] = $days;
                $row['total'] = round($days * (float) $s->price_per_day, 2);
            }

            return $row;
        })->values();
    }

    /** GET /api/v1/seller/showcases */
    public function index(Request $r)
    {
        return Showcase::with('slot')->where('seller_id', $r->user()->sellerProfile->id)->latest()->get()
            ->map(fn ($s) => $this->summary($s));
    }

    /** GET /api/v1/seller/showcases/{showcase} */
    public function show(Request $r, Showcase $showcase)
    {
        Gate::authorize('manage', $showcase);
        $showcase->load(['slot', 'seller', 'products']);

        return $this->summary($showcase) + ['preview' => ShowcasePresenter::card($showcase)];
    }

    /** POST /api/v1/seller/showcases */
    public function store(Request $r)
    {
        $data = $r->validate([
            'slot_id' => 'required|integer|exists:showcase_slots,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'title' => 'required|string|max:60',
            'subtitle' => 'nullable|string|max:100',
            'gradient_colors' => 'required|array|min:2|max:3',
            'gradient_colors.*' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'gradient_direction' => 'required|in:vertical,horizontal,diagonal',
            'text_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:min_width=600,min_height=200',   // لون الخط يختاره البائع
            'product_ids' => 'required|array|min:5|max:10',
            'product_ids.*' => 'integer',
        ]);

        $s = $this->svc->create($r->user(), $data);

        return response()->json($this->summary($s->load('slot')), 201);
    }

    /** POST /api/v1/seller/showcases/{showcase}/cancel */
    public function cancel(Request $r, Showcase $showcase)
    {
        $this->svc->cancelBySeller($showcase, $r->user());

        return response()->json(['message' => 'تم إلغاء الحجز واسترجاع المبلغ إلى محفظتك.']);
    }

    private function summary(Showcase $s): array
    {
        $ctr = $s->impressions > 0 ? round($s->clicks / $s->impressions * 100, 2) : 0;

        return [
            'id' => $s->id, 'slot' => $s->slot->name, 'title' => $s->title, 'status' => $s->status,
            'start_date' => $s->start_date->toDateString(), 'end_date' => $s->end_date->toDateString(),
            'amount' => (float) $s->amount, 'rejection_reason' => $s->rejection_reason,
            'impressions' => $s->impressions, 'clicks' => $s->clicks, 'ctr' => $ctr,
        ];
    }
}
