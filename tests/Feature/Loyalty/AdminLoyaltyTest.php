<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US5: إدارة البرنامج — الملخص، التعديل اليدوي بسبب، والإعدادات */
class AdminLoyaltyTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_summary_shows_outstanding_points_and_their_value(): void
    {
        $this->grant($this->customer(), 30);
        $this->grant($this->customer(), 20);

        $this->actingAs($this->admin())->get(route('admin.loyalty.index'))->assertOk()
            ->assertSee('50')->assertSee('10,000 ل.س', false);   // 50 × 200
    }

    public function test_admin_adds_points_with_reason_and_name_is_recorded(): void
    {
        $admin = $this->admin();
        $c = $this->customer();

        $this->actingAs($admin)->post(route('admin.loyalty.adjust', $c), ['direction' => 'add', 'points' => 10, 'reason' => 'تعويض تأخير'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $tx = Tx::where('user_id', $c->id)->where('type', Tx::ADMIN_ADD)->firstOrFail();
        $this->assertSame($admin->id, $tx->admin_id);
        $this->assertSame('تعويض تأخير', $tx->reason);
        $this->assertSame(10, $this->balance($c));

        $this->get(route('admin.loyalty.show', $c))->assertOk()->assertSee('تعويض تأخير')->assertSee($admin->first_name);
    }

    public function test_reason_is_required_and_deduct_cannot_exceed_balance(): void
    {
        $c = $this->customer();
        $this->grant($c, 5);
        $this->actingAs($this->admin());

        $this->post(route('admin.loyalty.adjust', $c), ['direction' => 'add', 'points' => 10, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.loyalty.adjust', $c), ['direction' => 'deduct', 'points' => 6, 'reason' => 'تصحيح'])->assertSessionHasErrors('points');
        $this->assertSame(5, $this->balance($c));
    }

    public function test_settings_change_the_rules(): void
    {
        $this->actingAs($this->admin())->put(route('admin.loyalty.settings'), [
            'loyalty_enabled' => '1', 'loyalty_spend_per_point' => 5000, 'loyalty_point_value' => 100,
            'loyalty_min_redeem' => 5, 'loyalty_expiry_months' => 6, 'loyalty_coupon_valid_days' => 15,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = PlatformSetting::current()->fresh();
        $this->assertSame(500_000, (int) $s->loyalty_spend_per_point);   // تُدخل بالليرة وتُخزَّن ×100
        $this->assertSame(10_000, (int) $s->loyalty_point_value);

        app()->forgetInstance(\App\Loyalty\LoyaltySettings::class);
        $u = $this->customer();
        $this->deliver($this->order($u, 2_000_000));   // 20,000 / 5,000 = 4
        $this->assertSame(4, $this->balance($u));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->customer())->get(route('admin.loyalty.index'))->assertForbidden();
    }
}
