<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_rejects_quantity_below_already_delivered(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 5],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $customer->id,
            'remarks' => '',
            "quantity_{$item->id}" => 3,
        ]);

        $response->assertRedirect();
        $this->assertSame(10, $item->fresh()->quantity);
    }

    public function test_rejects_quantity_less_than_one(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $customer->id,
            'remarks' => '',
            "quantity_{$item->id}" => 0,
        ]);

        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_no_op_edit_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $customer->id,
            'remarks' => $order->remarks ?? '',
            "quantity_{$item->id}" => 5,
        ]);

        $response->assertSessionHas('error', 'Nothing changed. Update at least one order detail before saving.');
        $this->assertSame(0, PurchaseOrderAudit::count());
    }

    public function test_terminal_order_only_allows_remarks_change(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 5, 'delivered_quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $other->id,
            'remarks' => 'Closing note',
            "quantity_{$item->id}" => 99,
        ]);

        $order->refresh();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('Closing note', $order->remarks);
        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_change_list_and_audit_details_match_exactly(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Acme Co');
        $newCustomer = $this->makeCustomer('New Co');
        $product = $this->makeProduct('Widget A');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $newCustomer->id,
            'remarks' => 'Updated remarks',
            "quantity_{$item->id}" => 8,
        ]);

        $audit = PurchaseOrderAudit::first();
        $this->assertSame('Order Updated', $audit->action);
        $this->assertSame(
            'Customer changed from Acme Co to New Co. Widget A quantity changed from 5 to 8. Remarks updated.',
            $audit->details
        );
    }

    public function test_quantity_edit_recomputes_status_to_completed(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $product->id, 'quantity' => 10, 'delivered_quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $customer->id,
            'remarks' => '',
            "quantity_{$item->id}" => 5,
        ]);

        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_orphaned_customer_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->actingAsUser($user)->get("/orders/{$order->id}/edit")->assertStatus(403);
    }
}
