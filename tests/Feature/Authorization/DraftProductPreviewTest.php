<?php

namespace Tests\Feature\Authorization;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * معاينة منتج غير منشور (لم تعتمده الإدارة): لا يراه إلا صاحبه، لا بائع آخر.
 */
class DraftProductPreviewTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function draftProductOf(array $tenant): Product
    {
        $tenant['product']->update(['admin_approved_at' => null, 'status' => 'draft']);

        return $tenant['product']->fresh();
    }

    public function test_another_seller_cannot_preview_a_draft_product_on_the_web(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $draft = $this->draftProductOf($b);
        $uri = route('products.show', $draft, false);

        $this->actingAs($a['seller'])->get($uri)->assertNotFound();
        $this->actingAs($a['customer'])->get($uri)->assertNotFound();

        $this->actingAs($b['seller'])->get($uri)->assertOk()->assertSee('Product b');
    }

    public function test_another_seller_cannot_preview_a_draft_product_through_the_api(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $draft = $this->draftProductOf($b);
        $uri = '/api/v1/products/'.$draft->getRouteKey();

        $this->actingAs($a['seller'], 'sanctum')->getJson($uri)->assertNotFound();
        $this->actingAs($a['customer'], 'sanctum')->getJson($uri)->assertNotFound();

        $this->actingAs($b['seller'], 'sanctum')->getJson($uri)->assertOk();
    }
}
