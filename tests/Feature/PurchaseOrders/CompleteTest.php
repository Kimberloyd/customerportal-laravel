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
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_blocked_if_already_terminal(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, now(), [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $response = $this->actingAsUser($staff)->post("/purchase-orders/{$order->id}/complete");

        $response->assertSessionHas('error', 'This order is already cancelled and cannot be completed.');
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->actingAsUser($user)->post("/purchase-orders/{$order->id}/complete")->assertStatus(403);
    }

    public function test_marks_every_item_fully_delivered_and_writes_audit(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $productA = $this->makeProduct('A');
        $productB = $this->makeProduct('B');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $productA->id, 'quantity' => 10, 'delivered_quantity' => 3],
            ['product_id' => $productB->id, 'quantity' => 4, 'delivered_quantity' => 0],
        ]);

        $response = $this->actingAsUser($staff)->post("/purchase-orders/{$order->id}/complete");

        $response->assertRedirect(route('purchase-orders.index'));
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
        foreach ($order->items as $item) {
            $this->assertSame($item->quantity, $item->delivered_quantity);
        }

        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Order Completed', $audit->action);
        $this->assertSame('All ordered quantities were marked delivered.', $audit->details);
    }
}
