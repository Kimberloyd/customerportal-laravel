<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use App\Support\OrderNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_customer_created_order_produces_a_portal_notification_without_a_chat_message(): void
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

        $this->assertSame(0, CustomerMessage::count());

        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertNull($record->external_reference);
        $this->assertStringContainsString('received', $record->note);
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

    public function test_facebook_is_not_marked_sent_when_messenger_is_not_configured(): void
    {
        config([
            'services.po_notifications.facebook_enabled' => true,
            'services.facebook.page_access_token' => null,
        ]);
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $product = $this->makeProduct('Widget');

        $this->makeThread(null, [
            'assigned_user_id' => $staff->id,
            'channel' => 'facebook_messenger',
            'external_sender_id' => 'facebook-recipient',
        ]);

        $this->actingAsUser($customerUser)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $record = PurchaseOrderNotification::where('channel', 'facebook')->first();
        $this->assertNotNull($record);
        $this->assertSame('skipped', $record->status);
        $this->assertStringContainsString('not configured', $record->note);
        $this->assertSame(1, CustomerMessage::count());
    }

    public function test_facebook_is_not_marked_sent_without_a_provider_reference(): void
    {
        config([
            'services.po_notifications.facebook_enabled' => true,
            'services.facebook.page_access_token' => 'test-token',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response([], 200),
        ]);
        $staff = User::factory()->create(['role' => 'employee']);
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $customerUser);
        $product = $this->makeProduct('Widget');

        $this->makeThread(null, [
            'assigned_user_id' => $staff->id,
            'channel' => 'facebook_messenger',
            'external_sender_id' => 'facebook-recipient',
        ]);

        $this->actingAsUser($customerUser)->post('/orders', [
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'product_id' => [$product->id],
            'product_search' => [''],
            'quantity' => [1],
        ]);

        $record = PurchaseOrderNotification::where('channel', 'facebook')->first();
        $this->assertNotNull($record);
        $this->assertSame('failed', $record->status);
        $this->assertStringContainsString('message reference', $record->note);
        $this->assertSame(1, CustomerMessage::count());
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
        $this->assertSame(0, CustomerMessage::count());
        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
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

        $this->assertSame(0, CustomerMessage::count());

        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertNull($record->external_reference);
    }

    public function test_every_order_lifecycle_update_texts_the_customer_when_sms_is_enabled(): void
    {
        config(['services.po_notifications.sms_enabled' => true, 'services.semaphore.api_key' => 'test-key']);
        Http::fake([
            'api.semaphore.co/*' => Http::response([['message_id' => 1, 'status' => 'Queued']]),
        ]);
        $user = User::factory()->create(['role' => 'customer', 'phone' => '09171234567']);
        $customer = $this->makeCustomer('Acme Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        OrderNotifications::updated($order, 'Quantity updated.');
        OrderNotifications::fulfillmentUpdated($order, 'Delivery updated.');
        OrderNotifications::completed($order);
        OrderNotifications::cancelled($order);
        OrderNotifications::received($order);

        Http::assertSentCount(5);
        $this->assertSame(
            5,
            PurchaseOrderNotification::query()
                ->where('purchase_order_id', $order->id)
                ->where('channel', 'sms')
                ->where('status', 'sent')
                ->count(),
        );
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

        $this->assertSame(0, CustomerMessage::count());

        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
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

        $this->assertSame(0, CustomerMessage::count());

        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
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

        $this->assertSame(0, CustomerMessage::count());

        $record = PurchaseOrderNotification::where('channel', 'portal')->first();
        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
    }
}
