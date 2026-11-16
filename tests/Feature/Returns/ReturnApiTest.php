<?php

namespace Tests\Feature\Returns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

class ReturnApiTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private Order $order;

    private OrderItem $line;

    private SellerProfile $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->customer = $this->customer();
        $this->order = $this->order($this->customer, 3_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 3, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
    }

    private function requested(int $quantity = 1): ReturnRequest
    {
        return app(ReturnService::class)->create($this->customer, $this->order, [$this->line->id => $quantity], 'changed_mind', 'وصف');
    }

    public function test_returnable_lists_quantities_and_window_end(): void
    {
        $this->requested(1);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/orders/'.$this->order->id.'/returnable')->assertOk();

        $response->assertJsonPath('data.0.order_item_id', $this->line->id)
            ->assertJsonPath('data.0.returnable_quantity', 2)
            ->assertJsonPath('data.0.unit_price', 10000)
            ->assertJsonPath('data.0.vendor_id', $this->seller->id)
            ->assertJsonPath('data.0.store_name', $this->seller->store_name);
        $this->assertNotNull($response->json('data.0.window_ends_at'));
    }

    public function test_show_refund_has_translated_method_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ReturnService::class);
        $request = $service->confirmReceived($admin, $service->sellerApprove($this->seller, $this->requested()));
        $service->recordRefund($admin, $request, $request->refund_amount, 'bank_transfer', 'TX-9', null);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/returns/'.$request->id)->assertOk()
            ->assertJsonPath('data.refund.method', 'bank_transfer')
            ->assertJsonPath('data.refund.method_label', 'حوالة بنكية');
        $this->withHeader('X-App-Locale', 'en')->getJson('/api/v1/returns/'.$request->id)
            ->assertJsonPath('data.refund.method_label', 'Bank transfer');
    }

    public function test_returnable_is_denied_for_another_customers_order(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/orders/'.$this->order->id.'/returnable')->assertForbidden();
    }

    public function test_create_with_photos_returns_the_resource(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/'.$this->order->id.'/returns', [
            'lines' => [$this->line->id => 2],
            'reason' => 'damaged',
            'description' => 'وصل مكسوراً',
            'photos' => [UploadedFile::fake()->image('a.jpg', 300, 300)],
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.status_label', 'بانتظار رد البائع')
            ->assertJsonPath('data.reason', 'damaged')
            ->assertJsonPath('data.reason_label', 'المنتج تالف')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.product_name', $this->line->product_name)
            ->assertJsonPath('data.actions.can_withdraw', true)
            ->assertJsonPath('data.actions.can_dispute', false)
            ->assertJsonPath('data.actions.can_approve', false)
            ->assertJsonCount(1, 'data.events.0.photos');
        $this->assertStringContainsString('/storage/', $response->json('data.events.0.photos.0'));
        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_create_business_failure_is_422_with_message(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/'.$this->order->id.'/returns', [
            'lines' => [$this->line->id => 4],
            'reason' => 'changed_mind',
        ])->assertStatus(422)->assertJsonPath('message', 'الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.');
        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_create_validates_input(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/'.$this->order->id.'/returns', [
            'lines' => [$this->line->id => 1],
            'reason' => 'bogus',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_index_lists_only_own_returns_and_show_has_events(): void
    {
        $mine = $this->requested();
        $other = $this->customer();
        $foreign = $this->order($other, 1_000_000);
        $foreignLine = $foreign->items()->first();
        $foreign = $this->deliver($foreign);
        app(ReturnService::class)->create($other, $foreign, [$foreignLine->id => 1], 'changed_mind', null);

        $this->actingAs($this->customer, 'sanctum');
        $this->getJson('/api/v1/returns')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $mine->number)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/returns/'.$mine->id)->assertOk()
            ->assertJsonPath('data.refund_amount', 10000)
            ->assertJsonPath('data.items.0.refund_amount', 10000)
            ->assertJsonPath('data.events.0.type', 'requested')
            ->assertJsonPath('data.events.0.label', 'تم إرسال الطلب');
    }

    public function test_withdraw_closes_and_blocks_second_attempt(): void
    {
        $request = $this->requested();
        $this->actingAs($this->customer, 'sanctum');

        $this->postJson('/api/v1/returns/'.$request->id.'/withdraw')->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.actions.can_withdraw', false);
        $this->postJson('/api/v1/returns/'.$request->id.'/withdraw')->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_dispute_after_rejection(): void
    {
        $request = $this->requested();
        app(ReturnService::class)->sellerReject($this->seller, $request, 'سليم');

        $this->actingAs($this->customer, 'sanctum');
        $this->getJson('/api/v1/returns/'.$request->id)->assertJsonPath('data.actions.can_dispute', true);

        $this->postJson('/api/v1/returns/'.$request->id.'/dispute', [])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson('/api/v1/returns/'.$request->id.'/dispute', ['note' => 'غير صحيح'])->assertOk()
            ->assertJsonPath('data.status', 'escalated')
            ->assertJsonPath('data.actions.can_dispute', false);
    }

    public function test_seller_list_filter_and_show(): void
    {
        $pending = $this->requested();
        $approved = $this->requested();
        app(ReturnService::class)->sellerApprove($this->seller, $approved);

        $this->actingAs($this->seller->user, 'sanctum');
        $this->getJson('/api/v1/seller/returns')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/seller/returns?status=approved')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->id);

        $this->getJson('/api/v1/seller/returns/'.$pending->id)->assertOk()
            ->assertJsonPath('data.actions.can_approve', true)
            ->assertJsonPath('data.actions.can_reject', true)
            ->assertJsonPath('data.actions.can_confirm_received', false)
            ->assertJsonPath('data.actions.can_withdraw', false);
        $this->getJson('/api/v1/seller/returns/'.$approved->id)
            ->assertJsonPath('data.actions.can_confirm_received', true);
    }

    public function test_seller_approve_reject_received(): void
    {
        $toApprove = $this->requested();
        $toReject = $this->requested();
        $this->actingAs($this->seller->user, 'sanctum');

        $this->postJson('/api/v1/seller/returns/'.$toApprove->id.'/approve', ['note' => 'أرسله'])->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->postJson('/api/v1/seller/returns/'.$toReject->id.'/reject', [])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson('/api/v1/seller/returns/'.$toReject->id.'/reject', ['note' => 'سليم'])->assertOk()
            ->assertJsonPath('data.status', 'rejected');
        $this->postJson('/api/v1/seller/returns/'.$toReject->id.'/received')->assertStatus(422)->assertJsonStructure(['message']);
        $this->postJson('/api/v1/seller/returns/'.$toApprove->id.'/received')->assertOk()
            ->assertJsonPath('data.status', 'received');
    }

    public function test_roles_are_separated(): void
    {
        $request = $this->requested();

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/seller/returns')->assertForbidden();
        $this->actingAs($this->seller->user, 'sanctum')->getJson('/api/v1/returns')->assertForbidden();
        $this->getJson('/api/v1/returns/'.$request->id)->assertForbidden();
    }
}
