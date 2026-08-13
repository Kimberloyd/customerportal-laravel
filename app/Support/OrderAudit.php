<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ports record_order_audit() from
 * app/purchase_orders/purchase_order_routes.py: every mutation to an
 * order bumps updated_at and writes an audit row snapshotting the
 * order's remarks and the acting user at that moment (actor snapshot
 * fields, not a live FK lookup, so the row still reads sensibly after
 * the user is later renamed/deactivated).
 */
class OrderAudit
{
    public static function record(PurchaseOrder $order, string $action, ?string $details, Request $request): void
    {
        $updatedAt = now();
        $order->updated_at = $updatedAt;
        $order->save();

        $user = Auth::user();

        PurchaseOrderAudit::create([
            'purchase_order_id' => $order->id,
            'action' => $action,
            'details' => $details,
            'remarks' => $order->remarks,
            'created_at' => $updatedAt,
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role,
            'ip_address' => $request->ip(),
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
