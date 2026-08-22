<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CancelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_owning_customer_can_cancel_their_own_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $response = $this->actingAsUser($user)->post("/orders/{$order->id}/cancel");

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_blocked_once_terminal(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 1, 'delivered_quantity' => 1],
        ]);

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}/cancel");

        $response->assertSessionHas('error', 'This order is already completed and cannot be cancelled.');
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_staff_can_cancel_any_order(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 5, 'delivered_quantity' => 2],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/cancel");

        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_audit_row_exact_text(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/cancel");

        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Order Cancelled', $audit->action);
        $this->assertSame('Order status changed to Cancelled.', $audit->details);
    }

    public function test_non_owning_customer_cannot_cancel(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->post("/orders/{$order->id}/cancel")->assertStatus(403);
    }
}
