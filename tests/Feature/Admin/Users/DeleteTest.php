<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_self_delete_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->delete("/admin/users/{$admin->id}");

        $response->assertSessionHas('error', 'You cannot delete your current account.');
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_deleting_an_inactive_admin_is_not_blocked_by_the_last_admin_guard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $otherAdmin->update(['is_active' => false]);

        // otherAdmin is inactive, so they don't count toward the "last
        // active admin" total -- deleting them is unrestricted.
        $response = $this->actingAsUser($admin)->delete("/admin/users/{$otherAdmin->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));
        $this->assertNull(User::find($otherAdmin->id));
    }

    public function test_deleting_one_of_two_active_admins_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetAdmin = User::factory()->create(['role' => 'admin']);

        // Two active admins exist, so the count guard (<=1) doesn't
        // trigger -- deleting one leaves exactly one behind. Deleting
        // that final one can only be attempted by that admin themselves
        // (no other active admin exists to act as a distinct actor),
        // which is intercepted by the self-delete guard first -- same
        // precedence Flask's own code has (self-check before the count
        // check), making the count guard effectively a defensive
        // backstop rather than one reachable via a distinct HTTP actor.
        $response = $this->actingAsUser($admin)->delete("/admin/users/{$targetAdmin->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));
        $this->assertNull(User::find($targetAdmin->id));
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_linked_customers_are_unlinked_not_cascade_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'customer']);
        $customer = $this->makeCustomer('Own Co', $target);

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");

        $this->assertNull(User::find($target->id));
        $this->assertNotNull($customer->fresh());
        $this->assertNull($customer->fresh()->user_id);
    }

    public function test_audit_row_survives_user_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee', 'email' => 'doomed@example.com']);

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");

        $audit = AdminAudit::where('entity_type', 'user')->where('action', 'deleted')->first();
        $this->assertNotNull($audit);
        $this->assertSame('email=doomed@example.com, role=employee', $audit->details);
    }

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->delete("/admin/users/{$target->id}")->assertStatus(403);
    }
}
