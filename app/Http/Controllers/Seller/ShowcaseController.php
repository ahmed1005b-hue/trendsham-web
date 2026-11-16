<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Services\ShowcaseService;
use App\Showcases\ShowcasePresenter;
use App\Support\ColorContrast;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShowcaseController extends Controller
{
    public function __construct(private ShowcaseService $svc) {}

    /** قائمة حجوزات البائع */
    public function index(Request $r)
    {
        $showcases = Showcase::with('slot')
            ->where('seller_id', $r->user()->sellerProfile->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.showcases.index', compact('showcases'));
    }

    /** نموذج الحجز الجديد */
    public function create(Request $r)
    {
        $slots = ShowcaseSlot::where('is_active', true)->orderBy('position')->get();
        $products = Product::where('vendor_id', $r->user()->sellerProfile->id)
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->latest()
            ->get(['id', 'name', 'price', 'compare_at_price', 'stock_quantity'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price / 100,
                    'compare_at_price' => $p->compare_at_price ? $p->compare_at_price / 100 : null,
                    'stock' => $p->stock_quantity,
                ];
            });

        $presets = [
            'غروب' => ['#F46B45', '#EEA849'],
            'محيط' => ['#2193B0', '#6DD5ED'],
            'غابة' => ['#0F3D2E', '#2E7D5B'],
            'ذهب أخضر' => ['#0F3D2E', '#9A7425'],   // الذهبي الفاتح كان يجعل النص غير مقروء
            'ليل' => ['#141E30', '#243B55'],
            'وردي' => ['#B24592', '#F15F79'],
            'بنفسجي' => ['#4A00E0', '#8E2DE2'],
            'نعناع' => ['#00B09B', '#96C93D'],
            'عنابي' => ['#780206', '#061161'],
            'رمل' => ['#C79081', '#DFA579'],
            'ثلاثي دافئ' => ['#FF512F', '#F09819', '#FFD200'],
            'ثلاثي بارد' => ['#1A2980', '#1F6F8B', '#2A8C7C'],
        ];

        return view('seller.showcases.create', compact('slots', 'products', 'presets'));
    }

    /** إنشاء الحجز */
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
            // The selected slot applies its configured min/max product limits in ShowcaseService.
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer',
        ]);

        try {
            $showcase = $this->svc->create($r->user(), $data);

            return redirect()->route('seller.showcases.index')
                ->with('success', 'تم حجز المساحة بنجاح! العرض قيد المراجعة وسيظهر بعد موافقة الإدارة.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /** عرض تفاصيل الحجز + معاينة */
    public function show(Request $r, Showcase $showcase)
    {
        Gate::authorize('manage', $showcase);

        $showcase->load(['slot', 'products']);
        $preview = ShowcasePresenter::card($showcase);
        $ctr = $showcase->impressions > 0 ? round($showcase->clicks / $showcase->impressions * 100, 2) : 0;

        return view('seller.showcases.show', compact('showcase', 'preview', 'ctr'));
    }

    /** إلغاء الحجز */
    public function cancel(Request $r, Showcase $showcase)
    {
        Gate::authorize('manage', $showcase);

        try {
            $this->svc->cancelBySeller($showcase, $r->user());

            return back()->with('success', 'تم إلغاء الحجز واسترجاع المبلغ إلى محفظتك.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /** API: جلب المساحات مع توفر وتكلفة للفترة المختارة (AJAX) */
    public function slotsAvailability(Request $r)
    {
        $r->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);

        $slots = ShowcaseSlot::where('is_active', true)->orderBy('position')->get()->map(function ($s) use ($r) {
            $available = $this->svc->isAvailable($s, $r->start_date, $r->end_date);
            $days = (int) Carbon::parse($r->start_date)->diffInDays(Carbon::parse($r->end_date), true) + 1;
            $total = round($days * (float) $s->price_per_day, 2);

            return [
                'id' => $s->id,
                'key' => $s->key,
                'name' => $s->name,
                'price_per_day' => (float) $s->price_per_day,
                'min_days' => $s->min_days,
                'max_days' => $s->max_days,
                'min_products' => $s->min_products,
                'max_products' => $s->max_products,
                'available' => $available,
                'days' => $days,
                'total' => $total,
                'not_available_reason' => $available ? null : ($s->is_active ? 'محجوزة بالكامل في هذه التواريخ' : 'مساحة معطلة'),
            ];
        });

        return response()->json($slots->values());
    }

    /** API: معاينة حية للألوان */
    public function previewColors(Request $r)
    {
        $r->validate([
            'gradient_colors' => 'required|array|min:2|max:3',
            'gradient_colors.*' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'gradient_direction' => 'required|in:vertical,horizontal,diagonal',
        ]);

        $result = ColorContrast::pick($r->gradient_colors);
        $isLight = $result[0] === 'light'; // pick() يعيد 'light' أو 'dark' (نص، وليس قيمة منطقية)
        $ratio = $result[1];

        return response()->json([
            'text_color' => $isLight ? 'light' : 'dark',
            'contrast_ratio' => round($ratio, 2),
            'valid' => $ratio >= 3.0,
            'message' => $ratio >= 3.0 ? 'الألوان مقروءة' : 'تباين ضعيف - النص قد لا يكون مقروءاً على بعض الألوان',
        ]);
    }
}
