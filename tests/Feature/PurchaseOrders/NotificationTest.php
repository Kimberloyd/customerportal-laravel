<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\CustomerMessage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_created_order_produces_one_inbox_message(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($user)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $this->assertSame(1, CustomerMessage::count());
        $message = CustomerMessage::first();
        $this->assertSame($customer->id, $message->customer_id);
        $this->assertSame('company', $message->sender_type);
        $this->assertSame('open', $message->status);
        $this->assertFalse($message->is_read);
        $this->assertStringContainsString('Submitted', $message->subject);
        $this->assertNotNull($message->public_token);
        $this->assertTrue($message->public_token_expires_at->isFuture());
    }

    public function test_staff_created_order_produces_no_notification(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($staff)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $this->assertSame(0, CustomerMessage::count());
    }
}
