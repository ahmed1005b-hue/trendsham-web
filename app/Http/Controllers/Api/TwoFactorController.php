<?php

namespace App\Http\Controllers\Api;

use App\Auth\TwoFactor\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountClosureService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

/**
 * الخطوة الثانية في دخول التطبيق: /auth/login أعاد «challenge» بدل الرمز،
 * والتطبيق يرسله هنا مع رمز تطبيق المصادقة ليحصل على رمز الدخول.
 */
class TwoFactorController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public static function challengeFor(User $user): string
    {
        return Crypt::encryptString(json_encode(['id' => $user->id, 'exp' => now()->addMinutes(5)->timestamp]));
    }

    public function store(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:20'],
        ]);

        try {
            $data = json_decode(Crypt::decryptString($validated['challenge']), true);
        } catch (DecryptException) {
            $data = null;
        }
        $user = is_array($data) && ($data['exp'] ?? 0) > time() ? User::find($data['id'] ?? 0) : null;

        if (! $user || ! $user->hasTwoFactor() || $user->status !== 'active') {
            return response()->json(['message' => __('انتهت المهلة، سجّل دخولك من جديد.')], 401);
        }

        $key = 'two-factor-api:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => __('محاولات كثيرة جداً. سجّل دخولك من جديد بعد قليل.')], 429);
        }

        if (! $twoFactor->verify($user, $validated['code'])) {
            RateLimiter::hit($key, 300);

            return response()->json(['message' => __('الرمز غير صحيح.'), 'errors' => ['code' => [__('الرمز غير صحيح.')]]], 422);
        }

        RateLimiter::clear($key);

        return response()->json([
            'token' => $user->createToken('mobile-app')->plainTextToken,
            'user' => new UserResource($user),
            'notice' => app(AccountClosureService::class)->onLogin($user),
        ]);
    }
}
