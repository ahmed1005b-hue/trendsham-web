<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** خريطة الموقع: الصفحات العامة فقط، بصيغة XML صحيحة. */
class SitemapTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_only(): void
    {
        Cache::forget(SitemapController::CACHE_KEY);
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $b['product']->update(['status' => 'inactive']);
        $b['store']->update(['status' => 'pending']);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'sitemap is valid XML');

        $locs = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        $this->assertContains(route('home'), $locs);
        $this->assertContains(route('products.show', $a['product']), $locs);
        $this->assertContains(route('stores.show', $a['store']), $locs);
        $this->assertNotContains(route('products.show', $b['product']), $locs);
        $this->assertNotContains(route('stores.show', $b['store']), $locs);
        $this->assertEmpty(array_filter($locs, fn ($loc) => str_contains($loc, '/admin') || str_contains($loc, '/customer')));
    }

    public function test_robots_points_to_the_sitemap_and_hides_private_areas(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'), false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /customer', false);
    }
}
