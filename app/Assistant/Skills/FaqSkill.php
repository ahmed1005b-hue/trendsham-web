<?php

namespace App\Assistant\Skills;

use App\Assistant\AssistantReply;
use App\Assistant\Chips;
use App\Assistant\Models\AssistantFaq;
use App\Assistant\Nlp\Normalizer;

/** أجوبة المدير للأسئلة الشائعة — والمساعد لا يخترع سياسة غير مكتوبة */
class FaqSkill
{
    /** كلمات تدل على سؤال عن سياسة المتجر */
    private const POLICY = ['ارجاع', 'استرجاع', 'ترجيع', 'رجع', 'ارجع', 'رجعه', 'استبدال', 'تبديل', 'سياسه', 'ضمان', 'كفاله', 'توصيل', 'شحن',
        'الدفع', 'دفع', 'كاش', 'تقسيط', 'فاتوره', 'مده', 'استرداد', 'مصاري', 'رسوم'];

    public function __construct(private readonly Normalizer $normalizer) {}

    public function answer(string $message): ?AssistantReply
    {
        $text = $this->normalizer->normalize($message);
        $best = null;
        $bestScore = 0;

        foreach (AssistantFaq::where('is_active', true)->orderBy('sort_order')->get() as $faq) {
            $score = 0;
            foreach ($faq->keywordList() as $kw) {
                if ($this->normalizer->matches($text, [$this->normalizer->normalize($kw)], fuzzy: false)) {
                    $score += substr_count($kw, ' ') + 1;   // العبارة الأطول أدق
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$faq, $score];
            }
        }

        return $best ? new AssistantReply($best->answer, 'faq', chips: [Chips::HUMAN]) : null;
    }

    public function isPolicyQuestion(string $message): bool
    {
        return $this->normalizer->matches($this->normalizer->normalize($message), self::POLICY, fuzzy: false);
    }

    public function noAnswer(): AssistantReply
    {
        return new AssistantReply(
            'هالسؤال ما عندي جواب مؤكد عليه، وما بدي أعطيك معلومة غلط 🙏 بتحب حوّلك لموظف خدمة العملاء؟',
            'faq', chips: [Chips::HUMAN],
        );
    }
}
