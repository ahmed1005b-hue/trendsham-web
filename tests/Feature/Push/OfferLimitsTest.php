<?php

namespace Tests\Feature\Push;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfferLimitsTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-11-12 $time", 'Asia/Damascus'));
    }

    private function offersOn(): User
    {
        $u = $this->customer();
        $u->forceFill(['notification_prefs' => ['offers' => true], 'offers_prompted_at' => now()])->save();
        $this->device($u);

        return $u;
    }

    private function offer(User $u, string $type = 'store_new_offer'): AppNotification
    {
        $n = AppNotification::send($u->id, $type, 'عرض جديد', null, '/store/1');
        $this->runAfterResponse();

        return $n->fresh();
    }

    public function test_customer_not_asked_yet_gets_no_offer_push(): void
    {
        $this->fakeFcm();
        $this->at('12:00');
        $u = $this->customer();
        $this->device($u);

        $this->assertSame('skipped_disabled', $this->offer($u)->push_status);
        $this->assertCount(0, $this->sentPushes());
    }

    public function test_first_follow_asks_about_offers_once_api(): void
    {
        $u = $this->customer();
        Sanctum::actingAs($u);
        $a = $this->seller();
        $b = $this->seller();

        $this->postJson("/api/v1/stores/{$a->id}/follow")->assertOk()->assertJsonPath('ask_offers_opt_in', true);
        $this->postJson("/api/v1/stores/{$b->id}/follow")->assertOk()->assertJsonPath('ask_offers_opt_in', false);
        $this->assertNotNull($u->fresh()->offers_prompted_at);
    }

    public function test_first_follow_asks_about_offers_once_web(): void
    {
        $u = $this->customer();
        $a = $this->seller();
        $b = $this->seller();

        $this->actingAs($u)->post(route('stores.follow', $a))->assertSessionHas('ask_offers_opt_in', true);
        $this->actingAs($u)->post(route('stores.follow', $b))->assertSessionMissing('ask_offers_opt_in');

        // الجواب من الموقع يُحفظ
        $this->actingAs($u)->post(route('customer.notification-settings.offers'), ['offers' => 1])->assertRedirect();
        $this->assertSame(['offers' => true], $u->fresh()->notification_prefs);

        $this->actingAs($u)->post(route('customer.notification-settings.offers'), ['offers' => 0]);
        $this->assertSame(['offers' => false], $u->fresh()->notification_prefs);
    }

    public function test_customer_who_already_chose_is_not_asked(): void
    {
        $u = $this->customer();
        $u->forceFill(['notification_prefs' => ['offers' => false]])->save();
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/stores/{$this->seller()->id}/follow")->assertJsonPath('ask_offers_opt_in', false);
    }

    public function test_put_offers_sets_prompted_at(): void
    {
        $u = $this->customer();
        Sanctum::actingAs($u);

        $this->putJson('/api/v1/notification-settings', ['groups' => ['offers' => true]])->assertOk();
        $this->assertNotNull($u->fresh()->offers_prompted_at);
    }

    public function test_at_most_three_offer_pushes_a_day(): void
    {
        $this->fakeFcm();
        $this->at('10:00');
        $u = $this->offersOn();

        $statuses = collect([
            $this->offer($u), $this->offer($u, 'store_new_product'), $this->offer($u), $this->offer($u),
        ])->pluck('push_status')->all();

        $this->assertSame(['sent', 'sent', 'sent', 'skipped_limit'], $statuses);
        $this->assertCount(3, $this->sentPushes());

        // يوم جديد ← يبدأ العدّ من جديد
        $this->at('10:00');
        Carbon::setTestNow(now()->addDay());
        $this->assertSame('sent', $this->offer($u)->push_status);
    }

    public function test_no_offers_at_night_but_orders_still_go(): void
    {
        $this->fakeFcm();
        $u = $this->offersOn();

        $this->at('23:00');
        $this->assertSame('skipped_quiet', $this->offer($u)->push_status);

        $this->at('08:59');
        $this->assertSame('skipped_quiet', $this->offer($u)->push_status);

        $this->at('09:00');
        $this->assertSame('sent', $this->offer($u)->push_status);

        $this->at('23:00');
        $order = AppNotification::send($u->id, 'order_status_changed', 'تحديث طلب', null, null);
        $this->runAfterResponse();
        $this->assertSame('sent', $order->fresh()->push_status);
    }
}
