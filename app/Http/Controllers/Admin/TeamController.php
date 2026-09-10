<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function store(Request $request)
    {
        $this->requireAdmin();
        $values = $this->validatedTeam($request);

        DB::transaction(function () use ($values, $request) {
            $agents = $this->availableAgents($values['agent_ids']);

            $team = Team::create(['name' => trim($values['name'])]);
            $team->members()->attach($agents->pluck('id'));
            $this->recordAudit($request, $team, 'created', 'team created with '.$agents->count().' agent(s)');
        });

        return redirect()->route('admin.dashboard', ['tab' => 'teams'])->with('success', 'Team created.');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('manage', $team);
        $values = $this->validatedTeam($request, $team);

        DB::transaction(function () use ($values, $request, $team) {
            $agents = $this->availableAgents($values['agent_ids'], $team);

            $team->update(['name' => trim($values['name'])]);
            $team->members()->sync($agents->pluck('id'));
            $this->recordAudit($request, $team, 'updated', 'team updated with '.$agents->count().' agent(s)');
        });

        return redirect()->route('admin.dashboard', ['tab' => 'teams'])->with('success', 'Team updated.');
    }

    public function destroy(Request $request, Team $team)
    {
        $this->authorize('manage', $team);

        DB::transaction(function () use ($request, $team) {
            $teamId = $team->id;
            $teamName = $team->name;
            $team->delete();
            $this->recordAudit($request, $team, 'deleted', 'team deleted: '.$teamName, $teamId);
        });

        return redirect()->route('admin.dashboard', ['tab' => 'teams'])->with('success', 'Team deleted.');
    }

    /** @return array{name: string, agent_ids: array<int, int>} */
    private function validatedTeam(Request $request, ?Team $team = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')->ignore($team?->id)],
            'agent_ids' => ['required', 'array', 'min:1', 'max:3'],
            'agent_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'agent_ids.max' => 'A team can have up to 3 agents.',
            'agent_ids.min' => 'Choose at least one agent for the team.',
        ]);
    }

    private function availableAgents(array $agentIds, ?Team $team = null)
    {
        $agents = User::query()
            ->whereIn('id', $agentIds)
            ->where('role', User::ROLE_AGENT)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();

        if ($agents->count() !== count($agentIds)) {
            throw ValidationException::withMessages(['agent_ids' => 'Choose active agent accounts only.']);
        }

        $assignedIds = DB::table('team_members')
            ->whereIn('user_id', $agents->pluck('id'))
            ->when($team, fn ($query) => $query->where('team_id', '!=', $team->id))
            ->pluck('user_id');

        if ($assignedIds->isNotEmpty()) {
            $assignedNames = $agents->whereIn('id', $assignedIds)->pluck('full_name')->values();
            $message = $assignedNames->count() === 1
                ? $assignedNames->first().' already belongs to a team. Choose another agent.'
                : $assignedNames->join(', ').' already belong to teams. Choose other agents.';

            throw ValidationException::withMessages(['agent_ids' => $message]);
        }

        return $agents;
    }

    private function recordAudit(Request $request, Team $team, string $action, string $details, ?int $teamId = null): void
    {
        AdminAudit::create([
            'entity_type' => 'team', 'entity_id' => $teamId ?? $team->id, 'action' => $action,
            'details' => $details,
            'actor_user_id' => Auth::id(), 'actor_role' => 'admin',
            'ip_address' => $request->ip(), 'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function requireAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
}
