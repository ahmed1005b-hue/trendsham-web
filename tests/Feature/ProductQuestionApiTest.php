<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductQuestionApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerWithProduct(): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $user->id,
            'store_name' => 'متجر الاختبار',
            'status' => 'approved',
        ]);
        $product = Product::create([
            'vendor_id' => $profile->id,
            'category_id' => \App\Models\Category::create(['name' => 'قسم', 'slug' => 'cat-test'])->id,
            'name' => 'منتج اختبار',
            'slug' => 'test-product',
            'sku' => 'SKU-TEST-1',
            'price' => 10000,
            'stock_quantity' => 5,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);

        return [$user, $product];
    }

    public function test_public_can_list_questions_for_a_live_product(): void
    {
        [, $product] = $this->makeSellerWithProduct();
        ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'question' => 'سؤال عام',
        ]);

        $this->getJson("/api/v1/products/{$product->slug}/questions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question', 'سؤال عام');
    }

    public function test_guest_cannot_ask_question(): void
    {
        [, $product] = $this->makeSellerWithProduct();

        $this->postJson("/api/v1/products/{$product->slug}/questions", ['question' => 'مرحباً'])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_ask_question(): void
    {
        [, $product] = $this->makeSellerWithProduct();
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/products/{$product->slug}/questions", ['question' => 'هل يتوفر لون أزرق؟'])
            ->assertCreated()
            ->assertJsonPath('data.question', 'هل يتوفر لون أزرق؟')
            ->assertJsonPath('data.can_delete', true);

        $this->assertDatabaseHas('product_questions', [
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'question' => 'هل يتوفر لون أزرق؟',
        ]);
    }

    public function test_question_requires_minimum_length(): void
    {
        [, $product] = $this->makeSellerWithProduct();
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson("/api/v1/products/{$product->slug}/questions", ['question' => 'ok'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_user_can_answer_a_question(): void
    {
        [$sellerUser, $product] = $this->makeSellerWithProduct();
        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'question' => 'ما هو المقاس؟',
        ]);

        // البائع يجاوب ويُوسم تلقائياً كجواب بائع.
        Sanctum::actingAs($sellerUser);
        $this->postJson("/api/v1/questions/{$question->id}/answers", ['answer' => 'متوفر M و L'])
            ->assertCreated()
            ->assertJsonPath('data.is_seller_answer', true);

        $this->assertDatabaseHas('product_question_answers', [
            'product_question_id' => $question->id,
            'is_seller_answer' => 1,
        ]);
    }

    public function test_helpful_vote_toggles_and_blocks_duplicates(): void
    {
        [, $product] = $this->makeSellerWithProduct();
        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'question' => 'سؤال عن الشحن',
        ]);
        $voter = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($voter);

        $first = $this->postJson("/api/v1/questions/{$question->id}/helpful")->assertOk();
        $first->assertJsonPath('is_helpful', true)->assertJsonPath('helpful_count', 1);

        $second = $this->postJson("/api/v1/questions/{$question->id}/helpful")->assertOk();
        $second->assertJsonPath('is_helpful', false)->assertJsonPath('helpful_count', 0);

        $this->assertDatabaseCount('product_question_helpful_votes', 0);
    }

    public function test_only_owner_or_admin_can_delete_question(): void
    {
        [, $product] = $this->makeSellerWithProduct();
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'question' => 'سؤال سأحذفه',
        ]);

        // مستخدم آخر لا يملك الحذف.
        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/questions/{$question->id}")->assertForbidden();

        // المالك يملك الحذف.
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/questions/{$question->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('product_questions', ['id' => $question->id]);
    }
}
