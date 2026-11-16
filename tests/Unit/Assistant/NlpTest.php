<?php

namespace Tests\Unit\Assistant;

use App\Assistant\Nlp\IntentDetector;
use App\Assistant\Nlp\Lexicon;
use App\Assistant\Nlp\Normalizer;
use App\Assistant\Nlp\QueryParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NlpTest extends TestCase
{
    public function test_normalizer_unifies_letters_digits_and_diacritics(): void
    {
        $n = new Normalizer;
        $this->assertSame('اريد سماعه حمراء 200', $n->normalize('أُريدُ  سمّاعة حمراء ٢٠٠!'));
        $this->assertSame('مستشفي', $n->normalize('مستشفى'));
        $this->assertSame('كتاب', $n->normalize('كتـــاب'));
    }

    public function test_similar_tolerates_one_typo_on_longer_words(): void
    {
        $this->assertTrue(Normalizer::similar('كوبونات', 'كوبنات'));
        $this->assertTrue(Normalizer::similar('موبايل', 'موبيل'));
        $this->assertFalse(Normalizer::similar('طلب', 'صلب'));   // كلمات قصيرة: لا تسامح
        $this->assertFalse(Normalizer::similar('موبايل', 'لابتوب'));
    }

    #[DataProvider('intents')]
    public function test_intent_detection(string $message, string $expected): void
    {
        $this->assertSame($expected, (new IntentDetector(new Normalizer))->detect($message));
    }

    public static function intents(): array
    {
        return [
            ['مرحبا', 'greeting'],
            ['السلام عليكم', 'greeting'],
            ['يسلمو كتير', 'thanks'],
            ['وين طلبي؟', 'order_status'],
            ['بدي اعرف حالة الطلب TS-20261111-ABCD', 'order_status'],
            ['بدي الغي طلبي', 'cancel_order'],
            ['شو عندي كوبونات', 'coupons'],
            ['كوبنات', 'coupons'],
            ['قارن بين ايفون و سامسونج', 'compare'],
            ['شو في عروض اليوم', 'deals'],
            ['بدي احكي مع موظف', 'handoff'],
            ['خدمة العملاء لو سمحت', 'handoff'],
            ['بدي موبايل تحت 200 الف', 'product_search'],
            ['ابحث عن سماعات', 'product_search'],
            ['قديش سعر الساعة', 'product_search'],
            ['نعم', 'confirm'],
            ['لا', 'deny'],
            ['شو بتقدر تعمل', 'help'],
            ['zzqx', 'unknown'],
        ];
    }

    public function test_parser_reads_max_price_in_thousands(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('بدي موبايل تحت 200 ألف');
        $this->assertSame(200000 * 100, $q->maxPrice);
        $this->assertNull($q->minPrice);
        $this->assertSame(['موبايل'], $q->keywords);
    }

    public function test_parser_reads_attached_thousands_and_arabic_digits(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('سماعات اقل من ١٥٠الف');
        $this->assertSame(150000 * 100, $q->maxPrice);
        $this->assertSame(['سماعات'], $q->keywords);
    }

    public function test_parser_reads_range_and_million(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('لابتوب بين 2 و 3 مليون');
        $this->assertSame(2000000 * 100, $q->minPrice);
        $this->assertSame(3000000 * 100, $q->maxPrice);
        $this->assertSame(['لابتوب'], $q->keywords);
    }

    public function test_parser_reads_range_with_shared_unit(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('بين 50 و 100 الف');
        $this->assertSame(50000 * 100, $q->minPrice);
        $this->assertSame(100000 * 100, $q->maxPrice);
        $this->assertSame([], $q->keywords);
    }

    public function test_parser_reads_min_price(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('ساعة فوق 500 الف');
        $this->assertSame(500000 * 100, $q->minPrice);
        $this->assertSame(['ساعه'], $q->keywords);
    }

    public function test_parser_extracts_color_size_and_cheapest(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('ابحث عن قميص احمر مقاس XL ارخص شي');
        $this->assertSame('احمر', $q->color);
        $this->assertSame('XL', $q->size);
        $this->assertTrue($q->cheapestFirst);
        $this->assertSame(['قميص'], $q->keywords);
        $this->assertTrue($q->hasCriteria());
    }

    public function test_parser_feminine_color_and_numeric_size(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('بدي صباط سودا نمرة 42');
        $this->assertSame('اسود', $q->color);
        $this->assertSame('42', $q->size);
        $this->assertSame(['صباط'], $q->keywords);
    }

    public function test_parser_with_nothing_useful_has_no_criteria(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('ابحث عن');
        $this->assertFalse($q->hasCriteria());
    }

    public function test_lexicon_expands_synonyms_and_spellings(): void
    {
        $alts = Lexicon::alternatives('موبايل');
        $this->assertContains('جوال', $alts);
        $this->assertContains('هاتف', $alts);

        $alts = Lexicon::alternatives('ساعه');
        $this->assertContains('ساعة', $alts);   // التاء المربوطة كما تُخزَّن في الأسماء

        $alts = Lexicon::alternatives('الموبايل');
        $this->assertContains('موبايل', $alts);
    }

    public function test_parser_survives_absurd_numbers(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('موبايل تحت 99999999999999 مليون');
        $this->assertIsInt($q->maxPrice);
    }

    public function test_range_unit_is_not_shared_when_it_would_invert_the_range(): void
    {
        $q = (new QueryParser(new Normalizer))->parse('بين 500 و 2 مليون');
        $this->assertSame(500 * 100, $q->minPrice);
        $this->assertSame(2000000 * 100, $q->maxPrice);
    }
}
