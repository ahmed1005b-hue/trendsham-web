<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TenantFixtureTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_two_tenants_are_complete_and_disjoint(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');

        foreach (['customer', 'seller', 'store', 'product', 'image', 'cartItem', 'order', 'orderItem', 'address', 'card', 'payment',
            'notification', 'conversation', 'question', 'supportConversation', 'deal', 'story', 'paymentMethod',
            'showcase', 'campaign', 'withdrawal', 'returnRequest'] as $key) {
            $this->assertNotNull($a[$key]?->getKey(), "a.$key");
            $this->assertNotSame($a[$key]->getKey(), $b[$key]->getKey(), "$key shared between tenants");
        }
        $this->assertSame($a['store']->id, $a['orderItem']->vendor_id);
        $this->assertSame($a['customer']->id, $a['order']->user_id);

        foreach (['a' => $a, 'b' => $b] as $tag => $t) {
            $this->assertOwnedBy($t, $tag);
        }
    }

    /** كل مورد في المستأجر مربوط بمالكه هو فقط، لا بمالك المستأجر الآخر. */
    private function assertOwnedBy(array $t, string $tag): void
    {
        $customer = $t['customer']->id;
        $store = $t['store']->id;
        $links = [
            'store.user_id' => [$t['store']->user_id, $t['seller']->id],
            'product.vendor_id' => [$t['product']->vendor_id, $store],
            'image.product_id' => [$t['image']->product_id, $t['product']->id],
            'cartItem.user_id' => [$t['cartItem']->user_id, $customer],
            'order.user_id' => [$t['order']->user_id, $customer],
            'orderItem.order_id' => [$t['orderItem']->order_id, $t['order']->id],
            'orderItem.vendor_id' => [$t['orderItem']->vendor_id, $store],
            'address.user_id' => [$t['address']->user_id, $customer],
            'card.user_id' => [$t['card']->user_id, $customer],
            'payment.order_id' => [$t['payment']->order_id, $t['order']->id],
            'notification.user_id' => [$t['notification']->user_id, $customer],
            'conversation.customer_id' => [$t['conversation']->customer_id, $customer],
            'conversation.vendor_id' => [$t['conversation']->vendor_id, $store],
            'question.user_id' => [$t['question']->user_id, $customer],
            'supportConversation.vendor_id' => [$t['supportConversation']->vendor_id, $store],
            'deal.product_id' => [$t['deal']->product_id, $t['product']->id],
            'story.vendor_id' => [$t['story']->vendor_id, $store],
            'paymentMethod.vendor_id' => [$t['paymentMethod']->vendor_id, $store],
            'showcase.seller_id' => [$t['showcase']->seller_id, $store],
            'campaign.seller_profile_id' => [$t['campaign']->seller_profile_id, $store],
            'withdrawal.vendor_id' => [$t['withdrawal']->vendor_id, $store],
            'returnRequest.user_id' => [$t['returnRequest']->user_id, $customer],
            'returnRequest.seller_profile_id' => [$t['returnRequest']->seller_profile_id, $store],
        ];
        foreach ($links as $name => [$actual, $expected]) {
            $this->assertEquals($expected, $actual, "$tag.$name");
        }
    }
}
