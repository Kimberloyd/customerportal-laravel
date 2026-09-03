<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->get('/admin/users/create')->assertStatus(403);
        $this->actingAsUser($employee)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'employee',
        ])->assertStatus(403);
    }

    public function test_password_required_on_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'role' => 'employee',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(1, User::count());
    }

    public function test_password_must_be_at_least_eight_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'short7',
            'password_confirmation' => 'short7',
            'role' => 'employee',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_confirmation_must_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'different12345',
            'role' => 'employee',
        ]);

        $response->assertSessionHasErrors('password_confirmation');
    }

    public function test_password_can_include_the_account_holders_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'Juniper Vale',
            'email' => 'juniper@example.com',
            'password' => 'JuniperGarden2026!',
            'password_confirmation' => 'JuniperGarden2026!',
            'role' => 'employee',
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));

        $this->assertDatabaseHas('users', ['email' => 'juniper@example.com']);
    }

    public function test_duplicate_email_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'taken@example.com']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'taken@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'employee',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_admin_can_create_an_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));
        $this->assertSame('admin', User::where('email', 'newadmin@example.com')->first()->role);
    }

    public function test_customer_role_is_rejected_from_admin_account_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer('Fresh Co');

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Customer User',
            'email' => 'newcust@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'customer',
            'customer_id' => $customer->id,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'newcust@example.com']);
        $this->assertNull($customer->fresh()->user_id);
    }

    public function test_writes_audit_row_on_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'employee',
        ]);

        $audit = AdminAudit::first();
        $this->assertSame('user', $audit->entity_type);
        $this->assertSame('created', $audit->action);
        $this->assertSame('email=new@example.com, role=employee', $audit->details);
    }
}
