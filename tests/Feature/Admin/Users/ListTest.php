<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->get('/admin/users')->assertStatus(403);
    }

    public function test_customer_gets_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($customer)->get('/admin/users')->assertStatus(403);
    }

    public function test_search_matches_name_or_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['full_name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['full_name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $response = $this->actingAsUser($admin)->get('/admin/users?search=jane');

        $response->assertInertia(fn ($page) => $page->has('users.data', 1));
    }

    public function test_role_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'employee']);
        User::factory()->create(['role' => 'customer']);

        $response = $this->actingAsUser($admin)->get('/admin/users?role=customer');

        $response->assertInertia(fn ($page) => $page->has('users.data', 1));
    }
}
