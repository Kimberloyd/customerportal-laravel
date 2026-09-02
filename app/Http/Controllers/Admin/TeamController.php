<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function store(Request $request)
    {
        $this->requireAdmin();
        $values = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')],
            'employee_ids' => ['required', 'array', 'min:1', 'max:3'],
            'employee_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'employee_ids.max' => 'A team can have up to 3 employees.',
            'employee_ids.min' => 'Choose at least one employee for the team.',
        ]);

        DB::transaction(function () use ($values, $request) {
            $employees = User::query()->whereIn('id', $values['employee_ids'])->where('role', 'employee')->where('is_active', true)->lockForUpdate()->get();
            if ($employees->count() !== count($values['employee_ids'])) {
                throw ValidationException::withMessages(['employee_ids' => 'Choose active employee accounts only.']);
            }
            $team = Team::create(['name' => trim($values['name'])]);
            $team->members()->attach($employees->pluck('id'));
            AdminAudit::create([
                'entity_type' => 'team', 'entity_id' => $team->id, 'action' => 'created',
                'details' => 'team created with '.$employees->count().' employee(s)',
                'actor_user_id' => Auth::id(), 'actor_role' => 'admin',
                'ip_address' => $request->ip(), 'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.dashboard', ['tab' => 'teams'])->with('success', 'Team created.');
    }

    private function requireAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
}
