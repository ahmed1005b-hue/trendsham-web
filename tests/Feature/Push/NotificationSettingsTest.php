<?php

namespace Tests\Feature\Push;

use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    public function test_customer_sees_their_groups_with_offers_undecided(): void
    {
        Sanctum::actingAs($this->customer());

        $res = $this->getJson('/api/v1/notification-settings')->assertOk()
            ->assertJsonPath('role', 'customer')
            ->assertJsonPath('offers_prompted', false)
            ->assertJsonPath('always_on_note', 'إشعارات الأمان والمال المهمة تصلك دائماً.');

        $groups = collect($res->json('groups'))->keyBy('key');
        $this->assertSame(['orders', 'messages', 'coupons_points', 'favorites', 'offers'], $groups->keys()->all());
        $this->assertTrue($groups['orders']['enabled']);
        $this->assertNull($groups['offers']['enabled']);
        $this->assertFalse($groups['orders']['locked']);
        $this->assertSame('الطلبات والدفع والشحن', $groups['orders']['label']);
        $this->assertNotEmpty($groups['orders']['description']);
    }

    public function test_seller_sees_seller_groups_all_enabled(): void
    {
        Sanctum::actingAs($this->seller()->user);

        $groups = collect($this->getJson('/api/v1/notification-settings')->assertOk()->assertJsonPath('role', 'seller')->json('groups'));
        $this->assertSame(['seller_orders', 'seller_messages', 'seller_store', 'seller_payouts', 'seller_coupons'], $groups->pluck('key')->all());
        $this->assertTrue($groups->every(fn ($g) => $g['enabled'] === true));
    }

    public function test_update_saves_for_the_account_and_rejects_other_role_keys(): void
    {
        $u = $this->customer();
        Sanctum::actingAs($u);

        $this->putJson('/api/v1/notification-settings', ['groups' => ['orders' => false, 'offers' => true]])->assertOk()
            ->assertJsonPath('groups.0.enabled', false)
            ->assertJsonPath('groups.3.enabled', true)
            ->assertJsonPath('offers_prompted', true);

        $this->assertSame(['orders' => false, 'offers' => true], $u->fresh()->notification_prefs);
        $this->assertNotNull($u->fresh()->offers_prompted_at);

        $this->putJson('/api/v1/notification-settings', ['groups' => ['seller_orders' => false]])->assertStatus(422);
        $this->putJson('/api/v1/notification-settings', ['groups' => ['orders' => 'maybe']])->assertStatus(422);

        // نفس الحساب من هاتف آخر يرى الإعداد نفسه (محفوظ في الحساب لا في الجهاز)
        Sanctum::actingAs($u->fresh());
        $this->getJson('/api/v1/notification-settings')->assertJsonPath('groups.0.enabled', false);
    }

    public function test_disabled_group_is_not_pushed_but_stays_in_the_bell(): void
    {
        $this->fakeFcm();
        $u = $this->customer(['notification_prefs' => ['orders' => false]]);
        $this->device($u);

        $n = AppNotification::send($u->id, 'order_status_changed', 'تحديث', null, null);
        $this->runAfterResponse();

        $this->assertSame('skipped_disabled', $n->fresh()->push_status);
        $this->assertCount(0, $this->sentPushes());
        $this->assertDatabaseHas('notifications', ['id' => $n->id, 'read_at' => null]);

        // مجموعة أخرى مفعّلة تُرسل
        AppNotification::send($u->id, 'new_message', 'رسالة', null, null);
        $this->runAfterResponse();
        $this->assertCount(1, $this->sentPushes());
    }

    public function test_critical_notifications_are_sent_even_when_everything_is_off(): void
    {
        $this->fakeFcm();
        $seller = $this->seller();
        $seller->user->forceFill(['notification_prefs' => array_fill_keys(
            ['seller_orders', 'seller_messages', 'seller_store', 'seller_payouts', 'seller_coupons'], false,
        )])->save();
        $this->device($seller->user);

        $a = AppNotification::send($seller->user_id, 'store_suspended', 'تم إيقاف متجرك', null, null);
        $b = AppNotification::send($seller->user_id, 'withdrawal_failed', 'فشل التحويل', null, null);
        $c = AppNotification::send($seller->user_id, 'withdrawal_paid', 'تم التحويل', null, null);
        $this->runAfterResponse();

        $this->assertSame('sent', $a->fresh()->push_status);
        $this->assertSame('sent', $b->fresh()->push_status);
        $this->assertSame('skipped_disabled', $c->fresh()->push_status);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/notification-settings')->assertUnauthorized();
    }
}
