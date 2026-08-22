<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ReceiveTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_rejects_a_single_item_exceeding_pending_with_no_partial_commit(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $productA = $this->makeProduct('A');
        $productB = $this->makeProduct('B');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $productA->id, 'quantity' => 5, 'delivered_quantity' => 0],
            ['product_id' => $productB->id, 'quantity' => 5, 'delivered_quantity' => 0],
        ]);
        [$itemA, $itemB] = $order->items;

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$itemA->id}" => 3,
            "received_{$itemB->id}" => 999,
        ]);

        $response->assertSessionHas('error', 'Received quantity cannot exceed pending quantity.');
        $this->assertSame(0, $itemA->fresh()->delivered_quantity);
        $this->assertSame(0, $itemB->fresh()->delivered_quantity);
    }

    public function test_rejects_all_zero_submission(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$item->id}" => 0,
        ]);

        $response->assertSessionHas('error', 'Enter at least one received quantity.');
    }

    public function test_status_transitions_submitted_to_partial_to_completed(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 10],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$item->id}" => 4,
        ]);
        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $order->fresh()->status);

        $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$item->id}" => 6,
        ]);
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_audit_details_lists_only_items_that_received_units(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $productA = $this->makeProduct('Widget A');
        $productB = $this->makeProduct('Widget B');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $productA->id, 'quantity' => 5],
            ['product_id' => $productB->id, 'quantity' => 5],
        ]);
        [$itemA, $itemB] = $order->items;

        $this->actingAsUser($staff)->post("/orders/{$order->id}/receive", [
            "received_{$itemA->id}" => 2,
            "received_{$itemB->id}" => 0,
        ]);

        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Fulfillment Updated', $audit->action);
        $this->assertSame('Widget A: 2 unit(s) delivered.', $audit->details);
    }

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($user)->post("/orders/{$order->id}/receive", [
            "received_{$item->id}" => 1,
        ])->assertStatus(403);
    }
}
