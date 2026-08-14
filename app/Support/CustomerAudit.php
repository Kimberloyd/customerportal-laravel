<?php

namespace App\Support;

use App\Models\AdminAudit;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ports record_admin_audit() as used by app/customers/customer_routes.py
 * -- same shape as App\Support\ProductAudit, entity_id deliberately not
 * a real FK so this row survives the customer being hard-deleted.
 */
class CustomerAudit
{
    public static function record(Customer $customer, string $action, Request $request): void
    {
        $user = Auth::user();

        AdminAudit::create([
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'action' => $action,
            'details' => "company_name={$customer->company_name}",
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role,
            'ip_address' => $request->ip(),
            'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
