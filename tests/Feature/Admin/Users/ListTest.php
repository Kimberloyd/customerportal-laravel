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

    public function test_admin_is_redirected_to_the_accounts_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->get('/admin/users')
            ->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));
    }
}
