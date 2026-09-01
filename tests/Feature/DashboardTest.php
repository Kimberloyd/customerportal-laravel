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

    public function test_customer_sees_the_empty_dashboard_without_needing_a_customer_link(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->missing('companyDashboard'));
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
