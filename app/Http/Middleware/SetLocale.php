<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const LOCALES = ['ar', 'en', 'tr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'ar');

        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);
        $response = $next($request);

        // لغة اختارها المستخدم صراحةً (لا الافتراضية) تُحفظ له لما يُرسل في الخلفية
        $chosen = $request->session()->get('locale') ?? $request->cookie('locale');
        if ($chosen === $locale && ($user = $request->user()) && method_exists($user, 'rememberLocale')) {
            $user->rememberLocale($locale);
        }

        if ($locale !== 'ar' && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $translations = self::dictionary($locale);

            if ($translations !== []) {
                $content = $this->translateHtmlText($response->getContent(), $translations);
                // السكربتات والأنماط تُتخطّى كاملة: فيها نصوص تشبه الوسوم ('<span title="...">')
                // وأي تهريب HTML داخلها يكسر الـ JavaScript.
                $content = preg_replace_callback('/<(script|style)\b[^>]*>.*?<\/\1\s*>|<([a-z][a-z0-9:-]*)\b([^>]*)>/isu', function ($match) use ($translations) {
                    if (($match[1] ?? '') !== '') {
                        return $match[0];
                    }
                    if (preg_match('/\btranslate\s*=\s*["\']no["\']/i', $match[3])) {
                        return $match[0];
                    }

                    $attributes = preg_replace_callback('/\b(placeholder|title|aria-label|alt|data-confirm)="([^"]*)"/u', function ($attribute) use ($translations) {
                        $value = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $translated = $this->translateText($value, $translations);

                        // لا نلمس ما لم يتغيّر، حتى لا يُعاد تهريبه مرتين
                        return $translated === $value ? $attribute[0] : $attribute[1].'="'.e($translated).'"';
                    }, $match[3]) ?? $match[3];

                    return '<'.$match[2].$attributes.'>';

                }, $content) ?? $content;
                $response->setContent($content);
            }
        }

        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->setContent($this->applyDocumentPreferences($response->getContent(), $locale));
        }

        return $response;
    }

    private function applyDocumentPreferences(string $html, string $locale): string
    {
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $html = preg_replace_callback('/<html\b([^>]*)>/i', function ($match) use ($locale, $direction) {
            $attributes = preg_replace('/\s+(?:lang|dir)="[^"]*"/i', '', $match[1]);

            return '<html'.$attributes.' lang="'.$locale.'" dir="'.$direction.'">';
        }, $html, 1) ?? $html;
        // نسخة Bootstrap حسب الاتجاه، مع بصمة SRI الخاصة بكل ملف (وإلا يحجبه المتصفح)
        $bootstrap = [
            'bootstrap.rtl.min.css' => 'sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb',
            'bootstrap.min.css' => 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH',
        ];
        $want = $locale === 'ar' ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css';
        $html = preg_replace_callback('/<link\b[^>]*bootstrap@5\.3\.3\/dist\/css\/bootstrap(?:\.rtl)?\.min\.css[^>]*>/i', function ($match) use ($bootstrap, $want) {
            $tag = preg_replace('/bootstrap(?:\.rtl)?\.min\.css/', $want, $match[0]);

            return preg_replace('/integrity="[^"]*"/', 'integrity="'.$bootstrap[$want].'"', $tag);
        }, $html) ?? $html;

        if (str_contains($html, 'data-ts-preferences')) {
            return $html;
        }

        // شريط preferences (اللغة والمظهر) منقول إلى صفحة حسابي، لا نحقنه هنا.

        // شريط preferences منقول إلى صفحة حسابي، لا نحقنه في أعلى الصفحة.

        $appearance = <<<'HTML'
<style>
.ts-preferences{position:relative;z-index:1050;display:flex;justify-content:flex-end;align-items:center;gap:.75rem;padding:.4rem max(1rem,calc((100% - 1200px)/2));background:#f5f3ee;color:#1f2a24;border-bottom:1px solid #ddd;direction:inherit;font:500 .875rem system-ui,sans-serif}
.ts-preference-group{display:flex;align-items:center;gap:.4rem;margin:0}
.ts-preferences select{max-width:10rem;padding:.25rem .45rem;border:1px solid #aaa;border-radius:.35rem;background:#fff;color:#222;font:inherit}
html[lang="en"] body,html[lang="tr"] body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
/* بيانات المستخدمين (أسماء المنتجات والمتاجر...) غالباً عربية: اتجاهها من محتواها لا من الصفحة */
:where(html[dir="ltr"] [translate="no"]){unicode-bidi:plaintext}
:where(html[dir="ltr"] :is(h1,h2,h3,h4,h5,h6,p,div,li)[translate="no"]){text-align:left}
/* خطوط العناوين العربية (Reem Kufi/Tajawal) تضيّق المسافات وتفتقد أحرفاً تركية؛ الشعار يحتفظ بخطه */
html[lang="en"] body *:not(.ts-logo,.ts-logo *,.ts-footer__brand,.ts-footer__brand *,.bi,code,pre,kbd,.font-monospace),html[lang="tr"] body *:not(.ts-logo,.ts-logo *,.ts-footer__brand,.ts-footer__brand *,.bi,code,pre,kbd,.font-monospace){font-family:inherit}
html[lang="en"] h1,html[lang="en"] h2,html[lang="en"] h3,html[lang="tr"] h1,html[lang="tr"] h2,html[lang="tr"] h3{font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
html.dark .ts-preferences{background:#202a26;color:#e6ece8;border-color:#3b4943}
html.dark .ts-preferences select,html.dark .ts-preference-group select{background:#151b19;color:#e6ece8;border-color:#64736c}
@media(max-width:520px){.ts-preferences{justify-content:center;flex-wrap:wrap}.ts-preference-group{gap:.25rem}}
@media print{.ts-preferences{display:none!important}}
</style>
HTML;
        // الوضع الليلي/النهاري لم يعد يُحقن هنا: مصدره الوحيد partials/theme-head
        // (كان هنا نظام ثالث بمفتاح ومحدد مختلفين يتعارض مع زر الهيدر).
        if (preg_match('/<\/head>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $html = substr_replace($html, $appearance, $match[0][1], 0);
        }

        return $html;
    }

    /** @var array<string, array{0: array<string, string>, 1: list<string>}> قاموس اللغة وأنماطه، مرة لكل ملف */
    private static array $compiled = [];

    /** قاموس اللغة (العربي ← الترجمة)، يُقرأ مرة لكل عملية ما لم يتغير الملف. */
    public static function dictionary(string $locale): array
    {
        return self::compiled($locale)[0];
    }

    private static function compiled(string $locale): array
    {
        $path = lang_path($locale.'.json');
        $key = $locale.'@'.(File::exists($path) ? File::lastModified($path) : 0);
        if (isset(self::$compiled[$key])) {
            return self::$compiled[$key];
        }

        $translations = File::exists($path) ? (json_decode(File::get($path), true) ?? []) : [];
        // الأطول أولاً: "سياسة الاسترجاع" قبل "سياسة"
        $keys = array_keys($translations);
        usort($keys, fn (string $left, string $right) => strlen($right) <=> strlen($left));
        $patterns = [];
        foreach (array_chunk($keys, 100) as $chunk) {
            $patterns[] = '/(?<![\p{L}\p{N}])('.implode('|', array_map(fn (string $k) => preg_quote($k, '/'), $chunk)).')(?![\p{L}\p{N}])/u';
        }

        return self::$compiled[$key] = [$translations, $patterns];
    }

    private function translateText(string $text, array $translations): string
    {
        // نص بلا حروف عربية لا يحتاج ترجمة (أرقام، أسماء لاتينية، رموز)
        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        foreach (self::compiled(app()->getLocale())[1] as $pattern) {
            $text = preg_replace_callback($pattern, fn (array $match) => $translations[$match[1]] ?? $match[1], $text) ?? $text;
        }

        return $text;
    }

    private function translateHtmlText(string $html, array $translations): string
    {
        // نتتبّع العنصر المكبوت (translate="no") وحده: اسمه وعمق تداخله مع نفس الوسم.
        // وسوم أخرى غير مغلقة أو تعليقات لا تُربك التتبع، و<script>/<style>/<textarea>
        // تُتخطّى كاملة (قد تحوي نصوصاً شبيهة بالوسوم مثل '<span>' أو a < b).
        $suppressedTag = null;
        $depth = 0;
        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

        return preg_replace_callback('/<(script|style|textarea)\b[^>]*>.*?<\/\1\s*>|<!--.*?-->|<[^>]*>|[^<]+/isu', function (array $match) use (&$suppressedTag, &$depth, $void, $translations) {
            $part = $match[0];
            if (($match[1] ?? '') !== '' || str_starts_with($part, '<!')) {
                return $part;
            }

            if (str_starts_with($part, '<')) {
                if (preg_match('/^<(\/?)([a-z][a-z0-9-]*)/i', $part, $tagMatch) !== 1) {
                    return $part;
                }
                $closing = $tagMatch[1] === '/';
                $tag = strtolower($tagMatch[2]);
                $selfClosing = in_array($tag, $void, true) || preg_match('/\/\s*>$/', $part) === 1;

                if ($suppressedTag !== null) {
                    if ($tag === $suppressedTag && ! $selfClosing) {
                        $depth += $closing ? -1 : 1;
                        if ($depth === 0) {
                            $suppressedTag = null;
                        }
                    }
                } elseif (! $closing && ! $selfClosing && preg_match('/\btranslate\s*=\s*["\']no["\']/i', $part) === 1) {
                    $suppressedTag = $tag;
                    $depth = 1;
                }

                return $part;
            }

            if ($suppressedTag !== null) {
                return $part;
            }

            $leading = preg_match('/^\s*/u', $part, $prefix) ? $prefix[0] : '';
            $trailing = preg_match('/\s*$/u', $part, $suffix) ? $suffix[0] : '';
            $normalized = trim(preg_replace('/\s+/u', ' ', $part));

            return $leading.$this->translateText($normalized, $translations).$trailing;
        }, $html) ?? $html;
    }
}
