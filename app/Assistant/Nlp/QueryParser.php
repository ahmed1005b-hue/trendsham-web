<?php

namespace App\Assistant\Nlp;

/** يستخرج من جملة البحث: الكلمات، حدود السعر، اللون، المقاس، و"الأرخص". */
class QueryParser
{
    private const UNIT = '(الف|الاف|مليون|ملايين|k)';

    public function __construct(private readonly Normalizer $normalizer) {}

    public function parse(string $message): SearchQuery
    {
        $text = $this->normalizer->normalize($message);
        // "200الف" => "200 الف" ، "و3" => "و 3"
        $text = preg_replace(['/(\d)(\p{L})/u', '/(\p{L})(\d)/u'], '$1 $2', $text) ?? $text;

        $min = $max = null;
        $num = '(\d+(?:\s\d{3})*)';
        $unit = self::UNIT;

        if (preg_match("/بين\s{$num}\s?{$unit}?\s?و\s?{$num}\s?{$unit}?/u", $text, $m)) {
            $u2 = $m[4] ?? '';
            $max = $this->amount($m[3], $u2);
            if (($m[2] ?? '') !== '') {
                $min = $this->amount($m[1], $m[2]);
            } else {
                // "بين 50 و 100 الف": الوحدة مشتركة — إلا إذا قلبت المدى ("بين 500 و 2 مليون")
                $min = $this->amount($m[1], $u2);
                if ($min > $max) {
                    $min = $this->amount($m[1], '');
                }
            }
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }
            $text = str_replace($m[0], ' ', $text);
        }
        if ($max === null && preg_match("/(تحت|اقل من|ارخص من|اقل|حد اقصي|ما بيتعدي|لحد|بحدود|under|below|max)\s{$num}\s?{$unit}?/u", $text, $m)) {
            $max = $this->amount($m[2], $m[3] ?? '');
            $text = str_replace($m[0], ' ', $text);
        }
        if ($min === null && preg_match("/(فوق|اكثر من|اكتر من|اغلي من|above|over|min)\s{$num}\s?{$unit}?/u", $text, $m)) {
            $min = $this->amount($m[2], $m[3] ?? '');
            $text = str_replace($m[0], ' ', $text);
        }

        $size = null;
        if (preg_match('/(?:مقاس|نمره|قياس|size)\s?([a-z0-9]{1,4})\b/u', $text, $m)) {
            $size = strtoupper($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        $tokens = $this->normalizer->tokens(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
        $cheapest = (bool) array_intersect($tokens, ['ارخص', 'الارخص', 'رخيص', 'رخيصه', 'cheapest', 'cheap']);

        $color = null;
        $keywords = [];
        foreach ($tokens as $t) {
            if ($size === null && preg_match('/^(xs|xl|xxl|xxxl|2xl|3xl|4xl)$/', $t)) {
                $size = strtoupper($t);

                continue;
            }
            if ($color === null && ($c = Lexicon::color($t) ?? Lexicon::color(Normalizer::stem($t)))) {
                $color = $c;

                continue;
            }
            if (ctype_digit($t) || in_array($t, Lexicon::STOPWORDS, true) || preg_match('/^'.self::UNIT.'$/u', $t)) {
                continue;
            }
            $stem = Normalizer::stem($t);
            if (mb_strlen($stem) >= 2 && ! in_array($stem, $keywords, true)) {
                $keywords[] = $stem;
            }
        }

        return new SearchQuery(array_slice($keywords, 0, 4), $min, $max, $color, $size, $cheapest);
    }

    /** المبلغ بالليرة × الوحدة، ثم ×100 للتخزين */
    private function amount(string $number, string $unit): int
    {
        // سقف معقول يمنع تجاوز حدود الأعداد الصحيحة مع أرقام عبثية
        $digits = str_replace(' ', '', $number);
        $n = strlen($digits) > 10 ? 9999999999 : (int) $digits;
        $mult = match ($unit) {
            'الف', 'الاف', 'k' => 1000,
            'مليون', 'ملايين' => 1000000,
            default => 1,
        };

        return $n * $mult * 100;
    }
}
