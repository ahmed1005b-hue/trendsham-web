<?php

namespace Tests\Feature;

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
            ->assertJsonPath('conversations.0.staff_name', $staff->name)
            ->assertJsonPath('conversations.0.unread_count', 1);
    }
}
