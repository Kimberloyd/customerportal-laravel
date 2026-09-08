<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_team_with_up_to_three_agents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agents = User::factory()->count(3)->create(['role' => 'agent']);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'North Team', 'agent_ids' => $agents->pluck('id')->all(),
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $team = Team::where('name', 'North Team')->firstOrFail();
        $this->assertCount(3, $team->members);
    }

    public function test_team_cannot_have_more_than_three_agents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agents = User::factory()->count(4)->create(['role' => 'agent']);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'Too Large', 'agent_ids' => $agents->pluck('id')->all(),
        ])->assertSessionHasErrors('agent_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Too Large']);
    }

    public function test_office_accounts_cannot_be_team_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $office = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'Office Team', 'agent_ids' => [$office->id],
        ])->assertSessionHasErrors('agent_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Office Team']);
    }

    public function test_agent_cannot_be_added_to_more_than_one_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);
        $existingTeam = Team::create(['name' => 'Existing Team']);
        $existingTeam->members()->attach($agent);

        $this->actingAsUser($admin)->post('/admin/teams', [
            'name' => 'Second Team',
            'agent_ids' => [$agent->id],
        ])->assertSessionHasErrors([
            'agent_ids' => $agent->full_name.' already belongs to a team. Choose another agent.',
        ]);

        $this->assertDatabaseMissing('teams', ['name' => 'Second Team']);
        $this->assertDatabaseCount('team_members', 1);
    }

    public function test_admin_can_update_a_team_and_its_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstAgent = User::factory()->create(['role' => 'agent']);
        $secondAgent = User::factory()->create(['role' => 'agent']);
        $team = Team::create(['name' => 'Old Name']);
        $team->members()->attach($firstAgent);

        $this->actingAsUser($admin)->put(route('admin.teams.update', $team), [
            'name' => 'New Name',
            'agent_ids' => [$secondAgent->id],
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id, 'user_id' => $firstAgent->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $secondAgent->id]);
    }

    public function test_admin_cannot_update_a_team_with_an_agent_from_another_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);
        $existingTeam = Team::create(['name' => 'Existing Team']);
        $existingTeam->members()->attach($agent);
        $team = Team::create(['name' => 'Other Team']);

        $this->actingAsUser($admin)->put(route('admin.teams.update', $team), [
            'name' => 'Changed Team',
            'agent_ids' => [$agent->id],
        ])->assertSessionHasErrors('agent_ids');

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Other Team']);
    }

    public function test_admin_can_delete_a_team(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);
        $team = Team::create(['name' => 'Temporary Team']);
        $team->members()->attach($agent);

        $this->actingAsUser($admin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'teams']));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
    }

    public function test_agent_cannot_update_or_delete_a_team(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $team = Team::create(['name' => 'Protected Team']);

        $this->actingAsUser($agent)->put(route('admin.teams.update', $team), [
            'name' => 'Changed Team',
            'agent_ids' => [$agent->id],
        ])->assertForbidden();

        $this->actingAsUser($agent)
            ->delete(route('admin.teams.destroy', $team))
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Protected Team']);
    }

    public function test_agent_cannot_create_a_team(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAsUser($agent)->post('/admin/teams', [
            'name' => 'No Access', 'agent_ids' => [$agent->id],
        ])->assertForbidden();
    }
}
