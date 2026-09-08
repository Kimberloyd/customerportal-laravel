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

    public function test_employee_cannot_be_added_to_more_than_one_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $existingTeam = Team::create(['name' => 'Existing Team']);
        $existingTeam->members()->attach($employee);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'Second Team',
            'employee_ids' => [$employee->id],
        ])->assertSessionHasErrors([
            'employee_ids' => $employee->full_name.' already belongs to a team. Choose another employee.',
        ]);

        $this->assertDatabaseMissing('teams', ['name' => 'Second Team']);
        $this->assertDatabaseCount('team_members', 1);
    }

    public function test_admin_can_update_a_team_and_its_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstEmployee = User::factory()->create(['role' => 'employee']);
        $secondEmployee = User::factory()->create(['role' => 'employee']);
        $team = Team::create(['name' => 'Old Name']);
        $team->members()->attach($firstEmployee);

        $this->actingAsUser($admin)->put(route('admin.teams.update', $team), [
            'name' => 'New Name',
            'employee_ids' => [$secondEmployee->id],
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id, 'user_id' => $firstEmployee->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $secondEmployee->id]);
    }

    public function test_admin_cannot_update_a_team_with_an_employee_from_another_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $existingTeam = Team::create(['name' => 'Existing Team']);
        $existingTeam->members()->attach($employee);
        $team = Team::create(['name' => 'Other Team']);

        $this->actingAsUser($admin)->put(route('admin.teams.update', $team), [
            'name' => 'Changed Team',
            'employee_ids' => [$employee->id],
        ])->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Other Team']);
    }

    public function test_admin_can_delete_a_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $team = Team::create(['name' => 'Temporary Team']);
        $team->members()->attach($employee);

        $this->actingAsUser($admin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
    }

    public function test_employee_cannot_update_or_delete_a_team(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $team = Team::create(['name' => 'Protected Team']);

        $this->actingAsUser($employee)->put(route('admin.teams.update', $team), [
            'name' => 'Changed Team',
            'employee_ids' => [$employee->id],
        ])->assertForbidden();

        $this->actingAsUser($employee)
            ->delete(route('admin.teams.destroy', $team))
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Protected Team']);
    }

    public function test_employee_cannot_create_a_team(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($employee)->post('/admin/teams', [
            'name' => 'No Access', 'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }
}
