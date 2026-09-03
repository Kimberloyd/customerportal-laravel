<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class EditTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests do not have a browser-provided CSRF token. Keep the
        // production middleware enabled while allowing this class to reach
        // the authorization and update behavior it is intended to verify.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_blank_password_leaves_hash_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee']);
        $originalHash = $target->password_hash;

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '1',
        ]);

        $this->assertSame($originalHash, $target->fresh()->password_hash);
    }

    public function test_new_password_bumps_session_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee']);
        $originalVersion = $target->session_version;

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '1',
            'password' => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);

        $this->assertSame($originalVersion + 1, $target->fresh()->session_version);
    }

    public function test_stale_session_is_rejected_after_a_session_version_bump(): void
    {
        // Reuses the exact mechanism EnsureSessionVersionMatches already
        // enforces (proven generically in Phase 1's
        // LegacyAuthBehaviorTest::test_session_version_mismatch_forces_logout_on_next_request)
        // -- this just confirms the scenario a password reset produces
        // (a session stamped with the pre-bump version, Auth::user()
        // resolving to the post-bump DB row) is exactly what triggers it.
        $target = User::factory()->create(['role' => 'employee', 'session_version' => 0]);
        $target->session_version = 1; // simulates the DB state after an admin's password reset

        $this->withSession(['session_version' => 0])
            ->actingAs($target)
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_self_password_reset_does_not_log_out_current_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin);
        $this->put("/admin/users/{$admin->id}", [
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => 'admin',
            'is_active' => '1',
            'password' => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);

        // The auth guard in this test still holds the original $admin
        // PHP object from actingAsUser() above -- refresh it in place so
        // it reflects the session_version the request just bumped (a
        // fresh HTTP client in production would naturally re-resolve
        // this from the DB on the next real request).
        $admin->refresh();

        $this->get('/dashboard')->assertOk();
    }

    public function test_self_edit_cannot_change_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->put("/admin/users/{$admin->id}", [
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => 'employee',
            'is_active' => '1',
        ]);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_self_edit_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->put("/admin/users/{$admin->id}", [
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_link_resync_unlinks_old_customer_and_links_new_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'customer']);
        $oldCustomer = $this->makeCustomer('Old Co', $target);
        $newCustomer = $this->makeCustomer('New Co');

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'customer',
            'customer_id' => $newCustomer->id,
            'is_active' => '1',
        ]);

        $this->assertNull($oldCustomer->fresh()->user_id);
        $this->assertSame($target->id, $newCustomer->fresh()->user_id);
    }

    public function test_role_change_away_from_customer_unlinks_everything(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $target);
        $originalSessionVersion = $target->session_version;

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '1',
        ]);

        $this->assertNull($customer->fresh()->user_id);
        $this->assertSame($originalSessionVersion + 1, $target->fresh()->session_version);

        $target->refresh();
        $this->withSession(['session_version' => $originalSessionVersion])
            ->actingAs($target)
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_convert_an_employee_into_a_customer_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer('Fresh Co');

        $this->actingAsUser($admin)->put("/admin/users/{$employee->id}", [
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'role' => 'customer',
            'customer_id' => $customer->id,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertSame('employee', $employee->fresh()->role);
        $this->assertNull($customer->fresh()->user_id);
    }

    public function test_change_list_audit_details_for_existing_customer_role_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->makeCustomer('Own Co', $target);

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '0',
        ]);

        $audit = AdminAudit::where('action', 'updated')->first();
        $this->assertSame('role customer -> employee, deactivated', $audit->details);
    }

    public function test_no_op_edit_records_profile_details_updated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAsUser($admin)->put("/admin/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '1',
        ]);

        $audit = AdminAudit::where('action', 'updated')->first();
        $this->assertSame('profile details updated', $audit->details);
    }

    public function test_employee_cannot_update_an_account(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->put("/admin/users/{$target->id}", [
            'full_name' => 'Unauthorized change',
            'email' => $target->email,
            'role' => 'employee',
            'is_active' => '1',
        ])->assertStatus(403);

        $this->assertNotSame('Unauthorized change', $target->fresh()->full_name);
    }

    public function test_full_page_edit_route_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($admin)
            ->get("/admin/users/{$target->id}/edit")
            ->assertNotFound();
    }
}
