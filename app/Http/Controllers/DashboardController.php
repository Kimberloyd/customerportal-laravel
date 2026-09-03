<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Support\CustomerScope;
use Illuminate\Support\Carbon;
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
                'metrics' => $this->dashboardMetrics(),
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
                'metrics' => [],
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
            'metrics' => $this->dashboardMetrics($customer->id),
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

    /**
     * Return the real activity data used by the Spectrum stat cards.
     *
     * @return array<int, array{label: string, series: array<int, int>, value: int, previous: int, deltaLabel: string}>
     */
    private function dashboardMetrics(?int $customerId = null): array
    {
        return [
            $this->purchaseOrderMetric('Orders submitted', 'submitted_at', $customerId),
            $this->purchaseOrderMetric('Orders completed', 'completed_at', $customerId),
            $this->purchaseOrderMetric('Deliveries received', 'customer_received_at', $customerId),
            $this->orderUpdateMetric($customerId),
        ];
    }

    /**
     * @return array{label: string, series: array<int, int>, value: int, previous: int, deltaLabel: string}
     */
    private function purchaseOrderMetric(string $label, string $dateColumn, ?int $customerId): array
    {
        // The column is an internal allowlist, never a request value, so the
        // grouped expression cannot be altered by a dashboard visitor.
        if (! in_array($dateColumn, ['submitted_at', 'completed_at', 'customer_received_at'], true)) {
            throw new \InvalidArgumentException('Unsupported purchase-order metric column.');
        }

        [$previousStart, $currentStart, $tomorrow] = $this->metricRange();

        $query = PurchaseOrder::query()
            ->selectRaw("DATE({$dateColumn}) as metric_date, COUNT(*) as metric_total")
            ->whereNotNull($dateColumn)
            ->where($dateColumn, '>=', $previousStart)
            ->where($dateColumn, '<', $tomorrow);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $this->metricCard(
            $label,
            $query->groupBy('metric_date')->pluck('metric_total', 'metric_date')->all(),
            $previousStart,
            $currentStart,
            $tomorrow,
        );
    }

    /**
     * @return array{label: string, series: array<int, int>, value: int, previous: int, deltaLabel: string}
     */
    private function orderUpdateMetric(?int $customerId): array
    {
        [$previousStart, $currentStart, $tomorrow] = $this->metricRange();

        $query = PurchaseOrderAudit::query()
            ->selectRaw('DATE(purchase_order_audits.created_at) as metric_date, COUNT(*) as metric_total')
            ->where('purchase_order_audits.created_at', '>=', $previousStart)
            ->where('purchase_order_audits.created_at', '<', $tomorrow);

        if ($customerId !== null) {
            $query
                ->join('purchase_orders as metric_orders', 'metric_orders.id', '=', 'purchase_order_audits.purchase_order_id')
                ->where('metric_orders.customer_id', $customerId);
        }

        return $this->metricCard(
            'Order updates',
            $query->groupBy('metric_date')->pluck('metric_total', 'metric_date')->all(),
            $previousStart,
            $currentStart,
            $tomorrow,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon}
     */
    private function metricRange(): array
    {
        $tomorrow = now()->startOfDay()->addDay();
        $currentStart = $tomorrow->copy()->subDays(30);

        return [$currentStart->copy()->subDays(30), $currentStart, $tomorrow];
    }

    /**
     * @param  array<string, int|string>  $dailyTotals
     * @return array{label: string, series: array<int, int>, value: int, previous: int, deltaLabel: string}
     */
    private function metricCard(
        string $label,
        array $dailyTotals,
        Carbon $previousStart,
        Carbon $currentStart,
        Carbon $tomorrow,
    ): array {
        $series = [];
        $previous = 0;

        for ($day = $previousStart->copy(); $day->lt($tomorrow); $day->addDay()) {
            $total = (int) ($dailyTotals[$day->toDateString()] ?? 0);

            if ($day->lt($currentStart)) {
                $previous += $total;
            } else {
                $series[] = $total;
            }
        }

        return [
            'label' => $label,
            'series' => $series,
            'value' => array_sum($series),
            'previous' => $previous,
            'deltaLabel' => 'vs previous 30 days',
        ];
    }
}
