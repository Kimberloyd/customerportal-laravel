<?php

namespace App\Http\Controllers;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\AdminUserListing;
use App\Support\CustomerAccess;
use App\Support\CustomerScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /** Rows of recent account activity shown on the profile page. */
    private const ACTIVITY_LIMIT = 8;

    /** Rows in the customer-only "most ordered products" list. */
    private const TOP_PRODUCTS_LIMIT = 5;

    public function show(Request $request): Response
    {
        $user = $request->user();

        $orderCount = CustomerAccess::applyToOrders(PurchaseOrder::query(), $user)->count();

        $orderInsights = null;
        if ($user->role === User::ROLE_CUSTOMER) {
            $customer = CustomerScope::activeCustomerFor($user);
            $orderInsights = $customer ? $this->orderInsightsFor($customer) : null;
        }

        $activity = AdminAudit::where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->with('actor:id,full_name')
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['id', 'action', 'details', 'actor_user_id', 'actor_role', 'created_at'])
            ->map(fn (AdminAudit $entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'details' => $entry->details,
                'actor_name' => $entry->actor?->full_name,
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
            'order_insights' => $orderInsights,
        ]);
    }

    /**
     * Order status breakdown (as percentages) and the customer's most
     * ordered products by quantity. Order IDs are resolved through the
     * PurchaseOrder model first (not a raw join) so the query respects
     * its SoftDeletes scope -- archived orders don't count here, matching
     * every other order listing in the app.
     *
     * @return array{total_orders: int, status_breakdown: array, top_products: array}
     */
    private function orderInsightsFor(Customer $customer): array
    {
        $orders = PurchaseOrder::where('customer_id', $customer->id)->get(['id', 'status']);
        $total = $orders->count();

        $statusBreakdown = $orders->countBy('status')
            ->map(fn (int $count, string $status) => [
                'status' => $status,
                'count' => $count,
                'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        $topProducts = PurchaseOrderItem::whereIn('purchase_order_id', $orders->pluck('id'))
            ->selectRaw('product_name, SUM(quantity) as total_quantity, COUNT(DISTINCT purchase_order_id) as order_count')
            ->groupBy('product_name')
            ->orderByDesc('total_quantity')
            ->limit(self::TOP_PRODUCTS_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'total_quantity' => (int) $row->total_quantity,
                'order_count' => (int) $row->order_count,
            ]);

        return [
            'total_orders' => $total,
            'status_breakdown' => $statusBreakdown,
            'top_products' => $topProducts,
        ];
    }
}
