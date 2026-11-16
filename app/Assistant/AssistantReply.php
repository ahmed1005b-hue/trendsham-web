<?php

namespace App\Assistant;

final class AssistantReply
{
    /**
     * @param  list<array>  $cards  بطاقات (product | order | link)
     * @param  list<string>  $chips  اقتراحات سريعة تُرسل كنص عند الضغط
     * @param  array|null  $pending  إجراء ينتظر "نعم" — يبقى في الخادم ولا يُرسل للعميل
     */
    public function __construct(
        public readonly string $text,
        public readonly string $intent = 'unknown',
        public readonly array $cards = [],
        public readonly array $chips = [],
        public readonly ?array $pending = null,
        public readonly bool $handoff = false,
    ) {}

    /** ما يُحفظ مع الرسالة (tool_calls) */
    public function meta(): array
    {
        return array_filter([
            'intent' => $this->intent,
            'cards' => $this->cards,
            'chips' => $this->chips,
            'pending' => $this->pending,
            'handoff' => $this->handoff,
        ], fn ($v) => $v !== [] && $v !== null && $v !== false);
    }

    /** ما يراه العميل: بلا تفاصيل الإجراء المعلّق */
    public static function publicMeta(?array $meta): array
    {
        $meta ??= [];
        if (isset($meta['pending'])) {
            $meta['awaiting_confirmation'] = true;
            unset($meta['pending']);
        }

        return $meta;
    }
}
