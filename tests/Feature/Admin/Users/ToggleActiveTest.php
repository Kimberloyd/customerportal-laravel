<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ToggleActiveTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_self_deactivation_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->post("/admin/users/{$admin->id}/toggle-active");

        $response->assertSessionHas('error', 'You cannot deactivate your current account.');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivation_takes_effect_in_the_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($admin)->post("/admin/users/{$target->id}/toggle-active");

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_deactivated_users_next_request_is_rejected(): void
    {
        // Reuses the exact mechanism EnsureSessionVersionMatches now
        // enforces for is_active (this phase's fix) -- constructs the
        // scenario a deactivation produces directly: Auth::user()
        // resolving to a DB row with is_active=false.
        $target = User::factory()->create(['role' => 'employee', 'is_active' => false]);

        $this->actingAs($target)
            ->withSession(['session_version' => $target->session_version])
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_audit_action_reflects_direction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($admin)->post("/admin/users/{$target->id}/toggle-active");
        $this->assertSame('deactivated', AdminAudit::latest('id')->first()->action);

        $this->actingAsUser($admin)->post("/admin/users/{$target->id}/toggle-active");
        $this->assertSame('activated', AdminAudit::latest('id')->first()->action);
    }

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $target = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->post("/admin/users/{$target->id}/toggle-active")->assertStatus(403);
    }
}
