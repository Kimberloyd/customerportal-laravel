<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_agent_gets_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAsUser($agent)->get('/admin/users')->assertStatus(403);
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
        User::factory()->create(['role' => 'agent']);
        User::factory()->create(['role' => 'customer']);

        $response = $this->actingAsUser($admin)->get('/admin/users?role=customer');

        $response->assertInertia(fn ($page) => $page->has('users.data', 1));
    }
}
