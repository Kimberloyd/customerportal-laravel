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

    public function test_method_spoofed_modal_submission_updates_the_order(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => 'Updated from modal',
            "quantity_{$item->id}" => 7,
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame('Updated from modal', $order->fresh()->remarks);
        $this->assertSame(7, $item->fresh()->quantity);
    }

    public function test_modal_can_add_a_product_and_reduce_an_undelivered_quantity(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $existingProduct = $this->makeProduct('Existing product', ['id' => 11]);
        $newProduct = $this->makeProduct('New product', ['id' => 22, 'unit_price' => 12.50]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $existingProduct->id, 'quantity' => 5],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => '',
            'items' => [
                ['id' => $item->id, 'product_id' => null, 'quantity' => 3],
                ['id' => null, 'product_id' => $newProduct->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'product_name' => 'New product',
            'quantity' => 2,
            'line_total' => 25,
        ]);
    }

    public function test_modal_can_remove_an_undelivered_product(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $firstProduct = $this->makeProduct('Keep product', ['id' => 31]);
        $secondProduct = $this->makeProduct('Remove product', ['id' => 32]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $firstProduct->id, 'quantity' => 2],
            ['product_id' => $secondProduct->id, 'quantity' => 1],
        ]);
        [$keptItem, $removedItem] = $order->items->values()->all();

        $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => '',
            'items' => [
                ['id' => $keptItem->id, 'product_id' => null, 'quantity' => 2],
            ],
        ]);

        $this->assertDatabaseMissing('purchase_order_items', ['id' => $removedItem->id]);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $keptItem->id]);
    }

    public function test_modal_cannot_remove_a_product_with_delivered_units(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $deliveredProduct = $this->makeProduct('Delivered product', ['id' => 41]);
        $otherProduct = $this->makeProduct('Other product', ['id' => 42]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $deliveredProduct->id, 'quantity' => 2, 'delivered_quantity' => 1],
            ['product_id' => $otherProduct->id, 'quantity' => 1],
        ]);
        [$deliveredItem, $otherItem] = $order->items->values()->all();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => '',
            'items' => [
                ['id' => $otherItem->id, 'product_id' => null, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHas(
            'error',
            'Delivered product cannot be removed because 1 unit(s) have already been delivered. Keep this product in the order.',
        );
        $this->assertDatabaseHas('purchase_order_items', ['id' => $deliveredItem->id]);
    }

    public function test_modal_rejects_an_item_id_from_another_order(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Scoped product');
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $otherOrder = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 9],
        ]);
        $otherItem = $otherOrder->items->first();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => '',
            'items' => [
                ['id' => $otherItem->id, 'product_id' => null, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHas(
            'error',
            'One product line no longer matches this order. Refresh the page and try again.',
        );
        $this->assertSame(9, $otherItem->fresh()->quantity);
        $this->assertCount(1, $order->fresh()->items);
    }

    public function test_completed_unconfirmed_order_cannot_add_products(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $deliveredProduct = $this->makeProduct('Delivered product', ['id' => 51]);
        $newProduct = $this->makeProduct('Added product', ['id' => 52]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $deliveredProduct->id, 'quantity' => 2, 'delivered_quantity' => 2],
        ]);
        $order->completed_at = now();
        $order->save();
        $item = $order->items->first();

        $response = $this->actingAsUser($staff)->post("/orders/{$order->id}", [
            '_method' => 'put',
            'customer_id' => $customer->id,
            'remarks' => '',
            'items' => [
                ['id' => $item->id, 'product_id' => null, 'quantity' => 2],
                ['id' => null, 'product_id' => $newProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHas('error', 'This completed order can no longer be edited.');
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertCount(1, $order->items);
    }

    public function test_completed_order_cannot_be_edited(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer('Other Co');
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $product->id, 'quantity' => 5, 'delivered_quantity' => 5],
        ]);
        $item = $order->items->first();

        $this->actingAsUser($staff)
            ->get("/orders/{$order->id}/edit")
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('error', 'This completed order can no longer be edited.');

        $response = $this->actingAsUser($staff)->put("/orders/{$order->id}", [
            'customer_id' => $other->id,
            'remarks' => 'Closing note',
            "quantity_{$item->id}" => 99,
        ]);

        $response->assertSessionHas('error', 'This completed order can no longer be edited.');
        $order->refresh();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertNull($order->remarks);
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
