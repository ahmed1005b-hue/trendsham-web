<?php

namespace Tests\Feature\Returns;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Returns\Models\ReturnEvent;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

class ReturnSchemaTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_tables_and_relations_exist(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, 2_000_000, ['status' => 'delivered']);
        $orderItem = $order->items()->first();

        $request = ReturnRequest::create([
            'number' => 'RT-000001', 'order_id' => $order->id, 'user_id' => $customer->id,
            'seller_profile_id' => $orderItem->vendor_id, 'status' => 'requested', 'reason' => 'damaged',
            'description' => 'وصل مكسورًا', 'refund_amount' => 2_000_000, 'respond_by' => now()->addDays(3),
        ]);
        $request->items()->create(['order_item_id' => $orderItem->id, 'quantity' => 1, 'refund_amount' => 1_000_000, 'seller_reversal' => 900_000, 'commission_reversal' => 100_000]);
        $request->items()->create(['order_item_id' => $orderItem->id, 'quantity' => 1, 'refund_amount' => 1_000_000, 'seller_reversal' => 900_000, 'commission_reversal' => 100_000]);
        $request->events()->create(['actor_id' => $customer->id, 'actor_role' => 'customer', 'type' => 'requested', 'photos' => ['returns/a.jpg']]);

        $request = $request->fresh();
        $this->assertCount(2, $request->items);
        $this->assertInstanceOf(ReturnItem::class, $request->items->first());
        $this->assertSame($orderItem->id, $request->items->first()->orderItem->id);
        $this->assertInstanceOf(ReturnEvent::class, $request->events->first());
        $this->assertSame(['returns/a.jpg'], $request->events->first()->photos);
        $this->assertInstanceOf(Carbon::class, $request->respond_by);
        $this->assertSame($order->id, $request->order->id);
        $this->assertSame($customer->id, $request->customer->id);
        $this->assertSame($orderItem->vendor_id, $request->seller->id);
        $this->assertNull($request->refund);
        $this->assertSame(1, Order::find($order->id)->returnRequests()->count());
        $this->assertSame(2, $orderItem->returnItems()->count());
        $this->assertSame(2, $orderItem->heldReturnQuantity());
        $this->assertSame(0, $orderItem->reversedQuantity());

        $request->items->first()->update(['settled_at' => now()]);
        $this->assertSame(1, $orderItem->reversedQuantity());

        $request->update(['status' => 'closed']);
        $this->assertSame(0, $orderItem->heldReturnQuantity());
        $this->assertContains('rejected', ReturnRequest::STATUSES);
    }

    public function test_settings_defaults_and_overrides(): void
    {
        $settings = new ReturnSettings;
        $this->assertSame(7, $settings->windowDays());
        $this->assertSame(3, $settings->sellerResponseDays());
        $this->assertSame(7, $settings->disputeDays());
        $this->assertSame(14, $settings->shipBackDays());

        PlatformSetting::current()->forceFill(['returns_window_days' => 10])->save();
        $this->assertSame(10, (new ReturnSettings)->windowDays());
    }
}
