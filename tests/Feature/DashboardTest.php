<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_sees_the_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_agent_sees_the_dashboard(): void
    {
        $agent = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($agent)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_office_sees_the_company_dashboard(): void
    {
        $office = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($office)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('workspace.is_customer', false));
    }

    public function test_customer_sees_the_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_customer_metrics_trends_and_order_lists_are_scoped_to_their_account(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'));
        $user = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Company', $user);
        $other = $this->makeCustomer('Private Company');
        $partial = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, '2026-09-07 10:00:00', [
            ['quantity' => 10, 'delivered_quantity' => 4, 'line_total' => 100],
        ]);
        $completed = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, '2026-09-01 00:00:00', [
            ['quantity' => 5, 'delivered_quantity' => 5, 'line_total' => 50],
        ]);
        $completed->update(['completed_at' => '2026-09-05 09:00:00']);
        $this->makeOrder($customer, PurchaseOrder::STATUS_CANCELLED, '2026-09-03 00:00:00', [
            ['quantity' => 100, 'delivered_quantity' => 0, 'line_total' => 999],
        ]);
        $previous = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, '2026-08-31 23:59:59', [
            ['quantity' => 2, 'delivered_quantity' => 2, 'line_total' => 20],
        ]);
        $previous->customer_received_at = now();
        $previous->save();
        $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['quantity' => 999, 'line_total' => 9999],
        ]);

        $this->actingAsUser($user)->get('/dashboard?period=7')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('workspace.name', 'Own Company')
                ->where('workspace.is_customer', true)
                ->where('dashboard.current.orders', 3)
                ->where('dashboard.current.value', 150)
                ->where('dashboard.current.ordered_units', 15)
                ->where('dashboard.current.delivered_units', 9)
                ->where('dashboard.current.fulfillment', 60)
                ->where('dashboard.current.completed', 1)
                ->where('dashboard.previous.orders', 1)
                ->where('dashboard.previous.value', 20)
                ->where('dashboard.previous.fulfillment', 100)
                ->where('dashboard.trend.0.date', '2026-09-01')
                ->where('dashboard.trend.0.current', 1)
                ->where('dashboard.trend.4.delivered', 1)
                ->where('dashboard.trend', fn ($points) => collect($points)->sum('current') === 3)
                ->where('dashboard.trend', fn ($points) => collect($points)->sum('delivered') === 1)
                ->where('dashboard.attention_count', 2)
                ->where('dashboard.attention.0.id', $completed->id)
                ->where('dashboard.attention.1.id', $partial->id)
                ->has('dashboard.recent', 3));
    }

    public function test_company_dashboard_includes_all_customers_and_prioritizes_submitted_orders(): void
    {
        $this->freezeTime();
        $agent = User::factory()->create(['role' => 'office']);
        $first = $this->makeCustomer('First Company');
        $second = $this->makeCustomer('Second Company');
        $this->makeOrder($first, PurchaseOrder::STATUS_PARTIAL, now()->subDays(4));
        $submitted = $this->makeOrder($second, PurchaseOrder::STATUS_SUBMITTED, now());
        $old = $this->makeOrder($first, PurchaseOrder::STATUS_PARTIAL, now()->subDays(100));
        $this->makeOrder($first, PurchaseOrder::STATUS_COMPLETED, now());

        $this->actingAsUser($agent)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('workspace.is_customer', false)
                ->where('dashboard.current.orders', 3)
                ->where('dashboard.attention_count', 3)
                ->where('dashboard.attention.0.id', $submitted->id)
                ->where('dashboard.attention.1.id', $old->id)
                ->has('dashboard.recent', 3));
    }

    public function test_missing_or_inactive_customer_profiles_never_expose_company_data(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $other = $this->makeCustomer('Private Company');
        $this->makeOrder($other, PurchaseOrder::STATUS_SUBMITTED, now());

        foreach (['missing', 'inactive'] as $scenario) {
            if ($scenario === 'inactive') {
                $this->makeCustomer('Inactive Company', $user)->update(['is_active' => false]);
            }

            $this->actingAsUser($user)->get('/dashboard')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('workspace.can_order', false)
                    ->where('dashboard.current.orders', 0)
                    ->where('dashboard.current.fulfillment', null)
                    ->where('dashboard.attention_count', 0)
                    ->has('dashboard.recent', 0)->has('dashboard.attention', 0));
        }
    }

    public function test_date_ranges_are_bounded_and_daily_series_include_zero_days(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        foreach (['7' => 7, '30' => 30, '90' => 90, '100000' => 30, 'bad' => 30, '0' => 30] as $period => $days) {
            $this->actingAsUser($user)->get('/dashboard?period='.$period)->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('dashboard.period', $days)
                    ->has('dashboard.trend', $days)
                    ->where('dashboard.current.orders', 0)
                    ->where('dashboard.current.value', 0)
                    ->where('dashboard.trend.0.current', 0));
        }
        $this->actingAsUser($user)->get('/dashboard?period[]=7')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.period', 30));
    }
}
