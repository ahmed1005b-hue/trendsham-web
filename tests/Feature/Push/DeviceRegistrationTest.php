<?php

namespace Tests\Feature\Push;

use App\Models\PushDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    public function test_logged_in_user_registers_a_device(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/push/devices', ['token' => 'fcm-abc', 'platform' => 'android', 'app_version' => '1.4.0'])
            ->assertOk()->assertExactJson(['registered' => true]);

        $device = PushDevice::where('token', 'fcm-abc')->first();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('1.4.0', $device->app_version);
        $this->assertNotNull($device->last_seen_at);

        // التسجيل مرة ثانية لا يكرر الصف
        $this->postJson('/api/v1/push/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertOk();
        $this->assertSame(1, PushDevice::where('token', 'fcm-abc')->count());
    }

    public function test_validation(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/push/devices', ['platform' => 'android'])->assertStatus(422)->assertJsonValidationErrors('token');
        $this->postJson('/api/v1/push/devices', ['token' => 'x', 'platform' => 'windows'])->assertStatus(422)->assertJsonValidationErrors('platform');
    }

    public function test_same_token_moves_to_the_account_that_logged_in_last(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        $this->device($first, 'shared-phone');

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/push/devices', ['token' => 'shared-phone', 'platform' => 'android'])->assertOk();

        $this->assertSame(0, $first->pushDevices()->count());
        $this->assertSame(1, $second->pushDevices()->count());
    }

    public function test_delete_removes_only_own_device(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $this->device($owner, 'mine');

        Sanctum::actingAs($other);
        $this->deleteJson('/api/v1/push/devices', ['token' => 'mine'])->assertNoContent();
        $this->assertDatabaseHas('push_devices', ['token' => 'mine']);

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/push/devices', ['token' => 'mine'])->assertNoContent();
        $this->assertDatabaseMissing('push_devices', ['token' => 'mine']);

        // غير موجود ← 204 أيضاً
        $this->deleteJson('/api/v1/push/devices', ['token' => 'mine'])->assertNoContent();
    }

    public function test_guest_is_rejected(): void
    {
        $this->postJson('/api/v1/push/devices', ['token' => 'x', 'platform' => 'android'])->assertUnauthorized();
        $this->deleteJson('/api/v1/push/devices', ['token' => 'x'])->assertUnauthorized();
    }
}
