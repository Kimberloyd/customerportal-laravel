<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class AgentCustomerAccountTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_agent_creates_customer_account_and_is_assigned_to_customer(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $customer = $this->makeCustomer('North Clinic');

        $this->actingAsUser($agent)->post('/customer-accounts', [
            'full_name' => 'North Clinic User', 'email' => 'north@example.com', 'phone' => '09171234567',
            'password' => 'password123', 'password_confirmation' => 'password123', 'customer_id' => $customer->id,
        ])->assertRedirect(route('customer-accounts.create'));

        $user = User::where('email', 'north@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame($user->id, $customer->fresh()->user_id);
        $this->assertSame($agent->id, $customer->fresh()->assigned_employee_id);
    }

    public function test_agent_cannot_create_account_for_customer_assigned_to_someone_else(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $otherAgent = User::factory()->create(['role' => 'agent']);
        $customer = $this->makeCustomer('Reserved Clinic');
        $customer->update(['assigned_employee_id' => $otherAgent->id]);

        $this->actingAsUser($agent)->post('/customer-accounts', [
            'full_name' => 'Reserved User', 'email' => 'reserved@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'customer_id' => $customer->id,
        ])->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('users', ['email' => 'reserved@example.com']);
    }

    public function test_agent_can_view_only_their_assigned_customers(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $otherAgent = User::factory()->create(['role' => 'agent']);
        $mine = $this->makeCustomer('My Customer');
        $mine->update(['assigned_employee_id' => $agent->id]);
        $other = $this->makeCustomer('Other Customer');
        $other->update(['assigned_employee_id' => $otherAgent->id]);

        $this->actingAsUser($agent)->get('/customer-accounts/create')->assertInertia(fn ($page) => $page
            ->where('assignedCustomers.0.company_name', 'My Customer')
            ->missing('assignedCustomers.1')
        );
    }

    public function test_admin_and_customer_cannot_use_agent_customer_account_routes(): void
    {
        $this->actingAsUser(User::factory()->create(['role' => 'admin']))->get('/customer-accounts/create')->assertForbidden();
        $this->actingAsUser(User::factory()->create(['role' => 'customer']))->post('/customer-accounts', [])->assertForbidden();
    }
}
