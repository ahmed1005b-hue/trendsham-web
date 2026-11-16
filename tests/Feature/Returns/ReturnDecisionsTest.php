<?php

namespace Tests\Feature\Returns;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnNotifier;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

class ReturnDecisionsTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private User $admin;

    private Order $order;

    private OrderItem $line;

    private SellerProfile $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->customer = $this->customer();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->order = $this->order($this->customer, 5_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 5, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function requested(): ReturnRequest
    {
        return $this->service()->create($this->customer, $this->order, [$this->line->id => 1], 'changed_mind', 'وصف');
    }

    private function rejected(): ReturnRequest
    {
        return $this->service()->sellerReject($this->seller, $this->requested(), 'سبب الرفض');
    }

    private function escalated(): ReturnRequest
    {
        return $this->service()->dispute($this->customer, $this->rejected(), 'اعتراض');
    }

    private function approved(): ReturnRequest
    {
        return $this->service()->sellerApprove($this->seller, $this->requested(), 'تمت');
    }

    private function assertIllegal(callable $fn): void
    {
        try {
            $fn();
            $this->fail('expected DomainException');
        } catch (\DomainException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_seller_approves_requested_return(): void
    {
        $request = $this->approved();

        $this->assertSame('approved', $request->status);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $request->ship_back_by->timestamp, 5);
        $event = $request->events()->latest('id')->first();
        $this->assertSame(['seller', 'approved', 'تمت'], [$event->actor_role, $event->type, $event->note]);
        $this->assertSame($this->seller->user_id, $event->actor_id);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_approved')->exists());
    }

    public function test_seller_approve_from_wrong_status_is_refused(): void
    {
        foreach ([$this->rejected(), $this->approved()] as $request) {
            $this->assertIllegal(fn () => $this->service()->sellerApprove($this->seller, $request, null));
        }
    }

    public function test_seller_rejects_with_note_and_opens_dispute_window(): void
    {
        $request = $this->rejected();

        $this->assertSame('rejected', $request->status);
        $this->assertSame('سبب الرفض', $request->decision_note);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $request->dispute_by->timestamp, 5);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_rejected')->exists());
    }

    public function test_seller_reject_requires_note_and_valid_status(): void
    {
        $request = $this->requested();
        $this->assertIllegal(fn () => $this->service()->sellerReject($this->seller, $request, '  '));
        $this->assertSame('requested', $request->fresh()->status);

        $approved = $this->approved();
        $this->assertIllegal(fn () => $this->service()->sellerReject($this->seller, $approved, 'x'));
    }

    public function test_customer_disputes_rejected_return_with_photos(): void
    {
        $rejected = $this->rejected();

        $request = $this->service()->dispute($this->customer, $rejected, 'اعتراض', [UploadedFile::fake()->image('a.jpg', 300, 300)]);

        $this->assertSame('escalated', $request->status);
        $event = $request->events()->latest('id')->first();
        $this->assertSame(['customer', 'disputed'], [$event->actor_role, $event->type]);
        Storage::disk('public')->assertExists($event->photos[0]);
        $this->assertTrue(AppNotification::where('user_id', $this->seller->user_id)->where('type', 'return_disputed')->exists());
    }

    public function test_dispute_from_wrong_status_is_refused_and_photos_cleaned(): void
    {
        $request = $this->requested();

        $this->assertIllegal(fn () => $this->service()->dispute($this->customer, $request, 'x', [UploadedFile::fake()->image('a.jpg', 300, 300)]));

        $this->assertSame([], Storage::disk('public')->allFiles('returns'));
    }

    public function test_dispute_after_window_is_refused(): void
    {
        $rejected = $this->rejected();

        $this->travel(8)->days();

        $this->assertIllegal(fn () => $this->service()->dispute($this->customer, $rejected, 'متأخر'));
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_only_the_owner_can_dispute(): void
    {
        $rejected = $this->rejected();

        $this->expectException(NotFoundHttpException::class);
        $this->service()->dispute($this->customer(), $rejected, 'x');
    }

    public function test_admin_approves_escalated_return(): void
    {
        $request = $this->service()->adminApprove($this->admin, $this->escalated(), 'محق');

        $this->assertSame('approved', $request->status);
        $this->assertSame($this->admin->id, $request->decided_by);
        $this->assertSame('محق', $request->decision_note);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $request->ship_back_by->timestamp, 5);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_admin_decided')->exists());
    }

    public function test_admin_rejects_escalated_return_and_closes_it(): void
    {
        $request = $this->service()->adminReject($this->admin, $this->escalated(), 'غير مبرر');

        $this->assertSame('closed', $request->status);
        $this->assertNotNull($request->closed_at);
        $this->assertSame($this->admin->id, $request->decided_by);
        $this->assertIllegal(fn () => $this->service()->adminReject($this->admin, $this->requested(), 'x'));
        $this->assertIllegal(fn () => $this->service()->adminReject($this->admin, $this->escalated(), ''));
    }

    public function test_admin_approve_from_wrong_status_and_non_admin_refused(): void
    {
        $this->assertIllegal(fn () => $this->service()->adminApprove($this->admin, $this->requested(), null));

        $escalated = $this->escalated();
        $this->expectException(AccessDeniedHttpException::class);
        $this->service()->adminApprove($this->customer, $escalated, null);
    }

    public function test_seller_confirms_receipt(): void
    {
        $request = $this->service()->confirmReceived($this->seller->user, $this->approved());

        $this->assertSame('received', $request->status);
        $this->assertNotNull($request->received_at);
        $this->assertSame('seller', $request->events()->latest('id')->first()->actor_role);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_received')->exists());
    }

    public function test_confirm_receipt_from_wrong_status_is_refused(): void
    {
        $request = $this->requested();

        $this->assertIllegal(fn () => $this->service()->confirmReceived($this->seller->user, $request));
    }

    public function test_admin_can_confirm_receipt_for_unresponsive_seller(): void
    {
        $request = $this->service()->confirmReceived($this->admin, $this->approved());

        $this->assertSame('received', $request->status);
        $this->assertSame('admin', $request->events()->latest('id')->first()->actor_role);
    }

    public function test_seller_of_another_store_cannot_decide(): void
    {
        $other = $this->vendor();
        $request = $this->requested();

        foreach ([
            fn () => $this->service()->sellerApprove($other, $request, null),
            fn () => $this->service()->sellerReject($other, $request, 'x'),
            fn () => $this->service()->confirmReceived($other->user, $this->approved()),
            fn () => $this->service()->confirmReceived($this->customer, $this->approved()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('expected 404');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('requested', $request->fresh()->status);
    }

    public function test_daily_job_escalates_closes_and_is_idempotent(): void
    {
        $toEscalate = $this->requested();
        $toCloseRejected = $this->rejected();
        $toCloseApproved = $this->approved();
        $untouched = $this->requested();

        $this->travel(4)->days();
        $untouched->update(['respond_by' => now()->addDay()]);
        $this->assertSame(['escalated' => 1, 'closed' => 0], $this->service()->runDaily());
        $this->assertSame('escalated', $toEscalate->fresh()->status);
        $system = $toEscalate->events()->latest('id')->first();
        $this->assertSame(['system', null, 'escalated'], [$system->actor_role, $system->actor_id, $system->type]);

        $this->travel(11)->days();
        $result = $this->service()->runDaily();
        $this->assertSame(2, $result['closed']);
        $this->assertSame('closed', $toCloseRejected->fresh()->status);
        $this->assertSame('closed', $toCloseApproved->fresh()->status);
        $this->assertNotNull($toCloseApproved->fresh()->closed_at);
        $this->assertTrue(AppNotification::where('user_id', $this->customer->id)->where('type', 'return_closed')->exists());

        $this->assertSame(['escalated' => 0, 'closed' => 0], $this->service()->runDaily());
        $this->assertSame(1, $toCloseApproved->events()->where('type', 'closed')->count());
    }

    public function test_cancel_blocked_while_rejection_is_still_disputable(): void
    {
        $rejected = $this->rejected();

        try {
            $this->order->fresh()->changeStatus('cancelled');
            $this->fail('cancel must be blocked while the rejection can still be disputed');
        } catch (\DomainException $e) {
            $this->assertSame('لا يمكن إلغاء الطلب وعليه طلب إرجاع مفتوح.', $e->getMessage());
        }
        $this->assertSame('delivered', $this->order->fresh()->status);

        $this->travel(8)->days();
        $this->assertSame('delivered', $this->order->fresh()->changeStatus('cancelled'));
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_dispute_and_admin_approve_refused_on_cancelled_order(): void
    {
        $rejected = $this->rejected();
        $escalated = $this->escalated();
        // حالة لا يصل إليها المسار الطبيعي (الإلغاء ممنوع)، تحاكي بيانات سابقة للإصلاح
        Order::whereKey($this->order->id)->update(['status' => 'cancelled']);
        $message = 'لا يمكن متابعة طلب الإرجاع لأن الطلب ملغى.';

        foreach ([
            fn () => $this->service()->dispute($this->customer, $rejected, 'اعتراض'),
            fn () => $this->service()->adminApprove($this->admin, $escalated, null),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('expected DomainException');
            } catch (\DomainException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('escalated', $escalated->fresh()->status);
    }

    public function test_admins_are_notified_of_disputes_and_auto_escalations(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $disputed = $this->escalated();

        foreach ([$this->admin, $otherAdmin] as $admin) {
            $notification = AppNotification::where('user_id', $admin->id)->where('type', 'return_disputed')->sole();
            $this->assertSame('فتح الزبون نزاعًا على الإرجاع '.$disputed->number, $notification->title);
            $this->assertStringEndsWith('/admin/returns/'.$disputed->id, $notification->link);
        }

        $late = $this->requested();
        $this->travel(4)->days();
        $this->service()->runDaily();

        foreach ([$this->customer->id, $this->seller->user_id, $this->admin->id, $otherAdmin->id] as $userId) {
            $this->assertTrue(AppNotification::where('user_id', $userId)->where('type', 'return_escalated')
                ->where('link', 'like', '%/returns/'.$late->id)->exists());
        }
    }

    public function test_notifier_failure_is_reported_and_does_not_abort_the_action_or_daily_job(): void
    {
        $first = $this->requested();
        $second = $this->requested();
        $this->travel(4)->days();

        $this->mock(ReturnNotifier::class, function ($mock) {
            $mock->shouldReceive('escalated')->twice()->andThrow(new \RuntimeException('push down'));
            $mock->shouldReceive('requested')->once()->andThrow(new \RuntimeException('push down'));
            $mock->shouldReceive('approved')->once()->andThrow(new \RuntimeException('push down'));
        });
        Exceptions::fake();

        $this->assertSame(['escalated' => 2, 'closed' => 0], $this->service()->runDaily());
        $this->assertSame('escalated', $first->fresh()->status);
        $this->assertSame('escalated', $second->fresh()->status);

        $approved = $this->service()->sellerApprove($this->seller, $this->requested());
        $this->assertSame('approved', $approved->fresh()->status);
        Exceptions::assertReportedCount(4);
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'push down');
    }

    public function test_daily_command_prints_counts(): void
    {
        $this->requested();
        $this->travel(4)->days();

        $this->artisan('returns:daily')->expectsOutputToContain('صُعِّد 1')->assertSuccessful();
    }
}
