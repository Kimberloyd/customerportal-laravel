<?php

namespace Tests\Feature;

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

    public function test_staff_sees_the_empty_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->missing('kpis')
                ->missing('recentOrders')
                ->missing('monthlyVolume')
                ->missing('topProducts')
                ->missing('pendingOrders'));
    }

    public function test_admin_sees_the_empty_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->missing('kpis'));
    }

    public function test_customer_sees_the_empty_dashboard_without_needing_a_customer_link(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->missing('kpis'));
    }
}
