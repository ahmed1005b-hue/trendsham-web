<?php

namespace App\Assistant\Nlp;

/** ما فهمناه من جملة البحث. الأسعار بالوحدة الصغرى (×100) مثل قاعدة البيانات. */
final class SearchQuery
{
    /** @param list<string> $keywords */
    public function __construct(
        public readonly array $keywords = [],
        public readonly ?int $minPrice = null,
        public readonly ?int $maxPrice = null,
        public readonly ?string $color = null,
        public readonly ?string $size = null,
        public readonly bool $cheapestFirst = false,
    ) {}

    public function hasCriteria(): bool
    {
        return $this->keywords !== [] || $this->minPrice !== null || $this->maxPrice !== null
            || $this->color !== null || $this->size !== null;
    }
}
