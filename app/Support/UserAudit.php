<?php

namespace App\Support;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ports record_admin_audit() as used by app/admin/admin_routes.py for
 * user management -- same shape as ProductAudit/CustomerAudit, writes
 * to the same generic AdminAudit table with entity_type='user'.
 */
class UserAudit
{
    public static function record(User $user, string $action, ?string $details, Request $request): void
    {
        $actor = Auth::user();

        AdminAudit::create([
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'action' => $action,
            'details' => $details,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'ip_address' => $request->ip(),
            'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
