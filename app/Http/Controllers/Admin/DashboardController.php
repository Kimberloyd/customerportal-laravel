<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Product;
use App\Models\PurchaseOrder;
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
    public function index(): Response
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        return Inertia::render('Admin/Dashboard', [
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => PurchaseOrder::count(),
            'openMessages' => CustomerMessage::whereNull('parent_id')->where('status', 'open')->count(),
            'recentCustomers' => Customer::orderByDesc('created_at')->limit(10)->get([
                'id', 'customer_code', 'company_name', 'contact_person', 'email', 'phone',
            ]),
            'recentProducts' => Product::orderByDesc('created_at')->limit(10)->get([
                'id', 'generic_name', 'product_name', 'sku', 'unit', 'is_active',
            ]),
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
