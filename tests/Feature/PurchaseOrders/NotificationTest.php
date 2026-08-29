<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\CustomerMessage;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertSame((string) $message->id, $record->external_reference);
    }

    public function test_customer_created_order_texts_the_customer_when_sms_is_enabled(): void
    {
        config(['services.po_notifications.sms_enabled' => true, 'services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/*' => Http::response([['message_id' => 1, 'status' => 'Queued']]),
        ]);
        $user = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($user)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['number'] === '09171234567';
        });

        $record = PurchaseOrderNotification::where('channel', 'sms')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertSame('09171234567', $record->recipient);
    }

    public function test_customer_created_order_skips_sms_when_disabled(): void
    {
        config(['services.po_notifications.sms_enabled' => false]);
        $user = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct('Widget');

        $this->actingAsUser($user)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'semaphore.co'));

        $record = PurchaseOrderNotification::where('channel', 'sms')->first();
        $this->assertNotNull($record);
        $this->assertSame('skipped', $record->status);
    }

    public function test_staff_created_order_still_notifies_the_customer(): void
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

        // Order events notify the customer regardless of which side (the
        // customer themself or staff acting on their behalf) triggered
        // them -- a staff-created order is no exception.
        $this->assertSame(1, CustomerMessage::count());
        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
    }

    public function test_order_update_notifies_the_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Acme Co');
        $product = $this->makeProduct('Widget');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $customer->id,
            'remarks' => '',
            "quantity_{$item->id}" => 8,
        ]);

        $message = CustomerMessage::first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Updated', $message->subject);

        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertSame((string) $message->id, $record->external_reference);
    }

    public function test_fulfillment_update_notifies_the_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$item->id}" => 2,
        ]);

        $message = CustomerMessage::first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Delivery Updated', $message->subject);

        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
    }

    public function test_completion_notifies_the_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/complete");

        $message = CustomerMessage::first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Completed', $message->subject);

        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
    }

    public function test_cancellation_notifies_the_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/cancel");

        $message = CustomerMessage::first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Cancelled', $message->subject);

        $record = PurchaseOrderNotification::where('channel', 'inbox')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
    }
}
