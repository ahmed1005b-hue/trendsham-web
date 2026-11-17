<?php

namespace App\Auth\Social;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * يتحقق من رمز التطبيق مباشرة عند Google/Facebook قبل الوثوق بأي بيانات.
 * Google: رمز الهوية (id_token) يجب أن يكون صادراً لأحد معرّفات عملائنا.
 * Facebook: رمز الوصول يجب أن يكون صادراً لتطبيقنا نحن (لا لتطبيق آخر).
 */
class SocialTokenVerifier
{
    public function verify(string $provider, string $token): SocialIdentity
    {
        if (! self::isConfigured($provider)) {
            throw new SocialLoginException(__('الدخول بهذه الطريقة غير مفعّل حالياً.'), 503);
        }

        try {
            return $provider === 'google' ? $this->google($token) : $this->facebook($token);
        } catch (SocialLoginException $e) {
            throw $e;
        } catch (Throwable) {
            throw $this->invalid();
        }
    }

    public static function isConfigured(string $provider): bool
    {
        return match ($provider) {
            'google' => self::googleClientIds() !== [],
            'facebook' => filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret')),
            default => false,
        };
    }

    /** الموقع يحتاج السرّ أيضاً (تحويل إلى المزوّد ثم العودة)؛ التطبيق يكفيه المعرّف. */
    public static function isWebConfigured(string $provider): bool
    {
        return self::isConfigured($provider) && filled(config("services.$provider.client_id"))
            && filled(config("services.$provider.client_secret"));
    }

    /** @return array<int, string> */
    public static function googleClientIds(): array
    {
        return array_values(array_filter(
            [config('services.google.client_id'), ...explode(',', (string) config('services.google.extra_client_ids'))],
            fn ($id) => filled($id),
        ));
    }

    private function invalid(): SocialLoginException
    {
        return new SocialLoginException(__('تعذّر التحقق من حسابك. حاول مجدداً.'), 401);
    }

    private function google(string $idToken): SocialIdentity
    {
        $response = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        $data = $response->json() ?? [];

        if (! $response->successful()
            || ! in_array($data['aud'] ?? null, array_map('trim', self::googleClientIds()), true)
            || ! in_array($data['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || (int) ($data['exp'] ?? 0) < time()
            || blank($data['sub'] ?? null)) {
            throw $this->invalid();
        }

        return self::fromGoogle($data);
    }

    private function facebook(string $accessToken): SocialIdentity
    {
        $appId = (string) config('services.facebook.client_id');
        $graph = 'https://graph.facebook.com/v19.0';

        $debug = Http::timeout(8)->get("$graph/debug_token", [
            'input_token' => $accessToken,
            'access_token' => $appId.'|'.config('services.facebook.client_secret'),
        ])->json('data') ?? [];

        if (($debug['is_valid'] ?? false) !== true || (string) ($debug['app_id'] ?? '') !== $appId) {
            throw $this->invalid();
        }

        $me = Http::timeout(8)->get("$graph/me", [
            'fields' => 'id,first_name,last_name,email',
            'access_token' => $accessToken,
        ]);
        if (! $me->successful() || (string) $me->json('id') !== (string) ($debug['user_id'] ?? '')) {
            throw $this->invalid();
        }

        return self::fromFacebook($me->json());
    }

    /** @param array<string, mixed> $data */
    public static function fromGoogle(array $data): SocialIdentity
    {
        return new SocialIdentity(
            provider: 'google',
            id: (string) $data['sub'],
            email: isset($data['email']) ? strtolower((string) $data['email']) : null,
            emailVerified: in_array($data['email_verified'] ?? $data['verified_email'] ?? false, [true, 'true', 1, '1'], true),
            firstName: $data['given_name'] ?? null,
            lastName: $data['family_name'] ?? null,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromFacebook(array $data): SocialIdentity
    {
        return new SocialIdentity(
            provider: 'facebook',
            id: (string) $data['id'],
            email: isset($data['email']) ? strtolower((string) $data['email']) : null,
            // Facebook لا يعيد البريد إلا إن كان مؤكَّداً لديه
            emailVerified: isset($data['email']),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
        );
    }
}
