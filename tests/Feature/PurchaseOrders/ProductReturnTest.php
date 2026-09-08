<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ProductReturnTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_owning_customer_can_request_a_return_for_delivered_products(): void
    {
        [$user, $order] = $this->receivedOrder();
        $item = $order->items->first();

        $response = $this->actingAsUser($user)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
        ]);

        $response->assertRedirect(route('purchase-orders.show', $order));
        $response->assertSessionHas('success', 'Return request sent. Our team will review it.');
        $this->assertDatabaseHas('product_returns', [
            'purchase_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'requested_by_user_id' => $user->id,
            'status' => ProductReturn::STATUS_REQUESTED,
            'reason' => 'The delivered packaging was damaged.',
        ]);
        $this->assertDatabaseHas('product_return_items', [
            'purchase_order_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('purchase_order_audits', [
            'purchase_order_id' => $order->id,
            'action' => 'Return Requested',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_customer_can_request_a_return_without_confirming_receipt(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $this->makeProduct()->id, 'quantity' => 2, 'delivered_quantity' => 2],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($user)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHas('success', 'Return request sent. Our team will review it.');
        $this->assertDatabaseCount('product_returns', 1);
    }

    public function test_customer_can_request_a_return_for_a_partially_delivered_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now(), [
            ['product_id' => $this->makeProduct()->id, 'quantity' => 5, 'delivered_quantity' => 2],
        ]);
        $item = $order->items->first();

        $response = $this->actingAsUser($user)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'One of the delivered units arrived damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHas('success', 'Return request sent. Our team will review it.');
        $this->assertDatabaseCount('product_returns', 1);
    }

    public function test_customer_can_request_a_return_regardless_of_how_long_ago_it_was_delivered(): void
    {
        [$user, $order] = $this->receivedOrder();
        $order->customer_received_at = now()->subDays(365);
        $order->save();
        $item = $order->items->first();

        $response = $this->actingAsUser($user)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHas('success', 'Return request sent. Our team will review it.');
        $this->assertDatabaseCount('product_returns', 1);
    }

    public function test_customer_cannot_request_more_than_the_delivered_quantity(): void
    {
        [$user, $order] = $this->receivedOrder();
        $item = $order->items->first();

        $this->actingAsUser($user)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 99]],
        ])->assertSessionHas('error', "{$item->display_name} can only be returned up to the {$item->delivered_quantity} unit(s) delivered.");

        $this->assertDatabaseCount('product_returns', 0);
    }

    public function test_only_the_owning_customer_can_request_a_return(): void
    {
        [$user, $order] = $this->receivedOrder();
        $otherUser = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Other Co', $otherUser);
        $item = $order->items->first();

        $this->actingAsUser($otherUser)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();

        $this->actingAsUser(User::factory()->create(['role' => 'office']))
            ->post(route('purchase-orders.returns.store', $order), [
                'reason' => 'The delivered packaging was damaged.',
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_staff_can_approve_then_record_a_return_as_received(): void
    {
        [$customerUser, $order] = $this->receivedOrder();
        $item = $order->items->first();
        $this->actingAsUser($customerUser)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
        ]);
        $return = ProductReturn::firstOrFail();
        $staff = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($staff)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_APPROVED,
            'review_note' => 'Please prepare the products for collection.',
        ])->assertSessionHas('success', 'Return request approved. Arrange collection or delivery with the customer.');

        $this->assertSame(ProductReturn::STATUS_APPROVED, $return->fresh()->status);
        $this->assertSame($staff->id, $return->fresh()->reviewed_by_user_id);
        $this->assertSame(1, $item->fresh()->delivered_quantity);

        $this->actingAsUser($staff)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_RECEIVED,
        ])->assertSessionHas('success', 'Returned products recorded as received.');

        $this->assertSame(ProductReturn::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertSame($staff->id, $return->fresh()->received_by_user_id);
        $this->assertSame(
            1,
            PurchaseOrderAudit::where('purchase_order_id', $order->id)
                ->where('action', 'Return Received')
                ->count(),
        );
    }

    public function test_approving_a_return_reopens_a_completed_order_for_redelivery(): void
    {
        [$customerUser, $order] = $this->receivedOrder();
        $item = $order->items->first();
        $this->actingAsUser($customerUser)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
        ]);
        $return = ProductReturn::firstOrFail();
        $staff = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($staff)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_APPROVED,
        ]);

        $freshItem = $item->fresh();
        $this->assertSame(1, $freshItem->delivered_quantity);
        $this->assertSame(2, $freshItem->pending_quantity);
        $this->assertSame(PurchaseOrder::STATUS_PARTIAL, $order->fresh()->status);
    }

    public function test_returning_every_delivered_unit_marks_the_order_returned_instead_of_submitted(): void
    {
        [$customerUser, $order] = $this->receivedOrder();
        $item = $order->items->first();
        $this->actingAsUser($customerUser)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The entire delivered batch was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 3]],
        ]);
        $return = ProductReturn::firstOrFail();
        $staff = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($staff)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_APPROVED,
        ]);

        $freshOrder = $order->fresh();
        $this->assertSame(0, $item->fresh()->delivered_quantity);
        $this->assertSame(PurchaseOrder::STATUS_RETURNED, $freshOrder->status);
        $this->assertNull($freshOrder->customer_received_at);
    }

    public function test_staff_must_explain_a_rejection_and_customers_cannot_review_returns(): void
    {
        [$customerUser, $order] = $this->receivedOrder();
        $item = $order->items->first();
        $this->actingAsUser($customerUser)->post(route('purchase-orders.returns.store', $order), [
            'reason' => 'The delivered packaging was damaged.',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ]);
        $return = ProductReturn::firstOrFail();

        $this->actingAsUser($customerUser)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_APPROVED,
        ])->assertForbidden();

        $staff = User::factory()->create(['role' => 'office']);
        $this->actingAsUser($staff)->put(route('purchase-orders.returns.update', $return), [
            'status' => ProductReturn::STATUS_REJECTED,
        ])->assertSessionHas('error', 'Explain why this return request cannot be approved.');

        $this->assertSame(ProductReturn::STATUS_REQUESTED, $return->fresh()->status);
    }

    public function test_customer_page_exposes_only_eligible_return_action(): void
    {
        [$user, $order] = $this->receivedOrder();

        $this->actingAsUser($user)->get(route('purchase-orders.show', $order))
            ->assertInertia(fn ($page) => $page
                ->where('canRequestReturn', true)
                ->where('canManageReturns', false)
                ->has('order.returns', 0)
                ->where('order.items.0.returnable_quantity', 3));
    }

    /** @return array{0: User, 1: PurchaseOrder} */
    private function receivedOrder(): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $this->makeProduct()->id, 'quantity' => 3, 'delivered_quantity' => 3],
        ]);
        $order->customer_received_at = now();
        $order->save();

        return [$user, $order->fresh('items')];
    }
}
