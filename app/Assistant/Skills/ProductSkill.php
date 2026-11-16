<?php

namespace App\Assistant\Skills;

use App\Assistant\AssistantReply;
use App\Assistant\Cards;
use App\Assistant\Chips;
use App\Assistant\Nlp\Lexicon;
use App\Assistant\Nlp\QueryParser;
use App\Assistant\Nlp\SearchQuery;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** البحث الذكي، العروض، والمقارنة */
class ProductSkill
{
    private const LIMIT = 6;

    public function __construct(private readonly QueryParser $parser) {}

    public function search(string $message): AssistantReply
    {
        $q = $this->parser->parse($message);

        if (! $q->hasCriteria()) {
            return new AssistantReply(
                'شو المنتج اللي بتدور عليه؟ اكتبلي اسمه، وفيك تحدد السعر أو اللون أو المقاس. مثلاً: "موبايل تحت 200 ألف" أو "قميص أسود مقاس XL".',
                'product_search', chips: ['موبايل تحت 200 ألف', Chips::DEALS],
            );
        }

        $products = $this->find($q, matchAll: true);
        if ($products->isEmpty() && count($q->keywords) > 1) {
            $products = $this->find($q, matchAll: false);
        }

        $what = $this->describe($q);
        if ($products->isEmpty()) {
            $hint = ($q->color || $q->size || $q->minPrice || $q->maxPrice)
                ? ' جرّب بدون تحديد اللون أو المقاس أو السعر.'
                : ' جرّب كلمة ثانية أو اسم أدق.';

            return new AssistantReply("ما لقيت منتجات {$what} حالياً.{$hint}", 'product_search', chips: [Chips::DEALS, Chips::HUMAN]);
        }

        return new AssistantReply(
            "لقيتلك {$products->count()} ".($products->count() === 1 ? 'منتج' : 'منتجات')." {$what}:",
            'product_search',
            cards: $products->map(fn ($p) => Cards::product($p))->all(),
        );
    }

    public function deals(): AssistantReply
    {
        $products = $this->visible()
            ->where(fn ($w) => $w->whereColumn('compare_at_price', '>', 'price')->orWhereHas('activeDailyDeal'))
            ->orderByRaw('(COALESCE(compare_at_price, price) - price) / price DESC')
            ->limit(self::LIMIT)->get();

        if ($products->isEmpty()) {
            return new AssistantReply('ما في عروض شغّالة هلق. تابعنا، العروض بتتجدد باستمرار 🙂', 'deals', chips: [Chips::COUPONS]);
        }

        return new AssistantReply('هي أقوى العروض هلق 🔥', 'deals', cards: $products->map(fn ($p) => Cards::product($p))->all());
    }

    public function compare(string $message): AssistantReply
    {
        $found = collect($this->compareParts($message))
            ->map(fn ($part) => $this->find($this->parser->parse($part), matchAll: true, limit: 1)->first())
            ->filter()->unique('id')->values();

        if ($found->count() < 2) {
            return new AssistantReply(
                'اكتبلي اسم المنتجين اللي بدك تقارن بيناتهن، مثلاً: "قارن بين سامسونج A15 و ايفون 13".',
                'compare',
            );
        }

        $lines = $found->map(function (Product $p) {
            $rating = $p->average_rating ? "⭐ {$p->average_rating}" : 'بلا تقييمات بعد';
            $stock = $p->stock_quantity > 0 ? 'متوفر' : 'غير متوفر';

            return "• {$p->name}: ".number_format($p->effective_price / 100)." ل.س | {$rating} | {$stock}";
        })->implode("\n");

        $cheaper = $found->sortBy(fn ($p) => $p->effective_price)->first();

        return new AssistantReply(
            "المقارنة:\n{$lines}\n\nالأرخص: {$cheaper->name}.",
            'compare',
            cards: $found->map(fn ($p) => Cards::product($p))->all(),
        );
    }

    /** "قارن بين X و Y" => [X, Y] */
    private function compareParts(string $message): array
    {
        $text = preg_replace('/^.*?(قارن(لي)?|مقارن[ةه]|الفرق|compare)\s+(بين\s+)?/u', '', $message) ?? $message;
        $parts = preg_split('/\s+(?:و|ولا|مع|او|أو|vs|and)\s+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function visible(): Builder
    {
        return Product::publiclyVisible()->with(['primaryImage', 'activeDailyDeal']);
    }

    private function find(SearchQuery $q, bool $matchAll, int $limit = self::LIMIT): Collection
    {
        $query = $this->visible();

        if ($q->keywords !== []) {
            $query->where(function (Builder $outer) use ($q, $matchAll) {
                foreach ($q->keywords as $kw) {
                    $clause = function (Builder $w) use ($kw) {
                        foreach (Lexicon::alternatives($kw) as $alt) {
                            $w->orWhere('name', 'like', "%{$alt}%")
                                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$alt}%"));
                        }
                    };
                    $matchAll ? $outer->where($clause) : $outer->orWhere($clause);
                }
            });
        }
        if ($q->minPrice !== null) {
            $query->where('price', '>=', $q->minPrice);
        }
        if ($q->maxPrice !== null) {
            $query->where('price', '<=', $q->maxPrice);
        }
        if ($q->color !== null) {
            $forms = collect(Lexicon::COLORS[$q->color])->flatMap(fn ($f) => Lexicon::alternatives($f))->unique()->values();
            $query->where(function (Builder $w) use ($forms) {
                $w->whereHas('variants', function ($v) use ($forms) {
                    $v->where('is_active', true)->where(function ($vv) use ($forms) {
                        foreach ($forms as $f) {
                            $vv->orWhere('color_name', 'like', "%{$f}%");
                        }
                    });
                });
                foreach ($forms as $f) {
                    $w->orWhere('name', 'like', "%{$f}%");
                }
            });
        }
        if ($q->size !== null) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('size', $q->size)->where('stock_quantity', '>', 0));
        }

        $q->cheapestFirst ? $query->orderBy('price') : $query->orderByDesc('stock_quantity')->orderByDesc('id');

        return $query->limit($limit)->get();
    }

    private function describe(SearchQuery $q): string
    {
        $bits = [];
        if ($q->keywords !== []) {
            $bits[] = 'لـ "'.implode(' ', $q->keywords).'"';
        }
        if ($q->color) {
            $bits[] = "باللون {$q->color}";
        }
        if ($q->size) {
            $bits[] = "مقاس {$q->size}";
        }
        if ($q->minPrice !== null && $q->maxPrice !== null) {
            $bits[] = 'بين '.number_format($q->minPrice / 100).' و '.number_format($q->maxPrice / 100).' ل.س';
        } elseif ($q->maxPrice !== null) {
            $bits[] = 'تحت '.number_format($q->maxPrice / 100).' ل.س';
        } elseif ($q->minPrice !== null) {
            $bits[] = 'فوق '.number_format($q->minPrice / 100).' ل.س';
        }

        return implode(' ', $bits);
    }
}
