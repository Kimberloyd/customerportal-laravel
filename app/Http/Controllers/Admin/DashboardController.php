<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Support\InventoryApiClient;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports app/admin/admin_routes.py's admin_dashboard(). Flask's version
 * is a full tabbed console with inline edit/delete forms duplicating
 * the Customers/Products/Purchase-Orders CRUD already built as
 * dedicated pages in this project -- porting those forms verbatim
 * would mean two code paths performing the same mutations. This is
 * deliberately scoped down to a read-only summary: the same counts,
 * the same recent-10 lists, linking out to the real pages for any
 * action.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly InventoryApiClient $inventory) {}

    public function index(): Response
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $productPage = $this->inventory->request(['limit' => 10]);

        return Inertia::render('Admin/Dashboard', [
            'totalCustomers' => Customer::count(),
            'totalProducts' => $productPage['meta']['total'] ?? 0,
            'totalOrders' => PurchaseOrder::count(),
            'openMessages' => CustomerMessage::whereNull('parent_id')->where('status', 'open')->count(),
            'recentCustomers' => Customer::orderByDesc('created_at')->limit(10)->get([
                'id', 'customer_code', 'company_name', 'contact_person', 'email', 'phone',
            ]),
            'recentProducts' => collect($productPage['data'] ?? [])->map(fn (array $product) => [
                'id' => $product['id'],
                'generic_name' => $product['generic'],
                'product_name' => $product['product_name'] ?? $product['name'],
                'sku' => $product['sku'],
                'unit' => $product['unit_type'],
                'is_active' => (bool) $product['is_active'],
            ])->values(),
            'recentOrders' => PurchaseOrder::with(['customer', 'items'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get()
                ->map(fn (PurchaseOrder $order) => [
                    'id' => $order->id,
                    'po_number' => $order->po_number,
                    'submitted_at' => $order->submitted_at?->toIso8601String(),
                    'customer_name' => $order->customer?->company_name,
                    'ordered_units' => (int) $order->items->sum('quantity'),
                    'balance_units' => $order->balance_units,
                    'status' => $order->status,
                ]),
        ]);
    }
}
