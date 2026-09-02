<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_team_with_up_to_three_employees(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employees = User::factory()->count(3)->create(['role' => 'employee']);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'North Team', 'employee_ids' => $employees->pluck('id')->all(),
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $team = Team::where('name', 'North Team')->firstOrFail();
        $this->assertCount(3, $team->members);
    }

    public function test_team_cannot_have_more_than_three_employees(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employees = User::factory()->count(4)->create(['role' => 'employee']);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'Too Large', 'employee_ids' => $employees->pluck('id')->all(),
        ])->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Too Large']);
    }

    public function test_employee_cannot_create_a_team(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->post('/admin/teams', [
            'name' => 'No Access', 'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }
}
