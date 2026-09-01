<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Support\CustomerScope;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        if (Auth::user()->role === 'customer') {
            return Inertia::render('Dashboard', [
                'customerDashboard' => $this->customerDashboard(),
            ]);
        }

        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $summary = PurchaseOrder::query()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as submitted_orders, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as reviewing_orders, '
                .'COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as partial_orders, '
                .'COALESCE(SUM(CASE WHEN status = ? AND completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END), 0) as completed_today',
                [
                    PurchaseOrder::STATUS_SUBMITTED,
                    PurchaseOrder::STATUS_REVIEWING,
                    PurchaseOrder::STATUS_PARTIAL,
                    PurchaseOrder::STATUS_PROCESSING,
                    PurchaseOrder::STATUS_COMPLETED,
                    $today,
                    $tomorrow,
                ],
            )
            ->first();

        $orderColumns = [
            'id', 'po_number', 'customer_id', 'status', 'submitted_at',
            'updated_at', 'completed_at', 'customer_received_at',
        ];
        $orderRelations = ['customer:id,company_name'];

        $needsAttention = PurchaseOrder::query()
            ->select($orderColumns)
            ->with($orderRelations)
            ->withSum('items as ordered_units', 'quantity')
            ->withSum('items as delivered_units', 'delivered_quantity')
            ->whereIn('status', [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_REVIEWING,
                PurchaseOrder::STATUS_PARTIAL,
                PurchaseOrder::STATUS_PROCESSING,
            ])
            ->orderByRaw(
                "CASE status WHEN 'submitted' THEN 0 WHEN 'reviewing' THEN 1 ELSE 2 END"
            )
            ->orderBy('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->serializeOrder($order));

        $recentOrders = PurchaseOrder::query()
            ->select($orderColumns)
            ->with($orderRelations)
            ->withSum('items as ordered_units', 'quantity')
            ->withSum('items as delivered_units', 'delivered_quantity')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->serializeOrder($order));

        $recentActivity = PurchaseOrderAudit::query()
            ->select([
                'id', 'purchase_order_id', 'action', 'details',
                'actor_user_id', 'actor_role', 'created_at',
            ])
            ->with([
                'purchaseOrder:id,po_number',
                'actor:id,full_name,role',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (PurchaseOrderAudit $audit) => [
                'id' => $audit->id,
                'order_id' => $audit->purchase_order_id,
                'po_number' => $audit->purchaseOrder?->po_number,
                'action' => $audit->action,
                'details' => $audit->details,
                'actor_name' => $audit->actor?->full_name,
                'actor_role' => $audit->actor_role ?? $audit->actor?->role,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'companyDashboard' => [
                'summary' => [
                    'submitted' => (int) ($summary?->submitted_orders ?? 0),
                    'reviewing' => (int) ($summary?->reviewing_orders ?? 0),
                    'partial' => (int) ($summary?->partial_orders ?? 0),
                    'completed_today' => (int) ($summary?->completed_today ?? 0),
                ],
                'needs_attention' => $needsAttention,
                'recent_orders' => $recentOrders,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerDashboard(): array
    {
        $customer = CustomerScope::forCurrentUser(required: false);

        if ($customer === null) {
            return [
                'linked' => false,
                'customer_name' => null,
                'summary' => [
                    'active' => 0,
                    'in_progress' => 0,
                    'ready_to_confirm' => 0,
                    'received' => 0,
                ],
                'action_required' => [],
                'active_orders' => [],
                'recent_orders' => [],
            ];
        }

        $activeStatuses = [
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_REVIEWING,
            PurchaseOrder::STATUS_PARTIAL,
            PurchaseOrder::STATUS_PROCESSING,
        ];
        $summary = PurchaseOrder::query()
            ->where('customer_id', $customer->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END), 0) as active_orders, '
                .'COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as in_progress_orders, '
                .'COALESCE(SUM(CASE WHEN status = ? AND customer_received_at IS NULL THEN 1 ELSE 0 END), 0) as ready_to_confirm, '
                .'COALESCE(SUM(CASE WHEN customer_received_at IS NOT NULL THEN 1 ELSE 0 END), 0) as received_orders',
                [
                    ...$activeStatuses,
                    PurchaseOrder::STATUS_PARTIAL,
                    PurchaseOrder::STATUS_PROCESSING,
                    PurchaseOrder::STATUS_COMPLETED,
                ],
            )
            ->first();

        $orderColumns = [
            'id', 'po_number', 'customer_id', 'status', 'submitted_at',
            'updated_at', 'completed_at', 'customer_received_at',
        ];
        $orderQuery = fn () => PurchaseOrder::query()
            ->select($orderColumns)
            ->with('customer:id,company_name')
            ->withSum('items as ordered_units', 'quantity')
            ->withSum('items as delivered_units', 'delivered_quantity')
            ->where('customer_id', $customer->id);

        $actionRequired = $orderQuery()
            ->where('status', PurchaseOrder::STATUS_COMPLETED)
            ->whereNull('customer_received_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->serializeOrder($order));

        $activeOrders = $orderQuery()
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('updated_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->serializeOrder($order));

        $recentOrders = $orderQuery()
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => $this->serializeOrder($order));

        return [
            'linked' => true,
            'customer_name' => $customer->company_name,
            'summary' => [
                'active' => (int) ($summary?->active_orders ?? 0),
                'in_progress' => (int) ($summary?->in_progress_orders ?? 0),
                'ready_to_confirm' => (int) ($summary?->ready_to_confirm ?? 0),
                'received' => (int) ($summary?->received_orders ?? 0),
            ],
            'action_required' => $actionRequired,
            'active_orders' => $activeOrders,
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'customer_name' => $order->customer?->company_name,
            'status' => $order->status,
            'display_status' => $order->customer_received_at ? 'received' : $order->status,
            'ordered_units' => (int) ($order->ordered_units ?? 0),
            'delivered_units' => (int) ($order->delivered_units ?? 0),
            'balance_units' => $order->status === PurchaseOrder::STATUS_CANCELLED
                ? 0
                : max(0, (int) ($order->ordered_units ?? 0) - (int) ($order->delivered_units ?? 0)),
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
