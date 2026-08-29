<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\CustomerScope;
use App\Support\OrdersReportExport;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ports app/reports/report_routes.py. No staff-only gate exists in
 * Flask here -- a customer sees both reports too, auto-scoped to their
 * own orders via CustomerScope::forCurrentUser()'s default
 * required=true -- an orphaned customer account (no valid linked
 * Customer) still gets a 403, same as Dashboard/Products/Purchase
 * Orders, not silently shown unscoped (all-customer) data.
 */
class ReportController extends Controller
{
    private const PAGE_SIZE = 25;

    public function overview(Request $request): Response
    {
        $now = CarbonImmutable::now('UTC');
        [$selectedRange, $startDate, $endDate, $periodStart, $periodEnd, $periodLabel] =
            $this->analyticsPeriod($request, $now);

        $linkedCustomer = CustomerScope::forCurrentUser();
        $customerId = $linkedCustomer?->id
            ?? ($request->query('customer_id') ? (int) $request->query('customer_id') : null);

        $statusCounts = PurchaseOrder::where('submitted_at', '>=', $periodStart)
            ->where('submitted_at', '<', $periodEnd)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $customers = $linkedCustomer
            ? collect([['id' => $linkedCustomer->id, 'company_name' => $linkedCustomer->company_name]])
            : Customer::where('is_active', true)->orderBy('company_name')->get(['id', 'company_name']);

        return Inertia::render('Reports/Overview', [
            'filters' => [
                'range' => $selectedRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_label' => $periodLabel,
                'customer_id' => $customerId,
            ],
            'customers' => $customers,
            'isCustomerView' => $linkedCustomer !== null,
            'metrics' => $this->computeFulfillmentMetrics($periodStart, $periodEnd, $customerId),
            'monthlyTrend' => $this->buildMonthlyTrend($periodStart, $periodEnd, $customerId),
            'statusMix' => $this->buildStatusMix($statusCounts),
            'agingRows' => $this->buildAgingRows($periodStart, $periodEnd, $customerId, $now),
            'productPerformance' => $this->buildProductPerformance($periodStart, $periodEnd, $customerId),
            'customerPerformance' => $linkedCustomer ? [] : $this->buildCustomerPerformance($periodStart, $periodEnd, $customerId),
        ]);
    }

    public function orders(Request $request): Response
    {
        [$ordersQuery, $filters] = $this->filteredOrdersQuery($request);

        $linkedCustomer = CustomerScope::forCurrentUser();
        $customers = $linkedCustomer
            ? collect([['id' => $linkedCustomer->id, 'company_name' => $linkedCustomer->company_name]])
            : Customer::orderBy('company_name')->get(['id', 'company_name']);

        $summary = $this->reportSummary($ordersQuery);
        $paginator = (clone $ordersQuery)
            ->with(['customer', 'items'])
            ->orderByDesc('submitted_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => $this->serializeOrderRow($order));

        return Inertia::render('Reports/Orders', [
            'orders' => $paginator,
            'filters' => $filters,
            'customers' => $customers,
            'summary' => $summary,
        ]);
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        [$ordersQuery, $filters] = $this->filteredOrdersQuery($request);
        $summary = $this->reportSummary($ordersQuery);
        $orders = (clone $ordersQuery)
            ->select(['id', 'po_number', 'customer_id', 'status', 'remarks', 'submitted_at'])
            ->with([
                'customer:id,company_name',
                'items:id,purchase_order_id,quantity,delivered_quantity,product_name',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->lazy(250);

        return OrdersReportExport::stream($orders, $filters, $summary);
    }

    private function serializeOrderRow(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'customer_name' => $order->customer?->company_name,
            'products' => $order->items->map(fn ($i) => $i->display_name)->implode(', ') ?: '-',
            'ordered_units' => (int) $order->items->sum('quantity'),
            'delivered_units' => (int) $order->items->sum('delivered_quantity'),
            'balance_units' => $order->balance_units,
            'status' => $order->status,
            'remarks' => $order->remarks,
        ];
    }

    /**
     * @return array{0: Builder<PurchaseOrder>, 1: array}
     */
    private function filteredOrdersQuery(Request $request): array
    {
        $dateFilter = trim((string) $request->query('date_filter', 'all')) ?: 'all';
        $month = trim((string) $request->query('month', now()->format('Y-m'))) ?: now()->format('Y-m');
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));
        $statusFilter = strtolower(trim((string) $request->query('status', 'all'))) ?: 'all';

        $linkedCustomer = CustomerScope::forCurrentUser();
        $customerId = $linkedCustomer?->id
            ?? ($request->query('customer_id') ? (int) $request->query('customer_id') : null);

        $query = PurchaseOrder::query();
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $periodLabel = 'All Dates';

        if ($dateFilter === 'month') {
            $periodStart = CarbonImmutable::createFromFormat('!Y-m', $month, 'UTC');
            if ($periodStart) {
                $periodEnd = $periodStart->addMonthNoOverflow();
                $query->where('submitted_at', '>=', $periodStart)->where('submitted_at', '<', $periodEnd);
                $periodLabel = $periodStart->format('F Y');
            } else {
                $dateFilter = 'all';
            }
        } elseif ($dateFilter === 'custom') {
            $periodStart = ReportPeriod::parseDateOrNull($startDate);
            $selectedEnd = ReportPeriod::parseDateOrNull($endDate);
            if ($periodStart && $selectedEnd && $selectedEnd->gte($periodStart)) {
                $query->where('submitted_at', '>=', $periodStart)->where('submitted_at', '<', $selectedEnd->addDay());
                $periodLabel = $periodStart->format('M d, Y').' - '.$selectedEnd->format('M d, Y');
            } else {
                $dateFilter = 'all';
            }
        } elseif ($dateFilter !== 'all') {
            $dateFilter = 'all';
        }

        if ($statusFilter === 'partial') {
            $query->whereIn('status', PurchaseOrder::IN_PROGRESS_STATUSES);
        } elseif ($statusFilter === PurchaseOrder::STATUS_SUBMITTED) {
            // "Reviewing" is a flavor of "submitted" -- see buildStatusMix().
            $query->whereIn('status', [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_REVIEWING]);
        } elseif (in_array($statusFilter, [
            PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        return [$query, [
            'date_filter' => $dateFilter,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_id' => $customerId,
            'status' => $statusFilter,
            'period_label' => $periodLabel,
        ]];
    }

    /**
     * Compute totals in SQL so the paginated report does not hydrate every
     * matching order and item merely to summarize them.
     *
     * @param  Builder<PurchaseOrder>  $ordersQuery
     */
    private function reportSummary(Builder $ordersQuery): array
    {
        $itemTotals = PurchaseOrderItem::query()
            ->whereIn('purchase_order_id', (clone $ordersQuery)->select('purchase_orders.id'))
            ->selectRaw('COALESCE(SUM(quantity), 0) as ordered_units')
            ->selectRaw('COALESCE(SUM(delivered_quantity), 0) as delivered_units')
            ->first();

        $balanceUnits = PurchaseOrderItem::query()
            ->whereIn('purchase_order_id', (clone $ordersQuery)
                ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
                ->select('purchase_orders.id'))
            ->selectRaw('COALESCE(SUM(quantity - COALESCE(delivered_quantity, 0)), 0) as balance_units')
            ->value('balance_units');

        return [
            'orders' => (clone $ordersQuery)->count(),
            'ordered_units' => (int) ($itemTotals?->ordered_units ?? 0),
            'delivered_units' => (int) ($itemTotals?->delivered_units ?? 0),
            'balance_units' => (int) ($balanceUnits ?? 0),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: CarbonImmutable, 4: CarbonImmutable, 5: string}
     */
    private function analyticsPeriod(Request $request, CarbonImmutable $now): array
    {
        $selectedRange = trim((string) $request->query('range', '6')) ?: '6';
        $startValue = trim((string) $request->query('start_date', ''));
        $endValue = trim((string) $request->query('end_date', ''));

        if ($selectedRange === 'custom') {
            $periodStart = ReportPeriod::parseDateOrNull($startValue);
            $selectedEnd = ReportPeriod::parseDateOrNull($endValue);
            if ($periodStart && $selectedEnd && $selectedEnd->gte($periodStart)) {
                return [
                    'custom', $startValue, $endValue,
                    $periodStart, $selectedEnd->addDay(),
                    $periodStart->format('M d, Y').' - '.$selectedEnd->format('M d, Y'),
                ];
            }
            $selectedRange = '6';
        }

        $presetMonths = ['3' => 3, '6' => 6, '12' => 12];
        if (! isset($presetMonths[$selectedRange])) {
            $selectedRange = '6';
        }
        $monthCount = $presetMonths[$selectedRange];
        $periodStart = ReportPeriod::monthsAgoStart($now, $monthCount - 1);
        $periodEnd = $now->addDay();

        return [$selectedRange, $startValue, $endValue, $periodStart, $periodEnd, "Past {$monthCount} Months"];
    }

    private function computeFulfillmentMetrics(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $customerId,
    ): array {
        $durationExpression = $this->completionDurationDaysExpression();

        $orderMetrics = PurchaseOrder::query()
            ->where('submitted_at', '>=', $periodStart)
            ->where('submitted_at', '<', $periodEnd)
            ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed_orders',
                [PurchaseOrder::STATUS_COMPLETED]
            )
            ->selectRaw(
                "AVG(CASE WHEN status = ? AND completed_at IS NOT NULL AND submitted_at IS NOT NULL THEN {$durationExpression} END) as average_completion_days",
                [PurchaseOrder::STATUS_COMPLETED]
            )
            ->first();

        $itemMetrics = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->selectRaw('COALESCE(SUM(purchase_order_items.quantity), 0) as ordered_units')
            ->selectRaw('COALESCE(SUM(purchase_order_items.delivered_quantity), 0) as delivered_units')
            ->selectRaw('COALESCE(SUM('.$this->pendingUnitsExpression('purchase_order_items').'), 0) as backlog_units')
            ->first();

        $totalOrders = (int) ($orderMetrics?->total_orders ?? 0);
        $completedOrders = (int) ($orderMetrics?->completed_orders ?? 0);
        $orderedUnits = (int) ($itemMetrics?->ordered_units ?? 0);
        $deliveredUnits = (int) ($itemMetrics?->delivered_units ?? 0);

        return [
            'ordered_units' => $orderedUnits,
            'delivered_units' => $deliveredUnits,
            'backlog_units' => (int) ($itemMetrics?->backlog_units ?? 0),
            'fulfillment_rate' => $orderedUnits ? round($deliveredUnits / $orderedUnits * 100, 1) : 0,
            'completion_rate' => $totalOrders ? round($completedOrders / $totalOrders * 100, 1) : 0,
            'average_completion_days' => $orderMetrics?->average_completion_days !== null
                ? round((float) $orderMetrics->average_completion_days, 1)
                : 0,
        ];
    }

    private function analyticsMonths(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $months = [];
        $cursor = $periodStart->startOfMonth();
        $finalMonth = $periodEnd->subSecond()->startOfMonth();
        while ($cursor->lte($finalMonth)) {
            $months[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    private function buildMonthlyTrend(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId): array
    {
        $monthStarts = $this->analyticsMonths($periodStart, $periodEnd);
        $values = [];
        foreach ($monthStarts as $month) {
            $values[$month->format('Y-m')] = ['ordered' => 0, 'delivered' => 0];
        }

        $monthExpression = $this->monthKeyExpression('purchase_orders.submitted_at');
        $rows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->selectRaw("{$monthExpression} as month_key")
            ->selectRaw('COALESCE(SUM(purchase_order_items.quantity), 0) as ordered')
            ->selectRaw('COALESCE(SUM(purchase_order_items.delivered_quantity), 0) as delivered')
            ->groupByRaw($monthExpression)
            ->get();

        foreach ($rows as $row) {
            $key = $row->month_key;
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $values[$key]['ordered'] = (int) $row->ordered;
            $values[$key]['delivered'] = (int) $row->delivered;
        }

        $maximum = 0;
        foreach ($values as $v) {
            $maximum = max($maximum, $v['ordered'], $v['delivered']);
        }
        $height = fn (int $units) => $maximum ? (int) round($units / $maximum * 100) : 0;

        return array_map(function (CarbonImmutable $month) use ($values, $height) {
            $v = $values[$month->format('Y-m')];

            return [
                'label' => $month->format('M'),
                'full_label' => $month->format('F Y'),
                'ordered' => $v['ordered'],
                'delivered' => $v['delivered'],
                'ordered_height' => $height($v['ordered']),
                'delivered_height' => $height($v['delivered']),
            ];
        }, $monthStarts);
    }

    private function buildStatusMix($statusCounts): array
    {
        $total = $statusCounts->sum();
        $labels = [
            PurchaseOrder::STATUS_SUBMITTED => 'Submitted',
            'partial' => 'Partial',
            PurchaseOrder::STATUS_COMPLETED => 'Completed',
            PurchaseOrder::STATUS_CANCELLED => 'Cancelled',
        ];
        $counts = [
            // "Reviewing" is a flavor of "submitted" (see the model
            // constant's docblock) -- bucketed together so it doesn't
            // silently vanish from the mix once any order reaches it.
            PurchaseOrder::STATUS_SUBMITTED => (int) ($statusCounts[PurchaseOrder::STATUS_SUBMITTED] ?? 0)
                + (int) ($statusCounts[PurchaseOrder::STATUS_REVIEWING] ?? 0),
            'partial' => (int) collect(PurchaseOrder::IN_PROGRESS_STATUSES)->sum(fn ($s) => $statusCounts[$s] ?? 0),
            PurchaseOrder::STATUS_COMPLETED => (int) ($statusCounts[PurchaseOrder::STATUS_COMPLETED] ?? 0),
            PurchaseOrder::STATUS_CANCELLED => (int) ($statusCounts[PurchaseOrder::STATUS_CANCELLED] ?? 0),
        ];

        return collect($labels)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $counts[$key],
            'percent' => $total ? (int) round($counts[$key] / $total * 100) : 0,
        ])->values()->all();
    }

    private function buildAgingRows(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $customerId,
        CarbonImmutable $now,
    ): array {
        $definitions = [
            ['label' => '0-2 days', 'min' => 0, 'max' => 2],
            ['label' => '3-7 days', 'min' => 3, 'max' => 7],
            ['label' => '8-14 days', 'min' => 8, 'max' => 14],
            ['label' => '15+ days', 'min' => 15, 'max' => null],
        ];

        $perOrder = DB::table('purchase_orders')
            ->leftJoin('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->whereNotIn('purchase_orders.status', PurchaseOrder::TERMINAL_STATUSES)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->select('purchase_orders.id')
            ->selectRaw($this->ageDaysExpression('purchase_orders.submitted_at').' as age_days', [
                $now->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('COALESCE(SUM('.$this->pendingUnitsExpression('purchase_order_items').'), 0) as balance_units')
            ->groupBy('purchase_orders.id', 'purchase_orders.submitted_at');

        $bucketExpression = "CASE
            WHEN age_days <= 2 THEN '0-2 days'
            WHEN age_days <= 7 THEN '3-7 days'
            WHEN age_days <= 14 THEN '8-14 days'
            ELSE '15+ days'
        END";

        $aggregates = DB::query()
            ->fromSub($perOrder, 'active_orders')
            ->selectRaw("{$bucketExpression} as age_bucket")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(balance_units), 0) as units')
            ->groupByRaw($bucketExpression)
            ->get()
            ->keyBy('age_bucket');

        $rows = collect($definitions)->map(function ($definition) use ($aggregates) {
            $aggregate = $aggregates->get($definition['label']);

            return [
                'label' => $definition['label'],
                'orders' => (int) ($aggregate?->orders ?? 0),
                'units' => (int) ($aggregate?->units ?? 0),
            ];
        });

        $maxUnits = $rows->max('units') ?: 0;

        return $rows->map(fn ($row) => [
            ...$row,
            'percent' => $maxUnits ? (int) round($row['units'] / $maxUnits * 100) : 0,
        ])->all();
    }

    private function buildProductPerformance(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId): array
    {
        // Products left this database in the 2026_08_18 migration, so there is
        // no `products` table to join and no product_id to join on. The name
        // snapshotted onto each item at order time is the only stable key
        // left -- and the only complete one: roughly half of existing items
        // carry no sku, so grouping by that would collapse them into one bucket.
        //
        // Consequence worth knowing: a product renamed upstream now appears as
        // two rows in historical reports, because each order kept the name it
        // was placed under. generic_name was never snapshotted at all, so it
        // is not recoverable for past orders.
        $backlogExpression = 'SUM('.$this->pendingUnitsExpression('purchase_order_items').')';

        $rows = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->groupBy('purchase_order_items.product_name')
            ->select('purchase_order_items.product_name as product_name')
            ->selectRaw('SUM(purchase_order_items.quantity) as ordered')
            ->selectRaw('SUM(COALESCE(purchase_order_items.delivered_quantity, 0)) as delivered')
            ->selectRaw("{$backlogExpression} as backlog")
            ->orderByDesc('backlog')
            ->orderByDesc('ordered')
            ->orderBy('purchase_order_items.product_name')
            ->limit(10)
            ->get();

        return $rows->map(function ($row) {
            $ordered = (int) $row->ordered;
            $delivered = (int) $row->delivered;

            return [
                'product_name' => $row->product_name ?: 'Unknown product',
                'generic_name' => '-',
                'ordered' => $ordered,
                'delivered' => $delivered,
                'backlog' => (int) $row->backlog,
                'rate' => $ordered ? (int) round($delivered / $ordered * 100) : 0,
            ];
        })
            ->values()
            ->all();
    }

    private function buildCustomerPerformance(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId): array
    {
        $pendingUnits = $this->pendingUnitsExpression('purchase_order_items');
        $performance = PurchaseOrder::query()
            ->join('customers', 'customers.id', '=', 'purchase_orders.customer_id')
            ->leftJoin('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->groupBy('purchase_orders.customer_id', 'customers.company_name')
            ->select('customers.company_name as name')
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as orders_count')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN purchase_orders.status = ? THEN purchase_orders.id END) as completed_count',
                [PurchaseOrder::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN purchase_orders.status != ? THEN purchase_order_items.quantity ELSE 0 END), 0) as ordered',
                [PurchaseOrder::STATUS_CANCELLED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN purchase_orders.status != ? THEN COALESCE(purchase_order_items.delivered_quantity, 0) ELSE 0 END), 0) as delivered',
                [PurchaseOrder::STATUS_CANCELLED]
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN purchase_orders.status != ? THEN {$pendingUnits} ELSE 0 END), 0) as backlog",
                [PurchaseOrder::STATUS_CANCELLED]
            )
            ->orderByDesc('ordered')
            ->orderBy('customers.company_name')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $orders = (int) $row->orders_count;
                $completed = (int) $row->completed_count;

                return [
                    'name' => $row->name ?? 'Unknown',
                    'orders' => $orders,
                    'completed' => $completed,
                    'ordered' => (int) $row->ordered,
                    'delivered' => (int) $row->delivered,
                    'backlog' => (int) $row->backlog,
                    'completion_rate' => $orders ? (int) round($completed / $orders * 100) : 0,
                ];
            });

        return $performance
            ->values()
            ->all();
    }

    private function completionDurationDaysExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => 'GREATEST(TIMESTAMPDIFF(SECOND, submitted_at, completed_at), 0) / 86400',
            'sqlite' => 'MAX(julianday(completed_at) - julianday(submitted_at), 0)',
            default => throw new \RuntimeException('Unsupported database driver for report duration aggregates.'),
        };
    }

    private function monthKeyExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => throw new \RuntimeException('Unsupported database driver for report month aggregates.'),
        };
    }

    private function ageDaysExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => "CASE WHEN {$column} IS NULL THEN 0 ELSE GREATEST(TIMESTAMPDIFF(SECOND, {$column}, ?) DIV 86400, 0) END",
            'sqlite' => "CASE WHEN {$column} IS NULL THEN 0 ELSE CAST(MAX(julianday(?) - julianday({$column}), 0) AS INTEGER) END",
            default => throw new \RuntimeException('Unsupported database driver for report aging aggregates.'),
        };
    }

    private function pendingUnitsExpression(string $table): string
    {
        return "CASE
            WHEN {$table}.quantity > COALESCE({$table}.delivered_quantity, 0)
            THEN {$table}.quantity - COALESCE({$table}.delivered_quantity, 0)
            ELSE 0
        END";
    }
}
