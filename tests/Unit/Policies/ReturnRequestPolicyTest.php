<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * سياسة طلب الإرجاع: المالك مسموح، ومستأجر آخر مرفوض 403 بلا رسالة.
 */
class ReturnRequestPolicyTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** [ability, owner-side actor key] */
    public static function abilities(): array
    {
        return [
            'view' => ['view', 'customer'],
            'withdraw' => ['withdraw', 'customer'],
            'dispute' => ['dispute', 'customer'],
            'viewAsSeller' => ['viewAsSeller', 'seller'],
            'decide' => ['decide', 'seller'],
            'confirmReceived' => ['confirmReceived', 'seller'],
        ];
    }

    #[DataProvider('abilities')]
    public function test_owner_allowed_and_other_tenant_denied(string $ability, string $actor): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        $allowed = Gate::forUser($a[$actor])->inspect($ability, $a['returnRequest']);
        $this->assertTrue($allowed->allowed(), "$ability: owner must be allowed");

        $denied = Gate::forUser($b[$actor])->inspect($ability, $a['returnRequest']);
        $this->assertTrue($denied->denied(), "$ability: other tenant must be denied");
        $this->assertNull($denied->status(), "$ability: denial is a plain 403");
        $this->assertSame('', (string) $denied->message(), "$ability: denial message");
    }

    public function test_customer_abilities_have_no_admin_or_seller_bypass(): void
    {
        $a = $this->tenant('a');
        $admin = $this->admin();

        foreach (['view', 'withdraw', 'dispute'] as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability, $a['returnRequest']), "admin $ability");
            $this->assertFalse(Gate::forUser($a['seller'])->allows($ability, $a['returnRequest']), "seller $ability");
        }
    }

    public function test_decide_is_seller_owner_only_but_confirm_received_also_allows_admin(): void
    {
        $a = $this->tenant('a');
        $admin = $this->admin();

        $this->assertFalse(Gate::forUser($admin)->allows('decide', $a['returnRequest']));
        $this->assertFalse(Gate::forUser($a['customer'])->allows('decide', $a['returnRequest']));
        $this->assertFalse(Gate::forUser($a['customer'])->allows('confirmReceived', $a['returnRequest']));
        $this->assertTrue(Gate::forUser($admin)->allows('confirmReceived', $a['returnRequest']));
    }

    public function test_admin_abilities_are_admin_only(): void
    {
        $a = $this->tenant('a');
        $admin = $this->admin();

        foreach (['adminDecide', 'recordRefund'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $a['returnRequest']), "admin $ability");
            foreach (['customer', 'seller'] as $actor) {
                $denied = Gate::forUser($a[$actor])->inspect($ability, $a['returnRequest']);
                $this->assertTrue($denied->denied(), "$actor $ability");
                $this->assertNull($denied->status());
                $this->assertSame('', (string) $denied->message());
            }
        }
    }

    private function admin(): User
    {
        return User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'phone' => '0999000333',
            'email' => 'admin-returns@policy.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active',
        ]);
    }
}
