<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * auth.session: يُخرج جلسة الموقع إن تغيّرت كلمة السر من جهاز آخر.
 * يعمل فقط مع جلسة الموقع؛ الدخول برمز التطبيق (sanctum) لا جلسة له يتحقق منها.
 */
class AuthenticateWebSession extends AuthenticateSession
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (! $this->auth->guard() instanceof SessionGuard) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
