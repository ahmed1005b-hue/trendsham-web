<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerSupportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_list_support_conversations_with_unread_counts(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر الاختبار',
            'status' => 'approved',
        ]);
        $staff = User::factory()->create(['role' => 'admin']);
        $conversation = StaffConversation::create([
            'vendor_id' => $profile->id,
            'staff_id' => $staff->id,
            'subject' => 'مشكلة في الطلب',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        StaffMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $staff->id,
            'body' => 'كيف يمكنني المساعدة؟',
        ]);

        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/support')
            ->assertOk()
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('conversations.0.staff_name', 'فريق الدعم')
            ->assertJsonPath('conversations.0.unread_count', 1);
    }

    /** البائع يراسل «فريق الدعم» لا شخصاً باسمه: يُشعَر كل من يعمل في قسم الدعم، ولا تظهر للبائع أسماء الفريق. */
    public function test_seller_writes_to_the_whole_support_team(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر', 'status' => 'approved']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $support = User::factory()->create(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'employee_permissions' => ['support']]);
        $ordersOnly = User::factory()->create(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'employee_permissions' => ['orders']]);
        $customer = User::factory()->create(['role' => 'customer']);
        $message = ['subject' => 'سؤال', 'body' => 'عندي سؤال عن الطلب'];

        // نسخة قديمة من التطبيق ترسل staff_id: يُتجاهل ولا يكشف أحداً
        Sanctum::actingAs($seller);
        $this->postJson('/api/v1/seller/support', [...$message, 'staff_id' => $customer->id])->assertCreated();
        $this->actingAs($seller)->get(route('seller.support.create'))->assertOk()
            ->assertDontSee($admin->name)->assertDontSee($support->name);
        $this->actingAs($seller)->post(route('seller.support.store'), $message)->assertRedirect();

        $this->assertSame([$admin->id, $admin->id], StaffConversation::where('vendor_id', $profile->id)->pluck('staff_id')->all());
        $notified = AppNotification::where('type', 'staff_message')->pluck('user_id')->unique()->sort()->values()->all();
        $this->assertSame([$admin->id, $support->id], $notified);
        $this->assertStringContainsString('/employee/support/', (string) AppNotification::where('user_id', $support->id)->value('link'));
        $this->assertSame(0, AppNotification::where('user_id', $ordersOnly->id)->count());
    }
}
