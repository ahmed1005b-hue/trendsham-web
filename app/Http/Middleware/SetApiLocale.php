<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\MessageTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغة طلبات الـ API من ترويسة X-App-Locale التي يرسلها التطبيق حسب اختيار المستخدم
 * (ar إن غابت). لا نعتمد Accept-Language: المتصفحات وعملاء HTTP يرسلونها تلقائياً
 * (en-us مثلاً) دون أن يختار المستخدم لغة.
 *
 * تضبط لغة Laravel (فتُترجم __() ورسائل التحقق)، ثم تترجم نصوص الرد الموجّهة
 * للمستخدم والمكتوبة بالعربية في الكود: message/error وكل ما تحت errors، والحقول
 * التي ينتهي اسمها بـ label أو text. بيانات المستخدمين (أسماء المنتجات، عناوين
 * الكوبونات، الملاحظات) لا تُمس لأنها ليست ضمن هذه المفاتيح.
 */
class SetApiLocale
{
    public const LOCALES = ['ar', 'en', 'tr'];

    public const HEADER = 'X-App-Locale';

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header(self::HEADER);
        $locale = self::fromHeader($header);
        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        // لغة التطبيق تُحفظ للمستخدم، ليصله ما يُرسل في الخلفية (إشعارات، SMS، بريد) بلغته
        if ($header !== '' && ($user = $request->user()) && method_exists($user, 'rememberLocale')) {
            $user->rememberLocale($locale);
        }

        if ($locale !== 'ar' && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $response->setData($this->translate($data, $locale, false));
            }
        }

        return $response;
    }

    /** أول لغة مدعومة في الترويسة ("tr-TR,tr;q=0.9,en" ← tr). */
    public static function fromHeader(string $header): string
    {
        foreach (explode(',', strtolower($header)) as $part) {
            $code = substr(trim(explode(';', $part)[0]), 0, 2);
            if (in_array($code, self::LOCALES, true)) {
                return $code;
            }
        }

        return 'ar';
    }

    private function translate(array $data, string $locale, bool $userFacing): array
    {
        foreach ($data as $key => $value) {
            $facing = $userFacing || (is_string($key) && self::isUserFacingKey($key));
            if (is_array($value)) {
                $data[$key] = $this->translate($value, $locale, $facing);
            } elseif ($facing && is_string($value)) {
                $data[$key] = MessageTranslator::translate($value, $locale);
            }
        }

        return $data;
    }

    private static function isUserFacingKey(string $key): bool
    {
        return in_array($key, ['message', 'error', 'errors'], true)
            || preg_match('/(^|_)(label|text)$/', $key) === 1;
    }
}
