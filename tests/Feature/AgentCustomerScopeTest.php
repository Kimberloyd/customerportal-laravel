<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class AgentCustomerScopeTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_agent_sees_their_own_customer_and_a_teammates_but_not_unassigned_or_another_agents(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $teammate = User::factory()->create(['role' => User::ROLE_AGENT]);
        $otherAgent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $team = Team::create(['name' => 'North Team']);
        $team->members()->attach([$agent->id, $teammate->id]);

        $ownCustomer = $this->makeCustomer('Alpha Customer');
        $ownCustomer->update(['assigned_employee_id' => $agent->id]);
        $teamCustomer = $this->makeCustomer('Bravo Customer');
        $teamCustomer->update(['assigned_employee_id' => $teammate->id]);
        $otherCustomer = $this->makeCustomer('Charlie Customer');
        $otherCustomer->update(['assigned_employee_id' => $otherAgent->id]);
        $unassignedCustomer = $this->makeCustomer('Delta Customer');

        $ownOrder = $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $teamOrder = $this->makeOrder($teamCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $otherOrder = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $unassignedOrder = $this->makeOrder($unassignedCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($agent)->get(route('purchase-orders.index'));
        $response->assertInertia(fn ($page) => $page
            ->missing('orders')
            ->has('createOrderCustomers', 2)
            ->where('createOrderCustomers.0.id', $ownCustomer->id)
            ->where('createOrderCustomers.1.id', $teamCustomer->id)
            ->loadDeferredProps('orders', fn ($deferred) => $deferred
                ->where('orders.total', 2)));

        $this->actingAsUser($agent)->get(route('purchase-orders.show', $ownOrder))->assertOk();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $teamOrder))->assertOk();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $unassignedOrder))->assertForbidden();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $otherOrder))->assertForbidden();
    }

    public function test_an_agent_with_no_team_only_sees_their_own_customer(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $otherAgent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $ownCustomer = $this->makeCustomer('Solo Customer');
        $ownCustomer->update(['assigned_employee_id' => $agent->id]);
        $otherCustomer = $this->makeCustomer('Not Mine');
        $otherCustomer->update(['assigned_employee_id' => $otherAgent->id]);
        $unassignedCustomer = $this->makeCustomer('Nobody Yet');

        $ownOrder = $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $otherOrder = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $unassignedOrder = $this->makeOrder($unassignedCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($agent)->get(route('purchase-orders.show', $ownOrder))->assertOk();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $unassignedOrder))->assertForbidden();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $otherOrder))->assertForbidden();
    }

    public function test_admin_and_office_still_see_unassigned_customers_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $office = User::factory()->create(['role' => 'office']);
        $unassignedCustomer = $this->makeCustomer('Nobody Yet');
        $order = $this->makeOrder($unassignedCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)->get(route('purchase-orders.show', $order))->assertOk();
        $this->actingAsUser($office)->get(route('purchase-orders.show', $order))->assertOk();
    }

    public function test_admin_and_office_still_see_every_customers_orders_regardless_of_assignment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $office = User::factory()->create(['role' => 'office']);
        $someAgent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $customer = $this->makeCustomer('Assigned Elsewhere');
        $customer->update(['assigned_employee_id' => $someAgent->id]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now());

        $this->actingAsUser($admin)->get(route('purchase-orders.show', $order))->assertOk();
        $this->actingAsUser($office)->get(route('purchase-orders.show', $order))->assertOk();
    }

    public function test_agent_notification_and_message_badges_are_scoped_to_their_visible_customers(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $otherAgent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $ownCustomer = $this->makeCustomer('Own Customer');
        $ownCustomer->update(['assigned_employee_id' => $agent->id]);
        $otherCustomer = $this->makeCustomer('Other Customer');
        $otherCustomer->update(['assigned_employee_id' => $otherAgent->id]);
        $ownOrder = $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $otherOrder = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        foreach ([$ownOrder, $otherOrder] as $order) {
            PurchaseOrderNotification::create([
                'purchase_order_id' => $order->id,
                'channel' => 'portal',
                'status' => 'sent',
                'note' => 'Update',
                'created_at' => now(),
            ]);
        }
        $this->makeThread($ownCustomer, ['sender_type' => 'customer', 'is_read' => false]);
        $this->makeThread($otherCustomer, ['sender_type' => 'customer', 'is_read' => false]);

        // Only the own-customer notification/message counts, not the other agent's.
        $this->actingAsUser($agent)->getJson(route('notifications.recent'))
            ->assertOk()
            ->assertJsonPath('count', 1);
        $this->actingAsUser($agent)->getJson(route('messages.unread-count'))
            ->assertOk()
            ->assertJsonPath('count', 1);
    }
}
