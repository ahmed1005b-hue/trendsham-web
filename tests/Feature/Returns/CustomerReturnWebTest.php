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

class CustomerReturnWebTest extends TestCase
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

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function requested(int $quantity = 1): ReturnRequest
    {
        return $this->service()->create($this->customer, $this->order, [$this->line->id => $quantity], 'changed_mind', 'وصف');
    }

    public function test_customer_creates_a_return_with_photos(): void
    {
        $response = $this->actingAs($this->customer)->post(route('customer.returns.store', $this->order), [
            'lines' => [$this->line->id => 2],
            'reason' => 'damaged',
            'description' => 'وصل مكسوراً',
            'photos' => [UploadedFile::fake()->image('a.jpg', 300, 300), UploadedFile::fake()->image('b.jpg', 300, 300)],
        ]);

        $request = ReturnRequest::firstOrFail();
        $response->assertRedirect(route('customer.returns.show', $request));
        $this->assertSame('requested', $request->status);
        $this->assertSame(2, (int) $request->items()->first()->quantity);
        $this->assertCount(2, $request->events()->first()->photos);
        Storage::disk('public')->assertExists($request->events()->first()->photos[0]);
    }

    public function test_create_failure_flashes_the_service_message(): void
    {
        $response = $this->actingAs($this->customer)->from(route('customer.returns.create', $this->order))
            ->post(route('customer.returns.store', $this->order), [
                'lines' => [$this->line->id => 4],
                'reason' => 'changed_mind',
            ]);

        $response->assertRedirect(route('customer.returns.create', $this->order));
        $response->assertSessionHas('error', 'الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.');
        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_create_form_lists_only_the_returnable_quantity(): void
    {
        $this->requested(1);

        $this->actingAs($this->customer)->get(route('customer.returns.create', $this->order))
            ->assertOk()
            ->assertSee('max="2"', false)
            ->assertSee($this->seller->store_name);
    }

    public function test_index_lists_only_own_returns(): void
    {
        $mine = $this->requested();
        $other = $this->customer();
        $foreign = $this->order($other, 1_000_000);
        $foreignLine = $foreign->items()->first();
        $foreign = $this->deliver($foreign);
        $theirs = $this->service()->create($other, $foreign, [$foreignLine->id => 1], 'changed_mind', null);

        $this->actingAs($this->customer)->get(route('customer.returns.index'))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($theirs->number);
    }

    public function test_show_displays_events_and_withdraw_closes_the_return(): void
    {
        $request = $this->requested();

        $this->actingAs($this->customer)->get('/customer/returns/'.$request->id)
            ->assertOk()
            ->assertSee($request->number)
            ->assertSee('تم إرسال الطلب')
            ->assertSee('سحب الطلب');

        $this->post(route('customer.returns.withdraw', $request))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('closed', $request->fresh()->status);

        $this->get(route('customer.returns.show', $request))->assertOk()->assertSee('سحب الزبون الطلب')->assertDontSee('سحب الطلب');
    }

    public function test_withdraw_of_a_non_withdrawable_return_flashes_the_error(): void
    {
        $request = $this->service()->sellerReject($this->seller, $this->requested(), 'سبب');

        $this->actingAs($this->customer)->post(route('customer.returns.withdraw', $request))
            ->assertRedirect()
            ->assertSessionHas('error', 'لا يمكن سحب طلب الإرجاع في حالته الحالية.');
        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_customer_disputes_a_rejected_return(): void
    {
        $request = $this->service()->sellerReject($this->seller, $this->requested(), 'سبب الرفض');

        $this->actingAs($this->customer)->get(route('customer.returns.show', $request))
            ->assertOk()->assertSee('رفع النزاع');

        $this->post(route('customer.returns.dispute', $request), [
            'note' => 'أعترض على القرار',
            'photos' => [UploadedFile::fake()->image('c.jpg', 300, 300)],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('escalated', $request->fresh()->status);
    }

    public function test_dispute_requires_a_note_and_hides_the_form_when_not_rejected(): void
    {
        $request = $this->requested();

        $this->actingAs($this->customer)->get(route('customer.returns.show', $request))->assertDontSee('رفع النزاع');
        $this->post(route('customer.returns.dispute', $request), [])->assertSessionHasErrors('note');
        $this->post(route('customer.returns.dispute', $request), ['note' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame('requested', $request->fresh()->status);
    }

    public function test_return_button_shows_only_when_a_line_is_returnable(): void
    {
        $this->actingAs($this->customer)->get(route('customer.orders.show', $this->order))
            ->assertOk()->assertSee('طلب إرجاع')->assertSee(route('customer.returns.create', $this->order));

        // مهلة الإرجاع انتهت
        $this->line->update(['commission_settled_at' => now()->subDays(30)]);
        $this->get(route('customer.orders.show', $this->order))->assertOk()->assertDontSee(route('customer.returns.create', $this->order));
        $this->get(route('customer.returns.create', $this->order))->assertNotFound();
    }

    public function test_return_button_hidden_when_all_units_are_held_or_order_not_delivered(): void
    {
        $this->requested(3);
        $this->actingAs($this->customer)->get(route('customer.orders.show', $this->order))
            ->assertOk()->assertDontSee(route('customer.returns.create', $this->order));

        $pending = $this->order($this->customer, 1_000_000);
        $this->get(route('customer.orders.show', $pending))->assertOk()->assertDontSee(route('customer.returns.create', $pending));
    }

    public function test_another_customer_cannot_use_the_return_pages(): void
    {
        $request = $this->requested();
        $other = $this->customer();

        $this->actingAs($other)->get(route('customer.returns.show', $request))->assertForbidden();
        $this->post(route('customer.returns.withdraw', $request))->assertForbidden();
        $this->post(route('customer.returns.dispute', $request), ['note' => 'x'])->assertForbidden();
        $this->get(route('customer.returns.create', $this->order))->assertForbidden();
    }
}
