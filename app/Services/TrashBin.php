<?php

namespace App\Services;

use App\Assistant\Models\AssistantFaq;
use App\Coupons\Models\CouponCampaign;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DailyDeal;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ShippingCompany;
use App\Models\ShowcaseSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * المحذوفات: كل ما حُذف حذفاً مؤقتاً، مع الاسترجاع.
 * مشتركة بين صفحة الموقع وشاشة التطبيق.
 */
class TrashBin
{
    /** @var array<string, array{0: class-string<Model>, 1: string}> */
    public const TYPES = [
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

    /** @return Collection<string, int> عدد المحذوفات لكل نوع */
    public function counts(): Collection
    {
        return collect(self::TYPES)->map(fn (array $type) => $type[0]::onlyTrashed()->count());
    }

    /** النوع المطلوب، أو أول نوع فيه محذوفات. */
    public function current(?string $requested, Collection $counts): string
    {
        return array_key_exists((string) $requested, self::TYPES)
            ? (string) $requested
            : ($counts->filter()->keys()->first() ?? 'products');
    }

    /** @return Collection<string, string> */
    public function labels(): Collection
    {
        return collect(self::TYPES)->map(fn (array $type) => $type[1]);
    }

    public function page(string $type, int $perPage = 30): LengthAwarePaginator
    {
        return $this->trashedQuery($type)->latest('deleted_at')->paginate($perPage)
            ->through(fn (Model $item) => [
                'id' => $item->getKey(),
                'title' => $this->title($type, $item),
                'detail' => $this->detail($type, $item),
                'deleted_at' => $item->deleted_at,
            ]);
    }

    /**
     * يسترجع عنصراً محذوفاً.
     *
     * @throws \DomainException عنصر لا يعود قبل غيره (مثل عرض منتج محذوف)
     */
    public function restore(string $type, int $id): void
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $item = self::TYPES[$type][0]::onlyTrashed()->findOrFail($id);

        if ($blocker = $this->restoreBlocker($type, $item)) {
            throw new \DomainException($blocker);
        }

        DB::transaction(function () use ($type, $item) {
            if ($type === 'categories' && $item->parent_id) {
                Category::onlyTrashed()->whereKey($item->parent_id)->first()?->restore();
            }
            $item->restore();
        });
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
            return __('المنتج نفسه محذوف. استرجع المنتج أولاً.');
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
