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
use Illuminate\Support\Collection;
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

        $operationalOrders = PurchaseOrder::with('items')
            ->where('submitted_at', '>=', $periodStart)
            ->where('submitted_at', '<', $periodEnd)
            ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->get();

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
            'metrics' => $this->computeFulfillmentMetrics($operationalOrders),
            'monthlyTrend' => $this->buildMonthlyTrend($periodStart, $periodEnd, $customerId),
            'statusMix' => $this->buildStatusMix($statusCounts),
            'agingRows' => $this->buildAgingRows($operationalOrders, $now),
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
            ->with(['customer', 'items'])
            ->orderByDesc('submitted_at')
            ->get();

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
        } elseif (in_array($statusFilter, [
            PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED,
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

    /**
     * @param  Collection<int, PurchaseOrder>  $operationalOrders
     */
    private function computeFulfillmentMetrics(Collection $operationalOrders): array
    {
        $orderedUnits = (int) $operationalOrders->flatMap->items->sum('quantity');
        $deliveredUnits = (int) $operationalOrders->flatMap->items->sum('delivered_quantity');
        $backlogUnits = (int) $operationalOrders->sum(fn (PurchaseOrder $o) => $o->balance_units);

        $completedOrders = $operationalOrders->where('status', PurchaseOrder::STATUS_COMPLETED);
        // completed_at is set exactly once, when an order first becomes
        // completed; updated_at changes on any later edit (e.g. a
        // remarks update on an already-completed order), which would
        // otherwise silently inflate or shrink the reported duration.
        $completionDurations = $completedOrders
            ->filter(fn (PurchaseOrder $o) => $o->completed_at && $o->submitted_at)
            ->map(fn (PurchaseOrder $o) => max($o->completed_at->getTimestamp() - $o->submitted_at->getTimestamp(), 0) / 86400);

        return [
            'ordered_units' => $orderedUnits,
            'delivered_units' => $deliveredUnits,
            'backlog_units' => $backlogUnits,
            'fulfillment_rate' => $orderedUnits ? round($deliveredUnits / $orderedUnits * 100, 1) : 0,
            'completion_rate' => $operationalOrders->count() ? round($completedOrders->count() / $operationalOrders->count() * 100, 1) : 0,
            'average_completion_days' => $completionDurations->count() ? round($completionDurations->avg(), 1) : 0,
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

        $rows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->get([
                'purchase_orders.submitted_at as submitted_at',
                'purchase_order_items.quantity as quantity',
                'purchase_order_items.delivered_quantity as delivered_quantity',
            ]);

        foreach ($rows as $row) {
            if (! $row->submitted_at) {
                continue;
            }
            $key = CarbonImmutable::parse($row->submitted_at)->format('Y-m');
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $values[$key]['ordered'] += (int) $row->quantity;
            $values[$key]['delivered'] += (int) ($row->delivered_quantity ?? 0);
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
            PurchaseOrder::STATUS_SUBMITTED => (int) ($statusCounts[PurchaseOrder::STATUS_SUBMITTED] ?? 0),
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

    /**
     * @param  Collection<int, PurchaseOrder>  $operationalOrders
     */
    private function buildAgingRows(Collection $operationalOrders, CarbonImmutable $now): array
    {
        $definitions = [
            ['label' => '0-2 days', 'min' => 0, 'max' => 2],
            ['label' => '3-7 days', 'min' => 3, 'max' => 7],
            ['label' => '8-14 days', 'min' => 8, 'max' => 14],
            ['label' => '15+ days', 'min' => 15, 'max' => null],
        ];

        $activeOrders = $operationalOrders->whereNotIn('status', PurchaseOrder::TERMINAL_STATUSES);

        $rows = collect($definitions)->map(function ($def) use ($activeOrders, $now) {
            $matching = $activeOrders->filter(function (PurchaseOrder $order) use ($def, $now) {
                $age = $order->submitted_at ? max(intdiv($now->getTimestamp() - $order->submitted_at->getTimestamp(), 86400), 0) : 0;

                return $age >= $def['min'] && ($def['max'] === null || $age <= $def['max']);
            });

            return [
                'label' => $def['label'],
                'orders' => $matching->count(),
                'units' => (int) $matching->sum(fn (PurchaseOrder $o) => $o->balance_units),
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
        $rows = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->groupBy('purchase_order_items.product_name')
            ->get([
                'purchase_order_items.product_name as product_name',
                DB::raw('SUM(purchase_order_items.quantity) as ordered'),
                DB::raw('SUM(purchase_order_items.delivered_quantity) as delivered'),
            ]);

        return $rows->map(function ($row) {
            $ordered = (int) $row->ordered;
            $delivered = (int) $row->delivered;

            return [
                'product_name' => $row->product_name ?: 'Unknown product',
                'generic_name' => '-',
                'ordered' => $ordered,
                'delivered' => $delivered,
                'backlog' => max($ordered - $delivered, 0),
                'rate' => $ordered ? (int) round($delivered / $ordered * 100) : 0,
            ];
        })
            ->sortBy([
                ['backlog', 'desc'],
                ['ordered', 'desc'],
                ['product_name', 'asc'],
            ])
            ->take(10)
            ->values()
            ->all();
    }

    private function buildCustomerPerformance(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId): array
    {
        $orderCounts = PurchaseOrder::where('submitted_at', '>=', $periodStart)
            ->where('submitted_at', '<', $periodEnd)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->select('customer_id', DB::raw('count(*) as orders_count'), DB::raw("sum(case when status = 'completed' then 1 else 0 end) as completed_count"))
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $itemRows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->when($customerId, fn ($q) => $q->where('purchase_orders.customer_id', $customerId))
            ->get([
                'purchase_orders.customer_id as customer_id',
                'purchase_order_items.quantity as quantity',
                'purchase_order_items.delivered_quantity as delivered_quantity',
            ]);

        $itemTotals = [];
        foreach ($itemRows as $row) {
            $cid = $row->customer_id;
            $itemTotals[$cid] ??= ['ordered' => 0, 'delivered' => 0, 'backlog' => 0];
            $itemTotals[$cid]['ordered'] += (int) $row->quantity;
            $itemTotals[$cid]['delivered'] += (int) ($row->delivered_quantity ?? 0);
            $itemTotals[$cid]['backlog'] += max((int) $row->quantity - (int) ($row->delivered_quantity ?? 0), 0);
        }

        $customerNames = Customer::whereIn('id', $orderCounts->keys())->pluck('company_name', 'id');

        $performance = $orderCounts->map(function ($row, $cid) use ($itemTotals, $customerNames) {
            $totals = $itemTotals[$cid] ?? ['ordered' => 0, 'delivered' => 0, 'backlog' => 0];

            return [
                'name' => $customerNames[$cid] ?? 'Unknown',
                'orders' => (int) $row->orders_count,
                'completed' => (int) $row->completed_count,
                'ordered' => $totals['ordered'],
                'delivered' => $totals['delivered'],
                'backlog' => $totals['backlog'],
                'completion_rate' => $row->orders_count ? (int) round($row->completed_count / $row->orders_count * 100) : 0,
            ];
        })->values();

        return $performance
            ->sortBy([
                ['ordered', 'desc'],
                ['name', 'asc'],
            ])
            ->take(10)
            ->values()
            ->all();
    }
}
