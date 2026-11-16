<?php

namespace App\Notifications\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * إرسال مباشر إلى Firebase Cloud Messaging (HTTP v1) بمفتاح حساب الخدمة، بلا حزم إضافية:
 * JWT موقّع RS256 ← access token من Google (كاش 50 دقيقة) ← messages:send.
 * لا ترمي استثناءات أبداً: فشل Firebase لا يُفشل أي عملية في الموقع.
 */
class FcmClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'fcm.access_token';

    public const CHANNEL = 'trendsham_default';

    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    public function send(string $token, string $title, ?string $body, array $data): FcmResult
    {
        $creds = $this->credentials();
        if ($creds === null) {
            return FcmResult::failed('not_configured');
        }

        try {
            $access = $this->accessToken();
            if ($access === null) {
                return FcmResult::failed('auth_failed');
            }

            $notification = ['title' => $title];
            if ($body !== null && $body !== '') {
                $notification['body'] = $body;
            }

            $response = Http::withToken($access)->timeout(10)->acceptJson()->post(
                'https://fcm.googleapis.com/v1/projects/'.$this->projectId($creds).'/messages:send',
                ['message' => [
                    'token' => $token,
                    'notification' => $notification,
                    'data' => array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), array_filter($data, fn ($v) => $v !== null)),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => self::CHANNEL, 'sound' => 'default']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ]],
            );

            if ($response->successful()) {
                return FcmResult::sent();
            }

            $status = $response->json('error.status');
            $codes = collect($response->json('error.details') ?? [])->pluck('errorCode')->filter()->all();
            $invalid = $response->status() === 404 || $status === 'NOT_FOUND' || $status === 'INVALID_ARGUMENT'
                || in_array('UNREGISTERED', $codes, true) || in_array('INVALID_ARGUMENT', $codes, true);

            if ($response->status() === 401) {
                Cache::forget(self::CACHE_KEY);
            }

            return FcmResult::failed('http_'.$response->status().($status ? ':'.$status : ''), $invalid);
        } catch (Throwable $e) {
            Log::warning('FCM send failed: '.$e->getMessage());

            return FcmResult::failed('exception:'.class_basename($e));
        }
    }

    /** رمز الدخول لواجهة FCM، مخزّن 50 دقيقة. null عند الفشل. */
    public function accessToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $creds = $this->credentials();
        if ($creds === null) {
            return null;
        }

        $now = time();
        $segments = [
            $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->b64(json_encode([
                'iss' => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];
        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('FCM: could not sign JWT');

            return null;
        }
        $segments[] = $this->b64($signature);

        $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', $segments),
        ]);
        $token = $response->successful() ? $response->json('access_token') : null;
        if (! is_string($token) || $token === '') {
            Log::warning('FCM: token exchange failed with HTTP '.$response->status());

            return null;
        }

        Cache::put(self::CACHE_KEY, $token, now()->addMinutes(50));

        return $token;
    }

    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('services.firebase.credentials');
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/') && ! str_starts_with($path, '\\') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
            return null;
        }

        return $this->credentials = $json;
    }

    private function projectId(array $creds): string
    {
        return (string) (config('services.firebase.project_id') ?: ($creds['project_id'] ?? ''));
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
