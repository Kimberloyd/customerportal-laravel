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

    public function test_agent_can_access_their_own_and_team_customers_but_not_other_customers(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $teammate = User::factory()->create(['role' => User::ROLE_AGENT]);
        $otherAgent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $team = Team::create(['name' => 'North']);
        $team->members()->attach([$agent->id, $teammate->id]);

        $ownCustomer = $this->makeCustomer('Own Customer');
        $ownCustomer->update(['assigned_employee_id' => $agent->id]);
        $teamCustomer = $this->makeCustomer('Team Customer');
        $teamCustomer->update(['assigned_employee_id' => $teammate->id]);
        $otherCustomer = $this->makeCustomer('Other Customer');
        $otherCustomer->update(['assigned_employee_id' => $otherAgent->id]);

        $ownOrder = $this->makeOrder($ownCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $teamOrder = $this->makeOrder($teamCustomer, PurchaseOrder::STATUS_SUBMITTED, now());
        $otherOrder = $this->makeOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        $response = $this->actingAsUser($agent)->get(route('purchase-orders.index'));
        $response->assertInertia(fn ($page) => $page
            ->missing('orders')
            ->where('createOrderCustomers.0.id', $ownCustomer->id)
            ->where('createOrderCustomers.1.id', $teamCustomer->id)
            ->loadDeferredProps('orders', fn ($deferred) => $deferred
                ->where('orders.total', 2)));

        $this->actingAsUser($agent)->get(route('purchase-orders.show', $ownOrder))->assertOk();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $teamOrder))->assertOk();
        $this->actingAsUser($agent)->get(route('purchase-orders.show', $otherOrder))->assertForbidden();
    }

    public function test_agent_notification_and_message_badges_only_include_team_customers(): void
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

        $this->actingAsUser($agent)->getJson(route('notifications.recent'))
            ->assertOk()
            ->assertJsonPath('count', 1);
        $this->actingAsUser($agent)->getJson(route('messages.unread-count'))
            ->assertOk()
            ->assertJsonPath('count', 1);
    }
}
