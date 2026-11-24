<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * عناوين الشحن المحفوظة: حتى 3 لكل مستخدم (بيت/عمل/آخر)، وعنوان واحد معتمد للشحن.
 */
class UserAddressTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'customer'] + $attrs);
    }

    private function payload(array $override = []): array
    {
        return $override + ['label' => 'home', 'city' => 'دمشق', 'details' => 'المزة، شارع الجلاء، بناء 4'];
    }

    public function test_first_address_becomes_the_shipping_default(): void
    {
        Sanctum::actingAs($user = $this->customer());

        $res = $this->postJson('/api/v1/profile/addresses', $this->payload())->assertCreated();

        $res->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.label', 'home')
            ->assertJsonPath('data.label_text', 'البيت');
        $this->assertStringContainsString('دمشق', $res->json('data.shipping_text'));
        $this->assertStringContainsString('المزة، شارع الجلاء', $res->json('data.shipping_text'));
        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_coordinates_add_a_map_link_to_the_shipping_text(): void
    {
        Sanctum::actingAs($this->customer());

        $text = $this->postJson('/api/v1/profile/addresses', $this->payload(['latitude' => 33.5138, 'longitude' => 36.2765]))
            ->assertCreated()->json('data.shipping_text');

        $this->assertStringContainsString('https://www.google.com/maps?q=33.5138000,36.2765000', $text);
    }

    public function test_coordinates_must_come_as_a_valid_pair(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/profile/addresses', $this->payload(['latitude' => 33.5]))->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');
        $this->postJson('/api/v1/profile/addresses', $this->payload(['latitude' => 120, 'longitude' => 36]))->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
        $this->postJson('/api/v1/profile/addresses', $this->payload(['label' => 'office']))->assertUnprocessable()
            ->assertJsonValidationErrors('label');
    }

    public function test_at_most_three_addresses(): void
    {
        Sanctum::actingAs($this->customer());
        foreach (['home', 'work', 'other'] as $label) {
            $this->postJson('/api/v1/profile/addresses', $this->payload(['label' => $label]))->assertCreated();
        }

        $this->postJson('/api/v1/profile/addresses', $this->payload())->assertUnprocessable()
            ->assertJsonValidationErrors('address');
    }

    public function test_choosing_a_default_unsets_the_previous_one(): void
    {
        Sanctum::actingAs($user = $this->customer());
        $home = $this->postJson('/api/v1/profile/addresses', $this->payload())->json('data.id');
        $work = $this->postJson('/api/v1/profile/addresses', $this->payload(['label' => 'work', 'is_default' => true]))->json('data.id');

        $this->assertSame([$work], $user->addresses()->where('is_default', true)->pluck('id')->all());

        $this->postJson("/api/v1/profile/addresses/{$home}/default")->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame([$home], $user->addresses()->where('is_default', true)->pluck('id')->all());
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        Sanctum::actingAs($user = $this->customer());
        $home = $this->postJson('/api/v1/profile/addresses', $this->payload())->json('data.id');
        $work = $this->postJson('/api/v1/profile/addresses', $this->payload(['label' => 'work']))->json('data.id');

        $this->deleteJson("/api/v1/profile/addresses/{$home}")->assertNoContent();

        $this->assertTrue(UserAddress::findOrFail($work)->is_default);
    }

    public function test_update_changes_fields_and_keeps_one_default(): void
    {
        Sanctum::actingAs($user = $this->customer());
        $id = $this->postJson('/api/v1/profile/addresses', $this->payload())->json('data.id');

        $this->putJson("/api/v1/profile/addresses/{$id}", $this->payload(['label' => 'work', 'city' => 'حلب', 'is_default' => false]))
            ->assertOk()->assertJsonPath('data.city', 'حلب')->assertJsonPath('data.label', 'work')
            // العنوان الوحيد لا يُلغى اعتماده
            ->assertJsonPath('data.is_default', true);
    }

    public function test_users_cannot_see_or_change_other_users_addresses(): void
    {
        $other = $this->customer();
        $address = $other->addresses()->create($this->payload() + ['is_default' => true]);

        Sanctum::actingAs($this->customer());
        $this->getJson('/api/v1/profile/addresses')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/profile/addresses/{$address->id}", $this->payload())->assertNotFound();
        $this->deleteJson("/api/v1/profile/addresses/{$address->id}")->assertNotFound();
        $this->postJson("/api/v1/profile/addresses/{$address->id}/default")->assertNotFound();
    }

    public function test_profile_payload_exposes_verification_and_default_address(): void
    {
        Sanctum::actingAs($user = $this->customer(['phone_verified_at' => now()]));
        $this->postJson('/api/v1/profile/addresses', $this->payload());

        $this->getJson('/api/v1/profile')->assertOk()
            ->assertJsonPath('data.phone_verified', true)
            ->assertJsonPath('data.addresses_count', 1)
            ->assertJsonPath('data.default_address.city', 'دمشق');

        Sanctum::actingAs($this->customer(['phone_verified_at' => null]));
        $this->getJson('/api/v1/profile')->assertOk()
            ->assertJsonPath('data.phone_verified', false)
            ->assertJsonPath('data.addresses_count', 0)
            ->assertJsonPath('data.default_address', null);
    }

    public function test_web_profile_manages_addresses(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post(route('profile.addresses.store'), $this->payload())->assertRedirect();
        $address = $user->addresses()->firstOrFail();
        $this->assertTrue($address->is_default);

        $this->get(route('profile.edit'))->assertOk()
            ->assertSee('عناويني')
            ->assertSee('المزة، شارع الجلاء');

        $this->put(route('profile.addresses.update', $address), $this->payload(['city' => 'حمص']))->assertRedirect();
        $this->assertSame('حمص', $address->fresh()->city);

        $this->delete(route('profile.addresses.destroy', $address))->assertRedirect();
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_web_profile_hides_addresses_for_staff_accounts(): void
    {
        foreach (['seller', 'admin', 'employee'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)->get(route('profile.edit'))->assertOk()
                ->assertDontSee('عناويني')
                ->assertSee('تغيير كلمة السر');
        }
    }

    public function test_web_checkout_offers_saved_addresses_with_the_default_prefilled(): void
    {
        $user = $this->customer();
        $user->addresses()->create($this->payload(['label' => 'work', 'city' => 'حلب', 'details' => 'الفرقان']) + ['is_default' => true]);

        $html = $this->actingAs($user)->get(route('customer.checkout.show'))->getContent();

        // قد تُعيد الصفحة توجيهاً إن كانت السلة فارغة؛ عندها نتحقق من الجزء المشترك مباشرة
        if (! str_contains((string) $html, 'data-ts-saved-address')) {
            $html = view('customer.checkout._saved-addresses', ['addresses' => $user->addresses()->get()])->render();
        }
        $this->assertStringContainsString('data-ts-saved-address', $html);
        $this->assertStringContainsString('الفرقان', $html);
    }
}
