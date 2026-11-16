<?php

namespace App\Assistant\Nlp;

use Carbon\CarbonImmutable;

/** الفترة الزمنية في سؤال البائع: اليوم، امبارح، هالأسبوع، هالشهر (الافتراضي)، هالسنة */
final class Period
{
    public function __construct(
        public readonly string $label,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromMessage(string $message, ?Normalizer $normalizer = null): self
    {
        $n = $normalizer ?? new Normalizer;
        $text = $n->normalize($message);
        $now = CarbonImmutable::now();

        return match (true) {
            $n->matches($text, ['الشهر الماضي', 'الشهر اللي فات', 'الشهر السابق', 'الشهر الفايت', 'last month'], false)
                => new self('الشهر الماضي', $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()),
            $n->matches($text, ['الاسبوع الماضي', 'الاسبوع اللي فات', 'الاسبوع السابق', 'الاسبوع الفايت', 'last week'], false)
                => new self('الأسبوع الماضي', $now->subDays(13)->startOfDay(), $now->subDays(7)->endOfDay()),
            $n->matches($text, ['امبارح', 'مبارح', 'البارحه', 'يوم امس', 'امس', 'yesterday'], false)
                => new self('امبارح', $now->subDay()->startOfDay(), $now->subDay()->endOfDay()),
            $n->matches($text, ['اليوم', 'هاليوم', 'today'], false)
                => new self('اليوم', $now->startOfDay(), $now),
            $n->matches($text, ['الاسبوع', 'هالاسبوع', 'اسبوع', 'week'], false)
                => new self('آخر 7 أيام', $now->subDays(6)->startOfDay(), $now),
            $n->matches($text, ['السنه', 'هالسنه', 'سنه', 'year'], false)
                => new self('هالسنة', $now->startOfYear(), $now),
            default => new self('هالشهر', $now->startOfMonth(), $now),
        };
    }
}
