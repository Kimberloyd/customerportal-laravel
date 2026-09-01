<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ConfirmReceivedTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_owning_customer_can_confirm_a_completed_order_was_received(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());

        $response = $this->actingAsUser($user)
            ->post(route('purchase-orders.confirm-received', $order));

        $response
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('success', 'Order received. Thank you for confirming delivery.');
        $this->assertNotNull($order->fresh()->customer_received_at);
        $this->assertDatabaseHas('purchase_order_audits', [
            'purchase_order_id' => $order->id,
            'action' => 'Order Received',
            'actor_user_id' => $user->id,
            'actor_role' => 'customer',
        ]);
    }

    public function test_customer_cannot_confirm_another_customers_order(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->makeCustomer('Own Co', $user);
        $otherCustomer = $this->makeCustomer('Other Co');
        $order = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_COMPLETED, now());

        $this->actingAsUser($user)
            ->post(route('purchase-orders.confirm-received', $order))
            ->assertForbidden();

        $this->assertNull($order->fresh()->customer_received_at);
    }

    public function test_staff_cannot_confirm_receipt_for_a_customer(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());

        $this->actingAsUser($staff)
            ->post(route('purchase-orders.confirm-received', $order))
            ->assertForbidden();

        $this->assertNull($order->fresh()->customer_received_at);
    }

    public function test_customer_cannot_confirm_receipt_before_fulfillment_is_complete(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now());

        $this->actingAsUser($user)
            ->post(route('purchase-orders.confirm-received', $order))
            ->assertSessionHas(
                'error',
                'This order can be marked as received after all items have been delivered.',
            );

        $this->assertNull($order->fresh()->customer_received_at);
    }

    public function test_repeated_confirmation_keeps_the_original_time_and_one_audit_entry(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());

        $this->actingAsUser($user)->post(route('purchase-orders.confirm-received', $order));
        $receivedAt = $order->fresh()->customer_received_at;

        $response = $this->actingAsUser($user)
            ->post(route('purchase-orders.confirm-received', $order));

        $response->assertSessionHas('success', 'This order was already marked as received.');
        $this->assertTrue($receivedAt->equalTo($order->fresh()->customer_received_at));
        $this->assertSame(
            1,
            PurchaseOrderAudit::where('purchase_order_id', $order->id)
                ->where('action', 'Order Received')
                ->count(),
        );
    }

    public function test_customer_page_exposes_confirmation_only_for_unconfirmed_completed_orders(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());

        $this->actingAsUser($user)
            ->get(route('purchase-orders.show', $order))
            ->assertInertia(fn ($page) => $page
                ->where('canConfirmReceived', true)
                ->where('order.can_edit_items', false)
                ->where('order.customer_received_at', null));

        $order->customer_received_at = now();
        $order->save();

        $this->actingAsUser($user)
            ->get(route('purchase-orders.show', $order))
            ->assertInertia(fn ($page) => $page
                ->where('canConfirmReceived', false)
                ->where('order.can_edit_items', false)
                ->where('order.customer_received_at', fn ($value) => is_string($value)));
    }

    public function test_received_order_has_a_customer_facing_list_status(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now());
        $order->customer_received_at = now();
        $order->save();

        $this->actingAsUser($user)
            ->get(route('purchase-orders.index'))
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.status', PurchaseOrder::STATUS_COMPLETED)
                ->where('orders.data.0.display_status', 'received'));
    }
}
