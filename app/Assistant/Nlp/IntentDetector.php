<?php

namespace App\Assistant\Nlp;

/**
 * يحدد ما يريده المستخدم من رسالته. الترتيب مهم: النوايا المحددة أولاً
 * (موظف، إلغاء، مقارنة...) ثم البحث، ثم التحية والشكر.
 */
class IntentDetector
{
    public const CONFIRM = ['نعم', 'اي', 'ايه', 'ايوه', 'ايوا', 'اكيد', 'موافق', 'اكد', 'تاكيد', 'تمام', 'اوك', 'ok', 'yes', 'يب'];

    public const DENY = ['لا', 'لاء', 'كلا', 'no', 'مابدي', 'ما بدي', 'لا شكرا', 'تراجع', 'خلص لا'];

    /** @var array<string, list<string>> */
    private const KEYWORDS = [
        'handoff' => ['موظف', 'موظفه', 'انسان', 'بشري', 'خدمه العملاء', 'الدعم', 'دعم فني', 'احكي مع حدا', 'كلم حدا', 'شخص حقيقي', 'agent', 'human', 'support'],
        'cancel_order' => ['الغي', 'الغاء', 'الغيلي', 'لغي', 'بلغي', 'الغيه', 'cancel'],
        'compare' => ['قارن', 'قارنلي', 'مقارنه', 'الفرق بين', 'شو الفرق', 'ايهما افضل', 'مين احسن', 'compare'],
        'coupons' => ['كوبون', 'كوبونات', 'كوبوناتي', 'كوبوني', 'قسيمه', 'قسايم', 'كود خصم', 'كود الخصم', 'coupon', 'coupons', 'voucher'],
        'order_status' => ['طلب', 'طلبي', 'طلباتي', 'طلبيتي', 'طلبيه', 'اوردر', 'اوردري', 'order', 'orders', 'تتبع', 'شحنتي', 'الشحنه', 'وين وصل', 'حاله الطلب', 'رقم التتبع'],
        'deals' => ['عروض', 'عرض', 'العروض', 'تخفيضات', 'تنزيلات', 'خصومات', 'اوكازيون', 'deals', 'offers', 'sale'],
        'help' => ['شو بتعمل', 'شو بتقدر', 'بتقدر تعمل', 'شو فيك تعمل', 'كيف بتساعد', 'مساعده', 'ساعدني', 'help'],
        'product_search' => ['ابحث', 'ابحثلي', 'دور', 'بدور', 'ادور', 'دورلي', 'عندكم', 'عندكن', 'سعر', 'بكم', 'قديش', 'كم سعر', 'متوفر', 'موجود', 'اشتري', 'بدي', 'اريد', 'ابي', 'حابب', 'محتاج', 'وريني', 'search', 'find'],
        'greeting' => ['مرحبا', 'مرحبه', 'هلا', 'اهلا', 'اهلين', 'السلام', 'سلام', 'صباح الخير', 'مسا الخير', 'مساء الخير', 'hi', 'hello', 'hey', 'هاي'],
        'thanks' => ['شكرا', 'يسلمو', 'يسلموا', 'مشكور', 'مشكوره', 'تسلم', 'تسلمي', 'thanks', 'thank you', 'يعطيك العافيه'],
    ];

    /** هذه تُطابق بخطأ إملائي بسيط؛ الباقي (أفعال قصيرة شائعة) مطابقة تامة فقط */
    private const FUZZY = ['handoff', 'coupons', 'deals', 'compare'];

    public function __construct(private readonly Normalizer $normalizer, private readonly ?QueryParser $parser = null) {}

    public function detect(string $message): string
    {
        $text = $this->normalizer->normalize($message);
        $tokens = $this->normalizer->tokens($text);

        if ($tokens === []) {
            return 'unknown';
        }
        if (count($tokens) <= 3 && in_array($text, self::DENY, true)) {
            return 'deny';
        }
        if (count($tokens) <= 3 && array_diff($tokens, self::CONFIRM) === []) {
            return 'confirm';
        }

        foreach (['handoff', 'cancel_order', 'compare', 'coupons'] as $intent) {
            if ($this->hit($text, $intent)) {
                return $intent;
            }
        }
        if (preg_match('/\bts[\s-]?[a-z0-9-]{6,}\b/i', $message) || $this->hit($text, 'order_status')) {
            return 'order_status';
        }
        foreach (['deals', 'help'] as $intent) {
            if ($this->hit($text, $intent)) {
                return $intent;
            }
        }

        if ($this->hit($text, 'product_search') || $this->looksLikeProduct($message, $tokens)) {
            return 'product_search';
        }
        foreach (['greeting', 'thanks'] as $intent) {
            if ($this->hit($text, $intent)) {
                return $intent;
            }
        }

        return 'unknown';
    }

    private function hit(string $text, string $intent): bool
    {
        return $this->normalizer->matches($text, self::KEYWORDS[$intent], in_array($intent, self::FUZZY, true));
    }

    /** بلا فعل صريح: "سماعات بلوتوث" أو "شي احمر تحت 100 الف" */
    private function looksLikeProduct(string $message, array $tokens): bool
    {
        foreach ($tokens as $t) {
            if (Lexicon::knowsProduct($t)) {
                return true;
            }
        }
        $q = ($this->parser ?? new QueryParser($this->normalizer))->parse($message);

        return $q->minPrice !== null || $q->maxPrice !== null || $q->color !== null || $q->size !== null;
    }
}
