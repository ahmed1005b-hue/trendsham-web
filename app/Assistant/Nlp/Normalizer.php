<?php

namespace App\Assistant\Nlp;

/**
 * توحيد النص العربي قبل الفهم: الهمزات والتاء المربوطة والألف المقصورة،
 * التشكيل والتطويل، الأرقام الهندية، وعلامات الترقيم.
 */
class Normalizer
{
    /** "ال" وما يلتصق بها — الأطول أولاً */
    private const PREFIXES = ['وبال', 'وال', 'بال', 'فال', 'كال', 'لل', 'ال'];

    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '،' => ' ', '؟' => ' ', '؛' => ' ',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @return list<string> */
    public function tokens(string $normalized): array
    {
        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /** الكلمة بدون "ال" الملتصقة، إن بقي منها ما يكفي */
    public static function stem(string $token): string
    {
        foreach (self::PREFIXES as $p) {
            if (str_starts_with($token, $p) && mb_strlen($token) - mb_strlen($p) >= 3) {
                return mb_substr($token, mb_strlen($p));
            }
        }

        return $token;
    }

    /** تسامح مع خطأ إملائي واحد (حرفين للكلمات الطويلة) */
    public static function similar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $len = min(mb_strlen($a), mb_strlen($b));
        if ($len < 5) {
            return false;
        }
        $max = $len >= 7 ? 2 : 1;

        return abs(mb_strlen($a) - mb_strlen($b)) <= $max && self::distance($a, $b) <= $max;
    }

    /** مسافة ليفنشتاين على مستوى الحروف (لا البايتات) */
    public static function distance(string $a, string $b): int
    {
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $prev = range(0, count($y));
        foreach ($x as $i => $cx) {
            $cur = [$i + 1];
            foreach ($y as $j => $cy) {
                $cur[$j + 1] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($cx === $cy ? 0 : 1));
            }
            $prev = $cur;
        }

        return $prev[count($y)];
    }

    /**
     * هل يحتوي النص على إحدى الكلمات؟ العبارات (بمسافة) تُطابق كما هي،
     * والكلمة المفردة تُطابق الكلمة أو جذعها أو بخطأ إملائي بسيط.
     *
     * @param  list<string>  $keywords  مكتوبة بالشكل الموحَّد
     */
    public function matches(string $normalized, array $keywords, bool $fuzzy = true): bool
    {
        $tokens = $this->tokens($normalized);
        $padded = " {$normalized} ";

        foreach ($keywords as $kw) {
            if (str_contains($kw, ' ')) {
                if (str_contains($padded, " {$kw} ")) {
                    return true;
                }

                continue;
            }
            foreach ($tokens as $t) {
                $stem = self::stem($t);
                // واو العطف أو باء الجر الملتصقة: "وطلبي"، "بموظف" (مطابقة تامة فقط)
                $bare = in_array(mb_substr($t, 0, 1), ['و', 'ب'], true) && mb_strlen($t) > 3 ? mb_substr($t, 1) : null;
                if ($t === $kw || $stem === $kw || $bare === $kw || ($fuzzy && self::similar($stem, $kw))) {
                    return true;
                }
            }
        }

        return false;
    }
}
