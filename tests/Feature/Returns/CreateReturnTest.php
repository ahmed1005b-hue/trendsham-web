<?php

namespace Tests\Feature\Returns;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

class CreateReturnTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private Order $order;

    private OrderItem $line;

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
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function create(array $lines, string $reason = 'changed_mind', array $photos = [], ?Order $order = null, ?User $by = null): ReturnRequest
    {
        return $this->service()->create($by ?? $this->customer, $order ?? $this->order, $lines, $reason, 'وصف', $photos);
    }

    private function assertRejected(string $message, callable $fn): void
    {
        try {
            $fn();
            $this->fail('expected DomainException');
        } catch (\DomainException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_customer_returns_part_of_a_delivered_line(): void
    {
        $request = $this->create([$this->line->id => 2], 'damaged', [UploadedFile::fake()->image('a.jpg', 300, 300)]);

        $this->assertSame('requested', $request->status);
        $this->assertMatchesRegularExpression('/^RT-[A-Z0-9]{8}$/', $request->number);
        $this->assertSame($this->line->vendor_id, $request->seller_profile_id);
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $request->respond_by->timestamp, 5);
        $item = $request->items->first();
        $this->assertSame(2, $item->quantity);
        $this->assertSame(2_000_000, $item->refund_amount);
        $this->assertSame(0, $request->shipping_refund);
        $this->assertSame(2_000_000, $request->refund_amount);
        $event = $request->events->first();
        $this->assertSame('requested', $event->type);
        $this->assertCount(1, $event->photos);
        Storage::disk('public')->assertExists($event->photos[0]);
        $this->assertSame(2, $this->line->heldReturnQuantity());
    }

    public function test_single_vendor_full_seller_fault_return_carries_shipping_once_received(): void
    {
        $request = $this->create([$this->line->id => 3], 'damaged');

        // أجرة التوصيل لا تُحسب عند الإنشاء: تُحمَّل عند الاستلام على المرتجع الذي يُكمل إرجاع الطلب
        $this->assertSame(0, $request->shipping_refund);
        $this->assertSame(3_000_000, $request->refund_amount);

        $seller = SellerProfile::findOrFail($this->line->vendor_id);
        $request = $this->service()->confirmReceived($seller->user, $this->service()->sellerApprove($seller, $request));

        $this->assertSame(500_000, $request->fresh()->shipping_refund);
        $this->assertSame(3_500_000, $request->fresh()->refund_amount);
    }

    public function test_delivery_check_reads_the_locked_order_row_not_the_callers_copy(): void
    {
        $stale = $this->order;
        Order::whereKey($stale->id)->update(['status' => 'cancelled']);

        $this->assertSame('delivered', $stale->status);
        $this->assertRejected('لا يمكن إرجاع طلب لم يُسلَّم.', fn () => $this->create([$this->line->id => 1], order: $stale));
        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_rejects_after_window(): void
    {
        $this->line->update(['commission_settled_at' => now()->subDays(8)]);

        $this->assertRejected('انتهت مدة الإرجاع لهذا الطلب.', fn () => $this->create([$this->line->id => 1]));
    }

    public function test_rejects_undelivered_or_cancelled_order(): void
    {
        $this->order->forceFill(['status' => 'shipped'])->save();
        $this->assertRejected('لا يمكن إرجاع طلب لم يُسلَّم.', fn () => $this->create([$this->line->id => 1], order: $this->order->fresh()));

        $this->order->forceFill(['status' => 'cancelled'])->save();
        $this->assertRejected('لا يمكن إرجاع طلب لم يُسلَّم.', fn () => $this->create([$this->line->id => 1], order: $this->order->fresh()));
    }

    public function test_rejects_more_than_returnable_quantity_across_open_requests(): void
    {
        $this->create([$this->line->id => 2]);

        $this->assertRejected('الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.', fn () => $this->create([$this->line->id => 2]));
        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_rejected_request_still_holds_its_quantity(): void
    {
        $first = $this->create([$this->line->id => 3]);
        $first->update(['status' => 'rejected']);

        $this->assertRejected('الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.', fn () => $this->create([$this->line->id => 1]));
    }

    public function test_second_request_after_withdrawal_can_take_released_units(): void
    {
        $first = $this->create([$this->line->id => 3]);
        $this->service()->withdraw($this->customer, $first);

        $second = $this->create([$this->line->id => 3]);

        $this->assertSame('requested', $second->status);
        $this->assertSame(3, $this->line->heldReturnQuantity());
    }

    public function test_rejects_lines_from_two_sellers(): void
    {
        $other = $this->product(price: 500_000);
        $second = OrderItem::create([
            'order_id' => $this->order->id, 'product_id' => $other->id, 'vendor_id' => $other->vendor_id,
            'product_name' => 'x', 'unit_price' => 500_000, 'quantity' => 1, 'subtotal' => 500_000,
            'commission_settled_at' => now(),
        ]);

        $this->assertRejected('طلب الإرجاع يجب أن يخص متجراً واحداً.', fn () => $this->create([$this->line->id => 1, $second->id => 1]));
    }

    public function test_rejects_more_than_five_photos(): void
    {
        $photos = array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg"), range(1, 6));

        $this->expectException(\DomainException::class);
        $this->create([$this->line->id => 1], 'damaged', $photos);
    }

    public function test_rejects_unknown_reason(): void
    {
        $this->expectException(\DomainException::class);
        $this->create([$this->line->id => 1], 'because');
    }

    public function test_customer_cannot_return_another_customers_order(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->create([$this->line->id => 1], by: $this->customer());
    }

    public function test_withdraw_closes_and_releases_quantity(): void
    {
        $request = $this->create([$this->line->id => 2]);
        $this->assertSame(2, $this->line->heldReturnQuantity());

        $withdrawn = $this->service()->withdraw($this->customer, $request);

        $this->assertSame('closed', $withdrawn->status);
        $this->assertNotNull($withdrawn->closed_at);
        $this->assertSame(0, $this->line->heldReturnQuantity());
        $this->assertSame('withdrawn', $withdrawn->events()->latest('id')->first()->type);

        $this->assertRejected('لا يمكن سحب طلب الإرجاع في حالته الحالية.', fn () => $this->service()->withdraw($this->customer, $withdrawn));
    }

    public function test_invalid_photo_leaves_no_files_or_rows(): void
    {
        $photos = [
            UploadedFile::fake()->image('a.jpg', 300, 300),
            UploadedFile::fake()->image('b.jpg', 300, 300),
            UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ];

        $this->assertRejected('إحدى الصور غير صالحة.', fn () => $this->create([$this->line->id => 1], 'damaged', $photos));

        $this->assertSame(0, ReturnRequest::count());
        $this->assertSame([], Storage::disk('public')->allFiles('returns'));
    }

    public function test_failed_create_after_upload_deletes_photos(): void
    {
        $photos = [UploadedFile::fake()->image('a.jpg', 300, 300), UploadedFile::fake()->image('b.jpg', 300, 300)];

        $this->assertRejected('الكمية المطلوبة أكبر من الكمية القابلة للإرجاع.', fn () => $this->create([$this->line->id => 9], 'damaged', $photos));

        $this->assertSame(0, ReturnRequest::count());
        $this->assertSame([], Storage::disk('public')->allFiles('returns'));
    }

    public function test_seller_is_notified(): void
    {
        $request = $this->create([$this->line->id => 1]);

        $notification = AppNotification::where('user_id', $request->seller->user_id)->where('type', 'return_requested')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString($request->number, $notification->title);
        $this->assertSame(url('/seller/returns/'.$request->id), $notification->link);
    }
}
