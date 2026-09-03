<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_staff_sees_the_company_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = Customer::create([
            'company_name' => 'Example Hospital',
            'is_active' => true,
        ]);

        $submitted = $this->createOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subHours(4));
        $this->createOrder($customer, PurchaseOrder::STATUS_REVIEWING, now()->subHours(3));
        $partial = $this->createOrder($customer, PurchaseOrder::STATUS_PARTIAL, now()->subHours(2), 10, 4);
        $completed = $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, now()->subHour(), 5, 5);
        $completed->update(['completed_at' => now()]);

        PurchaseOrderAudit::create([
            'purchase_order_id' => $partial->id,
            'action' => 'Fulfillment Updated',
            'details' => '4 unit(s) delivered.',
            'actor_user_id' => $staff->id,
            'actor_role' => 'employee',
            'created_at' => now(),
        ]);

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('companyDashboard.summary.submitted', 1)
                ->where('companyDashboard.summary.reviewing', 1)
                ->where('companyDashboard.summary.partial', 1)
                ->where('companyDashboard.summary.completed_today', 1)
                ->has('companyDashboard.metrics', 4)
                ->where('companyDashboard.metrics.0.label', 'Orders submitted')
                ->where('companyDashboard.metrics.0.value', 4)
                ->where('companyDashboard.metrics.3.label', 'Order updates')
                ->where('companyDashboard.metrics.3.value', 1)
                ->has('companyDashboard.needs_attention', 3)
                ->where('companyDashboard.needs_attention.0.id', $submitted->id)
                ->where('companyDashboard.needs_attention.2.delivered_units', 4)
                ->where('companyDashboard.needs_attention.2.balance_units', 6)
                ->has('companyDashboard.recent_orders', 4)
                ->where('companyDashboard.recent_orders.0.id', $completed->id)
                ->has('companyDashboard.recent_activity', 1)
                ->where('companyDashboard.recent_activity.0.action', 'Fulfillment Updated')
                ->where('companyDashboard.recent_activity.0.actor_name', $staff->full_name));
    }

    public function test_admin_sees_the_company_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('companyDashboard.summary.submitted', 0)
                ->has('companyDashboard.needs_attention', 0)
                ->has('companyDashboard.recent_orders', 0)
                ->has('companyDashboard.recent_activity', 0));
    }

    public function test_customer_sees_a_dashboard_scoped_to_their_orders(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'company_name' => 'Customer Hospital',
            'is_active' => true,
        ]);
        $otherCustomer = Customer::create([
            'company_name' => 'Other Hospital',
            'is_active' => true,
        ]);

        $submitted = $this->createOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subHours(4));
        $partial = $this->createOrder($customer, PurchaseOrder::STATUS_PARTIAL, now()->subHours(3), 10, 4);
        $readyToConfirm = $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, now()->subHours(2), 5, 5);
        $readyToConfirm->completed_at = now()->subHour();
        $readyToConfirm->save();
        $received = $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, now()->subHour(), 3, 3);
        $received->completed_at = now()->subMinutes(30);
        $received->customer_received_at = now()->subMinutes(15);
        $received->save();
        $this->createOrder($otherCustomer, PurchaseOrder::STATUS_SUBMITTED, now());

        PurchaseOrderAudit::create([
            'purchase_order_id' => $submitted->id,
            'action' => 'Order Reviewing',
            'created_at' => now(),
        ]);
        PurchaseOrderAudit::create([
            'purchase_order_id' => $received->id,
            'action' => 'Fulfillment Updated',
            'created_at' => now()->subDays(31),
        ]);

        $this->actingAsUser($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->missing('companyDashboard')
                ->where('customerDashboard.linked', true)
                ->where('customerDashboard.customer_name', 'Customer Hospital')
                ->where('customerDashboard.summary.active', 2)
                ->where('customerDashboard.summary.in_progress', 1)
                ->where('customerDashboard.summary.ready_to_confirm', 1)
                ->where('customerDashboard.summary.received', 1)
                ->has('customerDashboard.metrics', 4)
                ->where('customerDashboard.metrics.0.label', 'Orders submitted')
                ->where('customerDashboard.metrics.0.value', 4)
                ->where('customerDashboard.metrics.0.previous', 0)
                ->where('customerDashboard.metrics.3.label', 'Order updates')
                ->where('customerDashboard.metrics.3.value', 1)
                ->where('customerDashboard.metrics.3.previous', 1)
                ->has('customerDashboard.action_required', 1)
                ->where('customerDashboard.action_required.0.id', $readyToConfirm->id)
                ->has('customerDashboard.active_orders', 2)
                ->where('customerDashboard.active_orders.0.id', $partial->id)
                ->where('customerDashboard.active_orders.1.id', $submitted->id)
                ->has('customerDashboard.recent_orders', 4)
                ->where('customerDashboard.recent_orders.0.id', $received->id));
    }

    public function test_customer_without_a_customer_link_sees_setup_guidance(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->missing('companyDashboard')
                ->where('customerDashboard.linked', false)
                ->where('customerDashboard.summary.active', 0)
                ->has('customerDashboard.action_required', 0)
                ->has('customerDashboard.active_orders', 0)
                ->has('customerDashboard.recent_orders', 0));
    }

    private function createOrder(
        Customer $customer,
        string $status,
        \DateTimeInterface $submittedAt,
        int $orderedUnits = 1,
        int $deliveredUnits = 0,
    ): PurchaseOrder {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'customer_id' => $customer->id,
            'status' => $status,
            'submitted_at' => $submittedAt,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'product_name' => 'Test Product',
            'quantity' => $orderedUnits,
            'delivered_quantity' => $deliveredUnits,
            'unit_price' => 0,
        ]);

        return $order;
    }
}
