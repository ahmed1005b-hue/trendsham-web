<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Models\AssistantFaq;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DailyDeal;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ShippingCompany;
use App\Models\ShowcaseSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** المحذوفات: كل ما حُذف حذفاً مؤقتاً، مع زر استرجاع. */
class TrashController extends Controller
{
    /** @var array<string, array{0: class-string<Model>, 1: string}> */
    private const TYPES = [
        'products' => [Product::class, 'المنتجات'],
        'categories' => [Category::class, 'الأقسام'],
        'coupon_campaigns' => [CouponCampaign::class, 'الكوبونات'],
        'coupons' => [Coupon::class, 'كوبونات قديمة'],
        'daily_deals' => [DailyDeal::class, 'عروض اليوم'],
        'shipping_companies' => [ShippingCompany::class, 'شركات الشحن'],
        'showcase_slots' => [ShowcaseSlot::class, 'مساحات العرض'],
        'product_questions' => [ProductQuestion::class, 'أسئلة المنتجات'],
        'assistant_faqs' => [AssistantFaq::class, 'أجوبة المساعد'],
    ];

    public function index(Request $request): View
    {
        $counts = collect(self::TYPES)->map(fn (array $type) => $type[0]::onlyTrashed()->count());
        $current = array_key_exists((string) $request->query('type'), self::TYPES)
            ? (string) $request->query('type')
            : ($counts->filter()->keys()->first() ?? 'products');

        $items = $this->trashedQuery($current)->latest('deleted_at')->paginate(30)->withQueryString();

        return view('admin.trash.index', [
            'types' => collect(self::TYPES)->map(fn (array $type) => $type[1]),
            'counts' => $counts,
            'current' => $current,
            'items' => $items->through(fn (Model $item) => [
                'id' => $item->getKey(),
                'title' => $this->title($current, $item),
                'detail' => $this->detail($current, $item),
                'deleted_at' => $item->deleted_at,
            ]),
        ]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $item = self::TYPES[$type][0]::onlyTrashed()->findOrFail($id);

        if ($blocker = $this->restoreBlocker($type, $item)) {
            return back()->with('error', $blocker);
        }

        DB::transaction(function () use ($type, $item) {
            if ($type === 'categories' && $item->parent_id) {
                Category::onlyTrashed()->whereKey($item->parent_id)->first()?->restore();
            }
            $item->restore();
        });

        return back()->with('success', 'تم الاسترجاع.');
    }

    private function trashedQuery(string $type)
    {
        $query = self::TYPES[$type][0]::onlyTrashed();

        return match ($type) {
            'products' => $query->with('vendor:id,store_name'),
            'daily_deals', 'product_questions' => $query->with(['product' => fn ($q) => $q->withTrashed()->select('id', 'name')]),
            'coupon_campaigns' => $query->with('seller:id,store_name'),
            default => $query,
        };
    }

    /** عرض اليوم وسؤال المنتج لا يعودان قبل منتجهما. */
    private function restoreBlocker(string $type, Model $item): ?string
    {
        if (in_array($type, ['daily_deals', 'product_questions'], true)
            && Product::onlyTrashed()->whereKey($item->product_id)->exists()) {
            return 'المنتج نفسه محذوف. استرجع المنتج أولاً.';
        }

        return null;
    }

    private function title(string $type, Model $item): string
    {
        return (string) match ($type) {
            'products', 'categories', 'shipping_companies', 'showcase_slots' => $item->name,
            'coupon_campaigns' => $item->title,
            'coupons' => preg_replace('/~del\d+$/', '', (string) $item->code),
            'daily_deals' => $item->product?->name,
            'product_questions', 'assistant_faqs' => $item->question,
        };
    }

    private function detail(string $type, Model $item): ?string
    {
        return match ($type) {
            'products' => $item->vendor?->store_name,
            'coupon_campaigns' => $item->seller?->store_name ?? __('المنصة'),
            'product_questions' => $item->product?->name,
            default => null,
        };
    }
}
