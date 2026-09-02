<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class EmployeeCustomerAccountTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_employee_creates_customer_account_and_is_assigned_to_customer(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('North Clinic');

        $this->actingAsUser($employee)->post('/customer-accounts', [
            'full_name' => 'North Clinic User', 'email' => 'north@example.com', 'phone' => '09171234567',
            'password' => 'password123', 'password_confirmation' => 'password123', 'customer_id' => $customer->id,
        ])->assertRedirect(route('customer-accounts.create'));

        $user = User::where('email', 'north@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame($user->id, $customer->fresh()->user_id);
        $this->assertSame($employee->id, $customer->fresh()->assigned_employee_id);
    }

    public function test_employee_cannot_create_account_for_customer_assigned_to_someone_else(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $otherEmployee = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Reserved Clinic');
        $customer->update(['assigned_employee_id' => $otherEmployee->id]);

        $this->actingAsUser($employee)->post('/customer-accounts', [
            'full_name' => 'Reserved User', 'email' => 'reserved@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'customer_id' => $customer->id,
        ])->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('users', ['email' => 'reserved@example.com']);
    }

    public function test_admin_and_customer_cannot_use_employee_customer_account_routes(): void
    {
        $this->actingAsUser(User::factory()->create(['role' => 'admin']))->get('/customer-accounts/create')->assertForbidden();
        $this->actingAsUser(User::factory()->create(['role' => 'customer']))->post('/customer-accounts', [])->assertForbidden();
    }
}
