<?php

namespace Tests\Concerns;

/**
 * يعرض صفحة بلغة معيّنة ويعيد كل نص عربي بقي ظاهراً للمستخدم
 * (نص أو placeholder/title/aria-label/alt/data-confirm)، متجاهلاً
 * السكربتات وما عليه translate="no" (بيانات المستخدمين).
 */
trait FindsUntranslatedArabic
{
    /** @return list<string> */
    protected function arabicLeftIn(string $locale, string $name, string $url): array
    {
        $response = $this->withSession(['locale' => $locale])->get($url);
        if ($response->getStatusCode() !== 200) {
            return ["[$locale] $name: HTTP {$response->getStatusCode()}"];
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//script|//style|//template|//*[@translate="no"]|//textarea|//select[@translate="no"]')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $found = [];
        foreach ($xpath->query('//body//text()') as $text) {
            $value = trim(preg_replace('/\s+/u', ' ', $text->nodeValue));
            if (preg_match('/\p{Arabic}/u', $value)) {
                $found[$value] = true;
            }
        }
        foreach ($xpath->query('//@placeholder|//@title|//@aria-label|//@alt|//@data-confirm') as $attr) {
            if (preg_match('/\p{Arabic}/u', $attr->value)) {
                $found['@'.$attr->name.'="'.$attr->value.'"'] = true;
            }
        }

        return array_map(fn ($text) => "[$locale] $name: ".mb_substr($text, 0, 160), array_keys($found));
    }
}
