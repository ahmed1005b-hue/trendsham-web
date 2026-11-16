<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** "موقعي الحالي": اقتراح المدينة والحي من الإحداثيات، والفشل لا يكسر شيئاً. */
class ReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNominatim(array $address, int $status = 200): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => $address], $status)]);
    }

    public function test_api_suggests_city_and_area(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->fakeNominatim([
            'city' => 'دمشق', 'suburb' => 'المزة', 'neighbourhood' => 'فيلات غربية',
            'road' => 'شارع الجلاء', 'country' => 'سوريا',
        ]);

        $this->getJson('/api/v1/profile/addresses/reverse-geocode?latitude=33.5&longitude=36.25')
            ->assertOk()
            ->assertJsonPath('data.city', 'دمشق')
            ->assertJsonPath('data.details', 'فيلات غربية، المزة، شارع الجلاء');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'lat=33.5')
            && $r->hasHeader('Accept-Language', 'ar')
            && $r->hasHeader('User-Agent'));
    }

    public function test_syrian_admin_prefixes_are_cleaned(): void
    {
        // استجابة حقيقية لساحة الأمويين: لا يوجد مفتاح city
        $this->assertSame(['city' => 'دمشق', 'details' => 'حي الربوة، المزة، نفق الأمويين'], ReverseGeocoder::fromAddress([
            'road' => 'نفق الأمويين', 'suburb' => 'حي الربوة', 'town' => 'بلدية المزة',
            'county' => 'ناحية دمشق', 'state_district' => 'منطقة دمشق', 'state' => 'محافظة دمشق',
        ]));
        $this->assertSame('بلودان', ReverseGeocoder::fromAddress(['village' => 'بلودان', 'state' => 'محافظة ريف دمشق'])['city']);
        $this->assertNull(ReverseGeocoder::fromAddress(['country' => 'سوريا']));
    }

    public function test_results_are_cached_but_failures_are_not(): void
    {
        Cache::flush();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([], 503)
            ->push(['address' => ['town' => 'جرمانا']])]);
        $geo = app(ReverseGeocoder::class);
        $this->assertNull($geo->lookup(33.5, 36.25));

        $this->assertSame(['city' => 'جرمانا', 'details' => ''], $geo->lookup(33.5, 36.25));
        $this->assertSame(['city' => 'جرمانا', 'details' => ''], $geo->lookup(33.50001, 36.25001));
        Http::assertSentCount(2); // الثالث من الكاش
    }

    public function test_network_error_returns_null_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->getJson('/api/v1/profile/addresses/reverse-geocode?latitude=1&longitude=2')
            ->assertOk()->assertJsonPath('data', null);
    }

    public function test_validation_and_auth(): void
    {
        $this->getJson('/api/v1/profile/addresses/reverse-geocode?latitude=1&longitude=2')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/v1/profile/addresses/reverse-geocode?latitude=95&longitude=2')
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_web_route_works_for_logged_in_users(): void
    {
        $this->fakeNominatim(['village' => 'بلودان', 'road' => 'الطريق العام']);

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->getJson(route('profile.addresses.reverse-geocode', ['latitude' => 33.7, 'longitude' => 36.1]))
            ->assertOk()
            ->assertJsonPath('data', ['city' => 'بلودان', 'details' => 'الطريق العام']);
    }

    public function test_profile_page_points_the_button_at_the_endpoint(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-geocode-url=', false)
            ->assertSee('reverse-geocode', false);
    }
}
