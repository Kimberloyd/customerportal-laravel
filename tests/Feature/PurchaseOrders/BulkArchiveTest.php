<?php

namespace Tests\Feature\PurchaseOrders;

use App\Events\PurchaseOrderChanged;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class BulkArchiveTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_admin_can_archive_several_orders_in_one_request(): void
    {
        Event::fake([PurchaseOrderChanged::class]);

        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $orderA = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $orderB = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now());
        $untouched = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)
            ->post(route('purchase-orders.bulk-destroy'), [
                'order_ids' => [$orderA->public_id, $orderB->public_id],
            ])
            ->assertRedirect(route('purchase-orders.index'))
            ->assertSessionHas('success', '2 orders archived.');

        $this->assertSoftDeleted('purchase_orders', ['id' => $orderA->id]);
        $this->assertSoftDeleted('purchase_orders', ['id' => $orderB->id]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $untouched->id, 'deleted_at' => null]);

        $this->assertDatabaseHas('purchase_order_audits', ['purchase_order_id' => $orderA->id, 'action' => 'Order Archived']);
        $this->assertDatabaseHas('purchase_order_audits', ['purchase_order_id' => $orderB->id, 'action' => 'Order Archived']);
        $this->assertSame(1, PurchaseOrderAudit::where('purchase_order_id', $orderA->id)->where('action', 'Order Archived')->count());
        $this->assertSame(1, PurchaseOrderAudit::where('purchase_order_id', $orderB->id)->where('action', 'Order Archived')->count());

        Event::assertDispatched(
            PurchaseOrderChanged::class,
            fn (PurchaseOrderChanged $event) => $event->orderId === $orderA->id && $event->change === 'archived',
        );
        Event::assertDispatched(
            PurchaseOrderChanged::class,
            fn (PurchaseOrderChanged $event) => $event->orderId === $orderB->id && $event->change === 'archived',
        );
    }

    public function test_a_single_order_is_reported_in_the_singular(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)
            ->post(route('purchase-orders.bulk-destroy'), ['order_ids' => [$order->public_id]])
            ->assertSessionHas('success', '1 order archived.');
    }

    public function test_office_cannot_bulk_archive_orders(): void
    {
        $agent = User::factory()->create(['role' => 'office']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($agent)
            ->post(route('purchase-orders.bulk-destroy'), ['order_ids' => [$order->public_id]])
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_customers_cannot_bulk_archive_orders(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($customerUser)
            ->post(route('purchase-orders.bulk-destroy'), ['order_ids' => [$order->public_id]])
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_agent_can_bulk_archive_their_own_customers_orders(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $customer = $this->makeCustomer();
        $customer->update(['assigned_employee_id' => $agent->id]);
        $orderA = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());
        $orderB = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now());

        $this->actingAsUser($agent)
            ->post(route('purchase-orders.bulk-destroy'), [
                'order_ids' => [$orderA->public_id, $orderB->public_id],
            ])
            ->assertRedirect(route('purchase-orders.index'))
            ->assertSessionHas('success', '2 orders archived.');

        $this->assertSoftDeleted('purchase_orders', ['id' => $orderA->id]);
        $this->assertSoftDeleted('purchase_orders', ['id' => $orderB->id]);
    }

    public function test_agent_cannot_bulk_archive_an_order_outside_their_customers(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $otherAgent = User::factory()->create(['role' => 'agent']);
        $ownCustomer = $this->makeCustomer('Own Co');
        $ownCustomer->update(['assigned_employee_id' => $agent->id]);
        $otherCustomer = $this->makeCustomer('Other Co');
        $otherCustomer->update(['assigned_employee_id' => $otherAgent->id]);
        $ownOrder = $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $otherOrder = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($agent)
            ->post(route('purchase-orders.bulk-destroy'), [
                'order_ids' => [$ownOrder->public_id, $otherOrder->public_id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $ownOrder->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $otherOrder->id, 'deleted_at' => null]);
    }

    public function test_empty_order_ids_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->post(route('purchase-orders.bulk-destroy'), ['order_ids' => []])
            ->assertSessionHasErrors('order_ids');
    }

    public function test_unknown_public_id_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)
            ->post(route('purchase-orders.bulk-destroy'), [
                'order_ids' => [$order->public_id, 'not-a-real-public-id'],
            ])
            ->assertSessionHasErrors('order_ids.1');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'deleted_at' => null]);
    }
}
