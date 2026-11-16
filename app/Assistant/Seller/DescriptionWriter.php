<?php

namespace App\Assistant\Seller;

use App\Assistant\Nlp\Normalizer;

/**
 * يكتب مسودة وصف منتج من حقائق يعطيها البائع فقط (الاسم، القسم، الألوان، المقاسات، المتجر).
 * لا يخترع مواصفات أو مواد أو ضمانات؛ العبارة الافتتاحية عامة حسب نوع القسم.
 */
class DescriptionWriter
{
    /** نوع القسم => [كلمات تدل عليه، العبارة الافتتاحية] */
    private const INTROS = [
        'clothes' => [['ملابس', 'قميص', 'تيشيرت', 'بلوزه', 'فستان', 'بنطال', 'بنطلون', 'جاكيت', 'كنزه', 'عبايه', 'ازياء'], 'قطعة أنيقة ومريحة بتناسب إطلالتك اليومية والمناسبات.'],
        'shoes' => [['احذيه', 'حذاء', 'صباط', 'بوط', 'شوز', 'كندره', 'صندل'], 'خيار عملي ومريح لخطواتك اليومية.'],
        'electronics' => [['الكترونيات', 'موبايل', 'هاتف', 'جوال', 'لابتوب', 'سماعه', 'شاحن', 'ساعه ذكيه', 'تلفزيون', 'كمبيوتر'], 'جهاز عملي بيسهّل يومك وبيلبّي احتياجاتك.'],
        'beauty' => [['عطور', 'عطر', 'تجميل', 'مكياج', 'عنايه', 'كريم', 'الجمال'], 'لمسة عناية بتليق فيك وبتكمّل إطلالتك.'],
        'home' => [['المنزل', 'المطبخ', 'منزل', 'مطبخ', 'ديكور', 'مفروشات', 'اثاث'], 'إضافة حلوة وعملية لبيتك.'],
        'kids' => [['اطفال', 'الاطفال', 'العاب', 'لعبه', 'بيبي'], 'اختيار لطيف ومناسب للصغار.'],
    ];

    public function write(string $name, ?string $category = null, array $colors = [], array $sizes = [], ?string $store = null): string
    {
        $name = trim(strip_tags($name));
        $colors = $this->clean($colors);
        $sizes = $this->clean($sizes);

        $lines = ["{$name} — {$this->intro($name.' '.$category)}", ''];
        if ($category) {
            $lines[] = '✔️ القسم: '.trim(strip_tags($category));
        }
        if ($colors) {
            $lines[] = '✔️ الألوان المتوفرة: '.implode('، ', $colors);
        }
        if ($sizes) {
            $lines[] = '✔️ المقاسات المتوفرة: '.implode('، ', $sizes);
        }
        $lines[] = '✔️ يُباع من '.($store ? trim(strip_tags($store)) : 'متجرنا').' على تريندشام، مع إمكانية الدفع عند الاستلام.';
        $lines[] = '';
        $lines[] = 'اطلبه هلق ويوصلك لباب بيتك 🚚';

        return implode("\n", $lines);
    }

    private function intro(string $context): string
    {
        $n = new Normalizer;
        $text = $n->normalize($context);
        foreach (self::INTROS as [$words, $intro]) {
            if ($n->matches($text, $words, fuzzy: false)) {
                return $intro;
            }
        }

        return 'اختيار مميز بجودة بتستاهل ثقتك.';
    }

    /** @return list<string> */
    private function clean(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($v) => mb_substr(trim(strip_tags((string) $v)), 0, 40),
            $values,
        ))));
    }
}
