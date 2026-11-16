<?php

namespace Tests\Unit\Push;

use App\Notifications\Push\FcmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Push\PushTestHelpers;
use Tests\TestCase;

class FcmClientTest extends TestCase
{
    use PushTestHelpers;

    public function test_without_credentials_file_it_is_not_configured_and_sends_nothing(): void
    {
        $this->noFcm();
        $client = app(FcmClient::class);

        $this->assertFalse($client->isConfigured());
        $result = $client->send('tok', 'عنوان', 'نص', ['screen' => 'order']);

        $this->assertFalse($result->ok);
        $this->assertSame('not_configured', $result->error);
        Http::assertNothingSent();
    }

    public function test_sends_v1_message_with_bearer_token_and_string_data(): void
    {
        $this->fakeFcm();
        $result = app(FcmClient::class)->send('device-1', 'طلبك تم شحنه', 'التفاصيل', ['notification_id' => 981, 'screen' => 'order', 'id' => 14]);

        $this->assertTrue($result->ok, (string) $result->error);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }
            $m = $request->data()['message'];

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
                && $request->hasHeader('Authorization', 'Bearer fake-access')
                && $m['token'] === 'device-1'
                && $m['notification'] === ['title' => 'طلبك تم شحنه', 'body' => 'التفاصيل']
                && $m['data'] === ['notification_id' => '981', 'screen' => 'order', 'id' => '14']
                && $m['android']['priority'] === 'high'
                && $m['android']['notification']['channel_id'] === 'trendsham_default'
                && $m['android']['notification']['sound'] === 'default';
        });
    }

    public function test_access_token_is_a_signed_jwt_and_is_cached(): void
    {
        $this->fakeFcm();
        $client = app(FcmClient::class);
        $client->send('a', 't', null, []);
        $client->send('b', 't', null, []);

        $tokenCalls = collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'oauth2.googleapis.com'));
        $this->assertCount(1, $tokenCalls);

        $req = $tokenCalls->first()[0];
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $req->data()['grant_type']);
        [$h, $p, $sig] = explode('.', $req->data()['assertion']);
        $header = json_decode(base64_decode(strtr($h, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $payload['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $payload['aud']);

        $key = json_decode(file_get_contents(config('services.firebase.credentials')), true)['private_key'];
        $public = openssl_pkey_get_details(openssl_pkey_get_private($key))['key'];
        $raw = base64_decode(strtr($sig, '-_', '+/').str_repeat('=', (4 - strlen($sig) % 4) % 4));
        $this->assertSame(1, openssl_verify("$h.$p", $raw, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_unregistered_or_invalid_token_is_reported(): void
    {
        $this->fakeFcm([
            Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
            Http::response(['error' => ['status' => 'INVALID_ARGUMENT']], 400),
        ]);
        $client = app(FcmClient::class);

        $a = $client->send('gone', 't', null, []);
        $b = $client->send('bad', 't', null, []);

        $this->assertFalse($a->ok);
        $this->assertTrue($a->invalidToken);
        $this->assertTrue($b->invalidToken);
    }

    public function test_server_error_and_timeout_do_not_throw(): void
    {
        $this->fakeFcm([Http::response(['error' => ['status' => 'INTERNAL']], 500)]);
        $a = app(FcmClient::class)->send('x', 't', null, []);
        $this->assertFalse($a->ok);
        $this->assertFalse($a->invalidToken);

        Http::fake(['fcm.googleapis.com/*' => fn () => throw new ConnectionException('timeout')]);
        $b = app(FcmClient::class)->send('x', 't', null, []);
        $this->assertFalse($b->ok);
        $this->assertNotNull($b->error);
    }
}
