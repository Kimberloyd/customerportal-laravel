<?php

namespace App\Http\Controllers;

use App\Models\AdminAudit;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\AdminUserListing;
use App\Support\CustomerAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /** Rows of recent account activity shown on the profile page. */
    private const ACTIVITY_LIMIT = 8;

    public function show(Request $request): Response
    {
        $user = $request->user();

        $orderCount = CustomerAccess::applyToOrders(PurchaseOrder::query(), $user)->count();

        $activity = AdminAudit::where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['action', 'details', 'actor_role', 'created_at'])
            ->map(fn (AdminAudit $entry) => [
                'action' => $entry->action,
                'details' => $entry->details,
                'actor_role' => $entry->actor_role,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Profile/Show', [
            'user' => [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_label' => AdminUserListing::ROLE_LABELS[$user->role] ?? $user->role,
                'member_since' => $user->created_at?->toIso8601String(),
            ],
            'stats' => [
                'order_count' => $orderCount,
                'managed_customer_count' => $user->role === User::ROLE_AGENT
                    ? $user->assignedCustomers()->count()
                    : null,
            ],
            'activity' => $activity,
        ]);
    }
}
