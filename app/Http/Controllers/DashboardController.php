<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Support\CustomerScope;
use App\Support\DashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $isCustomer = $request->user()->role === 'customer';
        $customer = CustomerScope::forCurrentUser(required: false);
        $orders = PurchaseOrder::query();

        // A missing or inactive customer link must never become a company-wide query.
        if ($isCustomer) {
            $customer ? $orders->where('customer_id', $customer->id) : $orders->whereRaw('1 = 0');
        } else {
            abort_unless(in_array($request->user()->role, ['admin', 'employee'], true), 403);
        }

        $period = $request->query('period', '30');
        $days = in_array($period, ['7', '30', '90'], true) ? (int) $period : 30;

        return Inertia::render('Dashboard', [
            'dashboard' => (new DashboardOverview)->build($orders, $days, $isCustomer),
            'workspace' => [
                'is_customer' => $isCustomer,
                'name' => $customer?->company_name,
                'can_order' => ! $isCustomer || $customer !== null,
            ],
        ]);
    }
}
