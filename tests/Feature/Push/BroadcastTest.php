<?php

namespace Tests\Feature\Push;

use App\Models\AppNotification;
use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    private const FORM = ['title' => 'تخفيضات الجمعة', 'body' => 'خصومات حتى 50% على متاجر مختارة', 'link' => '/store/1', 'confirm' => '1'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'Asia/Damascus'));
    }

    private function optedIn(): User
    {
        $u = $this->customer();
        $u->forceFill(['notification_prefs' => ['offers' => true], 'offers_prompted_at' => now()])->save();

        return $u;
    }

    public function test_admin_sees_the_page_with_counts_and_preview(): void
    {
        $this->noFcm();
        $this->customer();
        $this->device($this->optedIn());

        $this->actingAs($this->admin())->get(route('admin.push-broadcasts.index'))->assertOk()
            ->assertSee('إشعار جماعي')
            ->assertSee('data-total-customers="2"', false)
            ->assertSee('data-reachable="1"', false)
            ->assertSee('id="broadcastPreview"', false)
            ->assertDontSee('@php')
            ->assertDontSee('@endif');
    }

    public function test_confirmation_is_required(): void
    {
        $this->noFcm();
        $this->actingAs($this->admin())->post(route('admin.push-broadcasts.store'), ['confirm' => null] + self::FORM)
            ->assertSessionHasErrors('confirm');
        $this->assertSame(0, PushBroadcast::count());
    }

    public function test_send_reaches_every_customer_bell_and_only_opted_in_phones(): void
    {
        $this->fakeFcm();
        $this->at('2026-11-12 12:00');
        $on = $this->optedIn();
        $this->device($on, 'on-phone');
        $off = $this->customer();
        $this->device($off, 'off-phone');
        $quiet = $this->optedIn(); // بلا هاتف
        $seller = $this->seller();
        $this->device($seller->user, 'seller-phone');

        $this->actingAs($this->admin())->post(route('admin.push-broadcasts.store'), self::FORM)
            ->assertRedirect()->assertSessionHas('success');

        $b = PushBroadcast::firstOrFail();
        $this->assertNotNull($b->sent_at);
        $this->assertSame(3, $b->recipients_count);
        $this->assertSame(1, $b->pushed_count);

        $rows = AppNotification::where('broadcast_id', $b->id)->get()->keyBy('user_id');
        $this->assertCount(3, $rows);
        $this->assertFalse($rows->has($seller->user_id));
        $this->assertSame('sent', $rows[$on->id]->push_status);
        $this->assertSame('skipped_disabled', $rows[$off->id]->push_status);
        $this->assertSame('no_device', $rows[$quiet->id]->push_status);
        $this->assertSame('offers', $rows[$on->id]->group);
        $this->assertSame('broadcast', $rows[$on->id]->type);
        $this->assertSame(['screen' => 'store', 'id' => 1], $rows[$on->id]->target);

        $pushes = $this->sentPushes();
        $this->assertSame(['on-phone'], $pushes->pluck('token')->all());
        $this->assertSame('تخفيضات الجمعة', $pushes[0]['title']);
        $this->assertSame((string) $rows[$on->id]->id, $pushes[0]['data']['notification_id']);
    }

    public function test_second_broadcast_within_seven_days_is_rejected(): void
    {
        $this->noFcm();
        $this->at('2026-11-12 12:00');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), self::FORM)->assertSessionHas('success');

        $this->at('2026-11-18 12:00');
        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), self::FORM)->assertSessionHasErrors('title');
        $this->assertSame(1, PushBroadcast::count());

        $this->at('2026-11-19 12:01');
        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), self::FORM)->assertSessionHas('success');
        $this->assertSame(2, PushBroadcast::count());
    }

    public function test_night_broadcast_is_scheduled_for_nine_and_sent_once_by_the_command(): void
    {
        $this->fakeFcm();
        $this->at('2026-11-12 23:30');
        $u = $this->optedIn();
        $this->device($u, 'phone');

        $this->actingAs($this->admin())->post(route('admin.push-broadcasts.store'), self::FORM)->assertSessionHas('success');
        $b = PushBroadcast::firstOrFail();
        $this->assertSame('2026-11-13 09:00', $b->scheduled_for->timezone('Asia/Damascus')->format('Y-m-d H:i'));
        $this->assertNull($b->sent_at);
        $this->assertSame(0, AppNotification::where('broadcast_id', $b->id)->count());
        $this->assertCount(0, $this->sentPushes());

        $this->at('2026-11-13 09:01');
        $this->artisan('notifications:send-broadcasts')->assertSuccessful();
        $this->artisan('notifications:send-broadcasts')->assertSuccessful();

        $this->assertNotNull($b->fresh()->sent_at);
        $this->assertSame(1, AppNotification::where('broadcast_id', $b->id)->count());
        $this->assertCount(1, $this->sentPushes());
    }

    public function test_validation(): void
    {
        $this->noFcm();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), ['title' => str_repeat('ا', 61)] + self::FORM)
            ->assertSessionHasErrors('title');
        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), ['body' => str_repeat('ا', 181)] + self::FORM)
            ->assertSessionHasErrors('body');
        $this->actingAs($admin)->post(route('admin.push-broadcasts.store'), ['link' => 'https://evil.example/x'] + self::FORM)
            ->assertSessionHasErrors('link');
        $this->assertSame(0, PushBroadcast::count());
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->noFcm();
        $this->actingAs($this->customer())->get(route('admin.push-broadcasts.index'))->assertForbidden();
        $this->actingAs($this->seller()->user)->post(route('admin.push-broadcasts.store'), self::FORM)->assertForbidden();
    }
}
