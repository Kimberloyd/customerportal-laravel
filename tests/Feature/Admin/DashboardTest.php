<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin dashboard's KPI tiles and recent-activity lists were removed
 * when the page became a shell around the Products / Customers / Accounts
 * panels, so the tests counting those props went with them. What remains
 * is the access control, which still applies, plus a smoke test that the
 * page renders its default tab.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->get('/admin')->assertStatus(403);
    }

    public function test_customer_gets_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/admin')->assertStatus(403);
    }

    public function test_admin_sees_the_products_tab_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('activeTab', 'products'));
    }
}
