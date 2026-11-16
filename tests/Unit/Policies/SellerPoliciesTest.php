<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * سياسات الموارد المملوكة للبائع: صاحب المتجر مسموح، ومتجر آخر مرفوض بنفس الحالة والرسالة الحاليتين.
 */
class SellerPoliciesTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private const DEAL_EDIT_MESSAGE = 'غير مصرح لك بالتعديل على هذا العرض.';

    /** [ability, tenant model key, denial status, denial message] */
    public static function abilities(): array
    {
        return [
            'product manage' => ['manage', 'product', 403, ''],
            'story manage' => ['manage', 'story', 403, ''],
            'payment method manage' => ['manage', 'paymentMethod', 403, ''],
            'showcase manage' => ['manage', 'showcase', 403, ''],
            'staff conversation manage' => ['manage', 'supportConversation', 403, ''],
            'deal manage' => ['manage', 'deal', 403, self::DEAL_EDIT_MESSAGE],
            'deal delete' => ['delete', 'deal', 403, 'غير مصرح لك بحذف هذا العرض.'],
            'deal toggle' => ['toggle', 'deal', 403, 'غير مصرح لك بتعديل هذا العرض.'],
            'coupon campaign manage' => ['manage', 'campaign', 404, ''],
        ];
    }

    /** @dataProvider abilities */
    #[DataProvider('abilities')]
    public function test_owner_allowed_and_other_store_denied(string $ability, string $key, int $status, string $message): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        $allowed = Gate::forUser($a['seller'])->inspect($ability, $a[$key]);
        $this->assertTrue($allowed->allowed(), "$ability: owner must be allowed");

        $denied = Gate::forUser($b['seller'])->inspect($ability, $a[$key]);
        $this->assertTrue($denied->denied(), "$ability: other store must be denied");
        $this->assertSame($status === 403 ? null : $status, $denied->status(), "$ability: denial status");
        $this->assertSame($message, (string) $denied->message(), "$ability: denial message");
    }

    public function test_customers_and_sellers_without_a_store_are_denied(): void
    {
        $a = $this->tenant('a');
        $noStore = User::create([
            'first_name' => 'No', 'last_name' => 'Store', 'phone' => '0999000201',
            'email' => 'nostore@policy.test', 'password' => 'password', 'role' => 'seller', 'status' => 'active',
        ]);

        foreach (['product', 'story', 'paymentMethod', 'showcase', 'supportConversation', 'deal', 'campaign'] as $key) {
            $this->assertFalse(Gate::forUser($a['customer'])->allows('manage', $a[$key]), "customer on $key");
            $this->assertFalse(Gate::forUser($noStore)->allows('manage', $a[$key]), "store-less seller on $key");
        }
    }

    public function test_campaign_must_be_funded_by_the_seller_and_belong_to_the_store(): void
    {
        $a = $this->tenant('a');
        $a['campaign']->forceFill(['funder' => 'platform'])->save();

        $denied = Gate::forUser($a['seller'])->inspect('manage', $a['campaign']);
        $this->assertTrue($denied->denied());
        $this->assertSame(404, $denied->status());
    }

    public function test_receipt_is_for_the_owning_seller_or_an_admin_only(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $admin = User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'phone' => '0999000202',
            'email' => 'admin@seller-policy.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active',
        ]);

        $this->assertTrue(Gate::forUser($a['seller'])->allows('viewReceipt', $a['withdrawal']));
        $this->assertTrue(Gate::forUser($admin)->allows('viewReceipt', $a['withdrawal']));

        $denied = Gate::forUser($b['seller'])->inspect('viewReceipt', $a['withdrawal']);
        $this->assertTrue($denied->denied());
        $this->assertNull($denied->status());
        $this->assertSame('', (string) $denied->message());
        $this->assertFalse(Gate::forUser($a['customer'])->allows('viewReceipt', $a['withdrawal']));
    }
}
