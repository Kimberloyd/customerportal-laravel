<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_password_must_be_at_least_twelve_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'short1234',
            'password_confirmation' => 'short1234',
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

    public function test_admin_role_rejected_without_allow_admin_param(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Guy',
            'email' => 'new@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_admin_role_accepted_with_allow_admin_param(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post('/admin/users?allow_admin=1', [
            'full_name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('admin', User::where('email', 'newadmin@example.com')->first()->role);
    }

    public function test_customer_role_requires_valid_active_unlinked_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactiveCustomer = $this->makeCustomer('Inactive Co');
        $inactiveCustomer->update(['is_active' => false]);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Customer User',
            'email' => 'newcust@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'customer',
            'customer_id' => $inactiveCustomer->id,
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_customer_already_linked_to_another_user_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Linked Co', $otherUser);

        $response = $this->actingAsUser($admin)->post('/admin/users', [
            'full_name' => 'New Customer User',
            'email' => 'newcust@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'role' => 'customer',
            'customer_id' => $customer->id,
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_creates_user_and_links_customer_successfully(): void
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

        $response->assertRedirect(route('admin.users.index'));
        $newUser = User::where('email', 'newcust@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('password12345', $newUser->password_hash));
        $this->assertSame($newUser->id, $customer->fresh()->user_id);
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
