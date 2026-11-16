<?php

namespace Tests\Feature\Push;

use App\Models\Order;
use App\Models\PushDevice;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * أدوات اختبارات الإشعارات المنبثقة. Firebase مزيّف دائماً: لا طلب حقيقي يخرج.
 * مفتاح الخدمة يُولَّد عند الاختبار (RSA مؤقت) ولا يُحفظ في المستودع.
 */
trait PushTestHelpers
{
    protected static ?string $fakeCredentialsPath = null;

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'customer', 'phone_verified_at' => now()]);
    }

    protected function seller(string $status = 'approved'): SellerProfile
    {
        $user = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create([
            'user_id' => $user->id, 'store_name' => 'متجر '.Str::random(4), 'status' => $status, 'commission_rate' => 10,
            'identity_verified_at' => now(),
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function order(User $customer, string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)), 'user_id' => $customer->id,
            'status' => $status, 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
    }

    protected function device(User $user, ?string $token = null): PushDevice
    {
        return PushDevice::create([
            'user_id' => $user->id, 'token' => $token ?? 'tok-'.Str::random(20), 'platform' => 'android', 'last_seen_at' => now(),
        ]);
    }

    /** يفعّل Firebase بمفتاح وهمي ويزيّف ردوده. $responses: ردود متتالية لطلبات الإرسال (افتراضياً نجاح). */
    protected function fakeFcm(array $responses = []): void
    {
        config([
            'services.firebase.credentials' => $this->fakeCredentials(),
            'services.firebase.project_id' => 'test-project',
        ]);
        Cache::forget('fcm.access_token');

        $send = Http::sequence();
        foreach ($responses as $r) {
            $send->pushResponse($r);
        }
        $send->whenEmpty(Http::response(['name' => 'projects/test-project/messages/1'], 200));

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            'fcm.googleapis.com/*' => $send,
        ]);
    }

    /** Firebase غير مُعدّ (لا ملف مفتاح) مع منع أي طلب HTTP. */
    protected function noFcm(): void
    {
        config(['services.firebase.credentials' => base_path('tests/Fixtures/does-not-exist.json')]);
        Http::preventStrayRequests();
        Http::fake();
    }

    /** رسائل الإرسال المسجّلة إلى Firebase: [token, title, body, data]. */
    protected function sentPushes(): Collection
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
            ->map(function ($pair) {
                $m = $pair[0]->data()['message'] ?? [];

                return [
                    'token' => $m['token'] ?? null,
                    'title' => $m['notification']['title'] ?? null,
                    'body' => $m['notification']['body'] ?? null,
                    'data' => $m['data'] ?? [],
                ];
            })->values();
    }

    /** ينفّذ ما أُجِّل إلى ما بعد الرد (الإرسال للهاتف). */
    protected function runAfterResponse(): void
    {
        app()->terminate();
    }

    private function fakeCredentials(): string
    {
        if (static::$fakeCredentialsPath && is_file(static::$fakeCredentialsPath)) {
            return static::$fakeCredentialsPath;
        }

        // على ويندوز يحتاج openssl ملف إعداد؛ نجرّب الموجود مع PHP.
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([getenv('OPENSSL_CONF') ?: null, dirname(PHP_BINARY).'/extras/ssl/openssl.cnf', dirname(PHP_BINARY, 2).'/apache/conf/openssl.cnf'] as $cnf) {
            if ($cnf && is_file($cnf)) {
                $opts['config'] = $cnf;
                break;
            }
        }
        $key = openssl_pkey_new($opts);
        openssl_pkey_export($key, $pem, null, $opts);
        $path = tempnam(sys_get_temp_dir(), 'fcm').'.json';
        file_put_contents($path, json_encode([
            'type' => 'service_account', 'project_id' => 'test-project', 'private_key' => $pem,
            'client_email' => 'test@test-project.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        return static::$fakeCredentialsPath = $path;
    }
}
