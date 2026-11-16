<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * سياسات الموارد المملوكة للزبون: المالك مسموح، ومستأجر آخر مرفوض بنفس حالة الرفض الحالية.
 */
class CustomerPoliciesTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** [ability, owner-side actor key, tenant model key, denial status] */
    public static function abilities(): array
    {
        return [
            'order view' => ['view', 'customer', 'order', 403],
            'order cancel' => ['cancel', 'customer', 'order', 403],
            'order viewAsSeller' => ['viewAsSeller', 'seller', 'order', 403],
            'order invoice' => ['invoice', 'customer', 'order', 403],
            'order item review' => ['review', 'customer', 'orderItem', 403],
            'address manage' => ['manage', 'customer', 'address', 403],
            'card manage' => ['manage', 'customer', 'card', 403],
            'cart item manage' => ['manage', 'customer', 'cartItem', 403],
            'notification markRead' => ['markRead', 'customer', 'notification', 403],
            'conversation participate (customer)' => ['participate', 'customer', 'conversation', 403],
            'conversation participate (store owner)' => ['participate', 'seller', 'conversation', 403],
            'conversation asCustomer' => ['asCustomer', 'customer', 'conversation', 403],
            'conversation asSeller' => ['asSeller', 'seller', 'conversation', 403],
            'question delete' => ['delete', 'customer', 'question', 403],
            'question deleteAsAskerOrAdmin' => ['deleteAsAskerOrAdmin', 'customer', 'question', 403],
            'question answerAsSeller' => ['answerAsSeller', 'seller', 'question', 403],
        ];
    }

    /** @dataProvider abilities */
    #[DataProvider('abilities')]
    public function test_owner_allowed_and_other_tenant_denied(string $ability, string $actor, string $key, int $status): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        $allowed = Gate::forUser($a[$actor])->inspect($ability, $a[$key]);
        $this->assertTrue($allowed->allowed(), "$ability: owner must be allowed");

        $denied = Gate::forUser($b[$actor])->inspect($ability, $a[$key]);
        $this->assertTrue($denied->denied(), "$ability: other tenant must be denied");
        // Response::deny() بلا رمز = 403 عبر AccessDeniedHttpException؛ denyAsNotFound() يعطي 404 صريحاً
        $this->assertSame($status === 403 ? null : $status, $denied->status(), "$ability: denial status");
        // نفس رسالة abort(403) الحالية: نص فارغ (لا «This action is unauthorized.»)
        $this->assertSame('', (string) $denied->message(), "$ability: denial message");
    }

    public function test_customer_is_not_a_store_side_participant(): void
    {
        $a = $this->tenant('a');
        $this->assertFalse(Gate::forUser($a['customer'])->allows('viewAsSeller', $a['order']));
        $this->assertFalse(Gate::forUser($a['customer'])->allows('answerAsSeller', $a['question']));
        $this->assertFalse(Gate::forUser($a['seller'])->allows('asCustomer', $a['conversation']));
        $this->assertFalse(Gate::forUser($a['seller'])->allows('view', $a['order']));
    }

    public function test_invoice_is_owner_or_admin_only_and_sellers_cannot_view(): void
    {
        $a = $this->tenant('a');
        $admin = User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'phone' => '0999000111',
            'email' => 'admin@policy.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('invoice', $a['order']));
        // البائع الذي له عنصر في الطلب لا يرى الفاتورة (السلوك الحالي لـ InvoiceController)
        $this->assertFalse(Gate::forUser($a['seller'])->allows('invoice', $a['order']));
    }

    public function test_question_asker_or_admin_may_delete_through_the_api_ability(): void
    {
        $a = $this->tenant('a');
        $admin = User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'phone' => '0999000112',
            'email' => 'admin2@policy.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('deleteAsAskerOrAdmin', $a['question']));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $a['question']));
    }
}
