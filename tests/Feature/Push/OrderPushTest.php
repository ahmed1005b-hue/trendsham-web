<?php

namespace Tests\Feature\Push;

use App\Models\AppNotification;
use App\Notifications\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPushTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    private function changeStatus($order, string $status = 'processing')
    {
        return $this->actingAs($this->admin())->put(route('admin.orders.updateStatus', $order), ['status' => $status]);
    }

    private function lastNotification(int $userId): AppNotification
    {
        return AppNotification::where('user_id', $userId)->latest('id')->firstOrFail();
    }

    public function test_status_change_is_pushed_to_every_device_of_the_customer(): void
    {
        $this->fakeFcm();
        $customer = $this->customer();
        $this->device($customer, 'phone-a');
        $this->device($customer, 'phone-b');
        $order = $this->order($customer);

        $this->changeStatus($order)->assertSessionHas('success');

        $n = $this->lastNotification($customer->id);
        $pushes = $this->sentPushes();
        $this->assertCount(2, $pushes);
        $this->assertEqualsCanonicalizing(['phone-a', 'phone-b'], $pushes->pluck('token')->all());
        $this->assertSame($n->title, $pushes[0]['title']);
        $this->assertSame('order', $pushes[0]['data']['screen']);
        $this->assertSame((string) $order->id, $pushes[0]['data']['id']);
        $this->assertSame((string) $n->id, $pushes[0]['data']['notification_id']);
        $this->assertSame('order_status_changed', $pushes[0]['data']['type']);

        $this->assertSame('sent', $n->push_status);
        $this->assertNotNull($n->pushed_at);
        $this->assertSame('orders', $n->group);
        $this->assertSame(['screen' => 'order', 'id' => $order->id], $n->target);
    }

    public function test_without_devices_status_is_no_device(): void
    {
        $this->fakeFcm();
        $customer = $this->customer();

        $this->changeStatus($this->order($customer));

        $this->assertSame('no_device', $this->lastNotification($customer->id)->push_status);
        $this->assertCount(0, $this->sentPushes());
    }

    public function test_without_credentials_nothing_is_sent_and_bell_still_works(): void
    {
        $this->noFcm();
        $customer = $this->customer();
        $this->device($customer);

        $this->changeStatus($this->order($customer))->assertSessionHas('success');

        $this->assertSame('not_configured', $this->lastNotification($customer->id)->push_status);
        Http::assertNothingSent();
    }

    public function test_unregistered_token_is_removed_and_operation_still_succeeds(): void
    {
        $this->fakeFcm([Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
        $customer = $this->customer();
        $this->device($customer, 'dead-token');
        $order = $this->order($customer);

        $this->changeStatus($order)->assertSessionHas('success');

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseMissing('push_devices', ['token' => 'dead-token']);
        $this->assertSame('failed', $this->lastNotification($customer->id)->push_status);
    }

    public function test_firebase_down_does_not_break_the_operation(): void
    {
        $this->fakeFcm([Http::response(['error' => ['status' => 'INTERNAL']], 500)]);
        $customer = $this->customer();
        $this->device($customer, 'ok-token');
        $order = $this->order($customer);

        $this->changeStatus($order)->assertSessionHas('success');

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('failed', $this->lastNotification($customer->id)->push_status);
        $this->assertDatabaseHas('push_devices', ['token' => 'ok-token']); // خطأ مؤقت: الجهاز يبقى

        // مهلة (استثناء اتصال) كذلك لا تكسر شيئاً
        Http::fake(['fcm.googleapis.com/*' => fn () => throw new ConnectionException('timeout')]);
        $this->changeStatus($order, 'shipped')->assertSessionHas('success');
        $this->assertSame('failed', $this->lastNotification($customer->id)->push_status);
    }

    public function test_same_notification_is_never_pushed_twice(): void
    {
        $this->fakeFcm();
        $customer = $this->customer();
        $this->device($customer);

        $n = AppNotification::send($customer->id, 'order_status_changed', 'تحديث', 'نص', route('customer.orders.show', $this->order($customer)));
        $this->runAfterResponse();
        app(PushSender::class)->send($n->id);
        $this->runAfterResponse();

        $this->assertCount(1, $this->sentPushes());
    }

    public function test_api_list_returns_group_and_target(): void
    {
        $this->noFcm();
        $customer = $this->customer();
        $order = $this->order($customer);
        AppNotification::send($customer->id, 'order_status_changed', 'تحديث', null, route('customer.orders.show', $order));
        AppNotification::send($customer->id, 'something_new', 'عام', null, null);

        Sanctum::actingAs($customer);
        $items = collect($this->getJson('/api/v1/notifications')->assertOk()->json('data'))->keyBy('type');

        $this->assertSame('general', $items['something_new']['group']);
        $this->assertNull($items['something_new']['target']);
        $this->assertSame('orders', $items['order_status_changed']['group']);
        $this->assertSame(['screen' => 'order', 'id' => $order->id], $items['order_status_changed']['target']);
    }
}
