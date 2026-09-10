<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CompleteTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_blocked_if_already_terminal(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now(), [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $response = $this->actingAsUser($user)->post("/orders/{$order->public_id}/complete");

        $response->assertSessionHas('error', 'This order is already cancelled and cannot be closed.');
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_staff_role_gets_403(): void
    {
        $staff = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PROCESSING, now(), [
            ['product_id' => $product->id, 'quantity' => 3, 'delivered_quantity' => 3],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->public_id}/complete")->assertStatus(403);
    }

    public function test_non_owning_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_PROCESSING, now(), [
            ['product_id' => $product->id, 'quantity' => 3, 'delivered_quantity' => 3],
        ]);

        $this->actingAsUser($user)->post("/orders/{$order->public_id}/complete")->assertStatus(403);
    }

    public function test_unsettled_order_cannot_be_closed(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 3],
        ]);

        $response = $this->actingAsUser($user)->post("/orders/{$order->public_id}/complete");

        $response->assertSessionHas('error', 'Every item must be delivered before closing this order.');
        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $order->fresh()->status);
        $this->assertSame(3, $order->items->first()->fresh()->delivered_quantity);
        $this->assertDatabaseMissing('purchase_order_audits', [
            'purchase_order_id' => $order->id,
            'action' => 'Order Closed',
        ]);
    }

    public function test_owning_customer_can_close_a_fully_delivered_order_and_writes_audit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PROCESSING, now(), [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 10],
        ]);

        $response = $this->actingAsUser($user)->post("/orders/{$order->public_id}/complete");

        $response->assertRedirect(route('purchase-orders.show', $order));
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->customer_received_at);
        $this->assertSame(10, $order->items->first()->delivered_quantity);

        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Order Closed', $audit->action);
        $this->assertSame('The customer confirmed delivery and closed the fully delivered order.', $audit->details);
    }
}
