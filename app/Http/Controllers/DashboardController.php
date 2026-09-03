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
            // A year of audit rows, every completed order's lead time and the
            // cohort scan are the slowest work on this page, and none of it is
            // above the fold. Deferring lets the shell paint immediately while
            // the charts hold a skeleton. Deferred props must be top level --
            // Inertia only scans the outermost array for them.
            'companyCharts' => Inertia::defer(fn () => [
                'order_activity' => $this->orderActivityCalendar(),
                'activity_through' => $this->activityThrough(),
                'lead_times' => $this->fulfillmentLeadTimes(),
                'open_order_aging' => $this->openOrderAging(),
                'reorder_cohorts' => $this->reorderCohorts(),
            ], 'charts'),
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
     * Daily order activity for the calendar heatmap, covering the whole current
     * calendar year. A rolling 365-day window would reach back into last year
     * and print the same month name at both ends of the grid, so the window is
     * anchored to January 1st through December 31st.
     *
     * The series is dense -- every day of the year is present, quiet ones as
     * zeros -- so the grid draws a complete year rather than stopping at today.
     * Days still to come are zeros too, and read as quiet days; `activityThrough()`
     * marks where today falls so the streak count ignores them.
     *
     * @return array<int, array{t: int, value: int}>
     */
    private function orderActivityCalendar(): array
    {
        $tomorrow = now()->startOfDay()->addDay();
        $start = now()->startOfYear();
        $end = now()->endOfYear()->startOfDay()->addDay();

        $dailyTotals = PurchaseOrderAudit::query()
            ->selectRaw('DATE(created_at) as metric_date, COUNT(*) as metric_total')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $tomorrow)
            ->groupBy('metric_date')
            ->pluck('metric_total', 'metric_date')
            ->all();

        $days = [];

        for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
            $date = $day->toDateString();

            $days[] = [
                // The heatmap reads every cell with timeZone: 'UTC', so each day
                // has to be the UTC midnight of that calendar date -- not the
                // local midnight, which would shift cells into the wrong column.
                't' => Carbon::createFromFormat('Y-m-d', $date, 'UTC')
                    ->startOfDay()
                    ->getTimestamp() * 1000,
                'value' => (int) ($dailyTotals[$date] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * UTC midnight of today, matching how `orderActivityCalendar()` stamps each
     * day. The heatmap counts its current streak back from here -- without it a
     * grid padded to December 31st would always report a broken streak, because
     * the series ends on a day that has not happened yet.
     */
    private function activityThrough(): int
    {
        return Carbon::createFromFormat('Y-m-d', now()->toDateString(), 'UTC')
            ->startOfDay()
            ->getTimestamp() * 1000;
    }

    /**
     * Hours between submission and completion for each order completed in the
     * last year. The histogram bins these itself, so this stays raw samples.
     *
     * @return array<int, float>
     */
    private function fulfillmentLeadTimes(): array
    {
        $since = now()->startOfDay()->subDays(365);

        return PurchaseOrder::query()
            ->whereNotNull('submitted_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->whereColumn('completed_at', '>=', 'submitted_at')
            ->orderBy('completed_at')
            ->get(['submitted_at', 'completed_at'])
            ->map(fn (PurchaseOrder $order) => round(
                $order->submitted_at->diffInMinutes($order->completed_at) / 60,
                2,
            ))
            ->values()
            ->all();
    }

    /**
     * Open orders bucketed by how long they have been waiting. Every bucket is
     * always present so the bars keep a stable order and identity.
     *
     * @return array<int, array{bucket: string, orders: int}>
     */
    private function openOrderAging(): array
    {
        $today = now()->startOfDay();
        $threeDays = $today->copy()->subDays(2);
        $sixDays = $today->copy()->subDays(5);
        $elevenDays = $today->copy()->subDays(10);

        $buckets = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_REVIEWING,
                PurchaseOrder::STATUS_PARTIAL,
                PurchaseOrder::STATUS_PROCESSING,
            ])
            ->whereNotNull('submitted_at')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN submitted_at >= ? THEN 1 ELSE 0 END), 0) as fresh, '
                .'COALESCE(SUM(CASE WHEN submitted_at < ? AND submitted_at >= ? THEN 1 ELSE 0 END), 0) as recent, '
                .'COALESCE(SUM(CASE WHEN submitted_at < ? AND submitted_at >= ? THEN 1 ELSE 0 END), 0) as stale, '
                .'COALESCE(SUM(CASE WHEN submitted_at < ? THEN 1 ELSE 0 END), 0) as overdue',
                [
                    $threeDays,
                    $threeDays, $sixDays,
                    $sixDays, $elevenDays,
                    $elevenDays,
                ],
            )
            ->first();

        return [
            ['bucket' => '0-2 days', 'orders' => (int) ($buckets?->fresh ?? 0)],
            ['bucket' => '3-5 days', 'orders' => (int) ($buckets?->recent ?? 0)],
            ['bucket' => '6-10 days', 'orders' => (int) ($buckets?->stale ?? 0)],
            ['bucket' => 'Over 10 days', 'orders' => (int) ($buckets?->overdue ?? 0)],
        ];
    }

    /**
     * Reorder retention: customers are grouped by the month of their first ever
     * order, then measured on whether they ordered again in each later month.
     * Period 0 is always 100% -- placing the cohort is what defines it.
     *
     * @return array<int, array{label: string, size: int, retention: array<int, int>}>
     */
    private function reorderCohorts(int $months = 12): array
    {
        $windowStart = now()->startOfMonth()->subMonths($months - 1);

        // A customer's cohort is fixed by their first order ever, so this is
        // deliberately unbounded -- restricting it to the window would file a
        // long-standing customer into a cohort they don't belong to.
        $firstOrders = PurchaseOrder::query()
            ->whereNotNull('submitted_at')
            ->selectRaw('customer_id, MIN(submitted_at) as first_at')
            ->groupBy('customer_id')
            ->pluck('first_at', 'customer_id');

        $cohortOf = [];

        foreach ($firstOrders as $customerId => $firstAt) {
            $firstMonth = Carbon::parse($firstAt)->startOfMonth();

            if ($firstMonth->lt($windowStart)) {
                continue;
            }

            $cohortOf[(int) $customerId] = (int) $windowStart->diffInMonths($firstMonth);
        }

        if ($cohortOf === []) {
            return [];
        }

        $activity = PurchaseOrder::query()
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $windowStart)
            ->whereIn('customer_id', array_keys($cohortOf))
            ->get(['customer_id', 'submitted_at']);

        $activeMonths = [];

        foreach ($activity as $order) {
            $month = (int) $windowStart->diffInMonths($order->submitted_at->copy()->startOfMonth());
            $activeMonths[(int) $order->customer_id][$month] = true;
        }

        $members = [];

        foreach ($cohortOf as $customerId => $cohort) {
            $members[$cohort][] = $customerId;
        }

        $cohorts = [];

        for ($cohort = 0; $cohort < $months; $cohort++) {
            $customerIds = $members[$cohort] ?? [];

            if ($customerIds === []) {
                continue;
            }

            $size = count($customerIds);
            $observed = $months - $cohort;
            $retention = [];

            for ($period = 0; $period < $observed; $period++) {
                $returned = 0;

                foreach ($customerIds as $customerId) {
                    if (isset($activeMonths[$customerId][$cohort + $period])) {
                        $returned++;
                    }
                }

                $retention[] = (int) round(($returned / $size) * 100);
            }

            $cohorts[] = [
                'label' => $windowStart->copy()->addMonths($cohort)->format('M Y'),
                'size' => $size,
                'retention' => $retention,
            ];
        }

        return $cohorts;
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
