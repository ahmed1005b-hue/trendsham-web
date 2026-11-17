<?php

namespace App\Http\Controllers\Api;

use App\Auth\Social\SocialAccountResolver;
use App\Auth\Social\SocialLoginException;
use App\Auth\Social\SocialTokenVerifier;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AccountClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * دخول العميل من التطبيق بـ Google أو Facebook: التطبيق يرسل رمز المزوّد،
 * نتحقق منه عند المزوّد نفسه، ثم نعيد رمز دخول عادياً مثل /auth/login.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialTokenVerifier $verifier,
        private readonly SocialAccountResolver $resolver,
    ) {}

    /** المزوّدات المفعّلة حالياً، ليُظهر التطبيق أزرارها فقط. */
    public function providers(): JsonResponse
    {
        return response()->json([
            'google' => SocialTokenVerifier::isConfigured('google'),
            'facebook' => SocialTokenVerifier::isConfigured('facebook'),
            // معرّف عميل الويب عام (ليس سرّاً): يحتاجه تطبيق Android ليصدر Google رمز الهوية لنا
            'google_client_id' => SocialTokenVerifier::isConfigured('google') ? config('services.google.client_id') : null,
        ]);
    }

    public function login(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:4096']]);

        try {
            $user = $this->resolver->resolve($this->verifier->verify($provider, $validated['token']));
        } catch (SocialLoginException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $notice = app(AccountClosureService::class)->onLogin($user);

        return response()->json([
            'token' => $user->createToken('mobile-app')->plainTextToken,
            'user' => new UserResource($user),
            'notice' => $notice,
            'is_new' => $user->wasRecentlyCreated,
        ]);
    }
}
