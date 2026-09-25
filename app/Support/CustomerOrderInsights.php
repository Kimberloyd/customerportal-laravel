<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

/**
 * Order status breakdown (as percentages) and the customer's most ordered
 * products by quantity. Shared by ProfileController (a customer viewing
 * their own insights) and Admin\UserController (an admin viewing a
 * customer account's insights) so both stay in sync.
 */
class CustomerOrderInsights
{
    /** Rows in the "most ordered products" list. */
    private const TOP_PRODUCTS_LIMIT = 5;

    /**
     * Order IDs are resolved through the PurchaseOrder model first (not a
     * raw join) so the query respects its SoftDeletes scope -- archived
     * orders don't count here, matching every other order listing in the
     * app.
     *
     * @return array{total_orders: int, status_breakdown: array, top_products: array}
     */
    public static function for(Customer $customer): array
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
