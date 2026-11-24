<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات أمان لكل رد: تمنع عرض الموقع داخل إطار في موقع آخر (clickjacking)،
 * وتمنع المتصفح من تخمين نوع الملف، ولا ترسل رابط الصفحة كاملاً لمواقع خارجية،
 * ولا تكشف إصدار PHP.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
