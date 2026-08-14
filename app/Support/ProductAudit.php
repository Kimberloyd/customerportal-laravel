<?php

namespace App\Support;

use App\Models\AdminAudit;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ports record_admin_audit() (app/audit_log.py) as used by
 * app/products/product_routes.py -- entity_id is deliberately not a
 * real FK (matches AdminAudit's own migration comment) so this row
 * survives the product being hard-deleted.
 */
class ProductAudit
{
    public static function record(Product $product, string $action, Request $request): void
    {
        $user = Auth::user();

        AdminAudit::create([
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'action' => $action,
            'details' => "product_name={$product->product_name}, sku={$product->sku}",
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role,
            'ip_address' => $request->ip(),
            'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
