<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_company_dashboard_exposes_a_dense_year_of_order_activity(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $staff = User::factory()->create(['role' => 'employee']);
        $customer = Customer::create(['company_name' => 'Example Hospital', 'is_active' => true]);
        $order = $this->createOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDay());

        foreach ([now(), now(), now()->subDays(3)] as $createdAt) {
            PurchaseOrderAudit::create([
                'purchase_order_id' => $order->id,
                'action' => 'Order Submitted',
                'actor_user_id' => $staff->id,
                'actor_role' => 'employee',
                'created_at' => $createdAt,
            ]);
        }

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('companyCharts')
                ->loadDeferredProps('charts', fn ($charts) => $charts
                    // The whole calendar year, so the grid draws a complete
                    // Jan-Dec block instead of stopping at today. 2026 has 365 days.
                    ->has('companyCharts.order_activity', 365)
                    // Index 165 is 2026-06-15, the frozen "today".
                    ->where('companyCharts.order_activity.165.value', 2)
                    ->where('companyCharts.order_activity.165.t',
                        Carbon::parse('2026-06-15', 'UTC')->getTimestamp() * 1000)
                    ->where('companyCharts.order_activity.162.value', 1)
                    ->where('companyCharts.order_activity.164.value', 0)
                    // Oldest first, and never reaching back into last year.
                    ->where('companyCharts.order_activity.0.t',
                        Carbon::parse('2026-01-01', 'UTC')->getTimestamp() * 1000)
                    // Padded through December 31st, with days still to come at zero.
                    ->where('companyCharts.order_activity.364.t',
                        Carbon::parse('2026-12-31', 'UTC')->getTimestamp() * 1000)
                    ->where('companyCharts.order_activity.364.value', 0)
                    // The streak marker stays on today, not the padded tail.
                    ->where('companyCharts.activity_through',
                        Carbon::parse('2026-06-15', 'UTC')->getTimestamp() * 1000)));
    }

    public function test_company_dashboard_reports_fulfillment_lead_times_in_hours(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $staff = User::factory()->create(['role' => 'employee']);
        $customer = Customer::create(['company_name' => 'Example Hospital', 'is_active' => true]);

        $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, Carbon::parse('2026-06-10 08:00:00'))
            ->update(['completed_at' => Carbon::parse('2026-06-11 08:00:00')]);
        $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, Carbon::parse('2026-06-12 10:00:00'))
            ->update(['completed_at' => Carbon::parse('2026-06-12 12:30:00')]);
        // Still open, so it has no lead time to measure yet.
        $this->createOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDay());

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->loadDeferredProps('charts', fn ($charts) => $charts
                    ->has('companyCharts.lead_times', 2)
                    ->where('companyCharts.lead_times.0', 24)
                    ->where('companyCharts.lead_times.1', 2.5)));
    }

    public function test_company_dashboard_buckets_open_orders_by_age(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $staff = User::factory()->create(['role' => 'employee']);
        $customer = Customer::create(['company_name' => 'Example Hospital', 'is_active' => true]);

        $this->createOrder($customer, PurchaseOrder::STATUS_SUBMITTED, Carbon::parse('2026-06-14 09:00:00'));
        $this->createOrder($customer, PurchaseOrder::STATUS_REVIEWING, Carbon::parse('2026-06-11 09:00:00'));
        $this->createOrder($customer, PurchaseOrder::STATUS_PARTIAL, Carbon::parse('2026-06-07 09:00:00'));
        $this->createOrder($customer, PurchaseOrder::STATUS_PROCESSING, Carbon::parse('2026-05-20 09:00:00'));
        // Closed orders are not waiting on anyone.
        $this->createOrder($customer, PurchaseOrder::STATUS_COMPLETED, Carbon::parse('2026-06-14 09:00:00'));

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->loadDeferredProps('charts', fn ($charts) => $charts
                    ->has('companyCharts.open_order_aging', 4)
                    ->where('companyCharts.open_order_aging.0.bucket', '0-2 days')
                    ->where('companyCharts.open_order_aging.0.orders', 1)
                    ->where('companyCharts.open_order_aging.1.orders', 1)
                    ->where('companyCharts.open_order_aging.2.orders', 1)
                    ->where('companyCharts.open_order_aging.3.bucket', 'Over 10 days')
                    ->where('companyCharts.open_order_aging.3.orders', 1)));
    }

    public function test_company_dashboard_builds_reorder_cohorts(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $staff = User::factory()->create(['role' => 'employee']);
        $returning = Customer::create(['company_name' => 'Returning Hospital', 'is_active' => true]);
        $once = Customer::create(['company_name' => 'One Off Clinic', 'is_active' => true]);

        // Cohort Apr 2026: ordered again two months later, skipping May.
        $this->createOrder($returning, PurchaseOrder::STATUS_COMPLETED, Carbon::parse('2026-04-10 09:00:00'));
        $this->createOrder($returning, PurchaseOrder::STATUS_SUBMITTED, Carbon::parse('2026-06-02 09:00:00'));
        // Same cohort, never came back.
        $this->createOrder($once, PurchaseOrder::STATUS_COMPLETED, Carbon::parse('2026-04-12 09:00:00'));

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->loadDeferredProps('charts', fn ($charts) => $charts
                    ->has('companyCharts.reorder_cohorts', 1)
                    ->where('companyCharts.reorder_cohorts.0.label', 'Apr 2026')
                    ->where('companyCharts.reorder_cohorts.0.size', 2)
                    // Placing the cohort is 100%; May empty; June only the returner.
                    ->where('companyCharts.reorder_cohorts.0.retention', [100, 0, 50])));
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
