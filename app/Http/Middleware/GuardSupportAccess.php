<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SellerSupportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحرس جلسة «وضع الدعم» (موظف داخل لوحة متجر بإذن البائع) مع كل طلب:
 * - أنهى البائع الإذن، أو لم يعد الموظف في فريق الدعم: يُعاد الموظف إلى حسابه فوراً.
 * - صفحة خارج ما يسمح به SellerSupportAccess::ALLOWED_ROUTES: ممنوعة.
 * - تغيير نجح: يُسجَّل باسم الموظف في سجل الفريق ويراه البائع.
 */
class GuardSupportAccess
{
    public function __construct(private SellerSupportAccess $access) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $support = $this->access->current($request);
        if ($support === null) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! $this->access->stillAllowed($request, $support)) {
            return $this->sendBack($request, $this->access->leave($request), __('أنهى البائع إذن الدخول إلى لوحة متجره.'));
        }

        if ($routeName === 'support-access.leave' || $routeName === 'logout') {
            return $this->sendBack($request, $this->access->leave($request), __('خرجت من لوحة المتجر.'));
        }

        if (! $this->access->routeAllowed($request->route())) {
            $message = __('هذه الصفحة غير متاحة لفريق الدعم داخل لوحة المتجر.');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('seller.dashboard')->with('support_notice', $message);
        }

        $response = $next($request);

        if (! $request->isMethodSafe() && $response->getStatusCode() < 400 && ! $this->failedValidation($request)) {
            $this->access->recordChange($request, $support);
        }

        return $response;
    }

    private function failedValidation(Request $request): bool
    {
        return in_array('errors', (array) $request->session()->get('_flash.new', []), true);
    }

    private function sendBack(Request $request, ?User $staff, string $message): Response
    {
        $target = match (true) {
            $staff === null => route('login'),
            $staff->isAdmin() => route('admin.support.index'),
            default => route('employee.support.index'),
        };

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $target], 409);
        }

        return redirect()->to($target)->with('support_notice', $message);
    }
}
