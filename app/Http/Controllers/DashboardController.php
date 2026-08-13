<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\CustomerScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports app/dashboard/dashboard_routes.py from the Flask app. Two
 * independent date-range controls (KPI/recent-orders "range" vs
 * chart/top-products "chart_range"), both defaulting to presets, both
 * falling back to "month"/"6" respectively on invalid custom input --
 * mirrors the Flask helpers' exact fallback behavior.
 */
class DashboardController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 25;

    public function index(Request $request): Response
    {
        $now = CarbonImmutable::now('UTC');
        $customer = CustomerScope::forCurrentUser();
        $customerScopeId = $customer?->id;

        $pendingPage = max((int) $request->query('pending_page', 1), 1);
        $pendingStatus = trim((string) $request->query('pending_status', 'all')) ?: 'all';

        [$selectedRange, $startDate, $endDate, $periodStart, $periodEnd, $periodLabel] =
            $this->dashboardPeriod($request, $now);
        [$chartRange, $chartStartDate, $chartEndDate, $chartPeriodStart, $chartPeriodEnd, $chartPeriodLabel] =
            $this->chartPeriod($request, $now);

        [$recentOrders, $submittedOrders] = $this->fetchRecentAndSubmittedOrders(
            $periodStart, $periodEnd, $customerScopeId
        );
        $activeOrders = $this->countActiveOrders($customerScopeId);
        $completedOrders = $this->countCompletedOrders($customerScopeId);
        $newOrdersToday = $this->countNewOrdersToday($customerScopeId, $now);
        $monthlyVolume = $this->monthlyOrderVolume($chartPeriodStart, $chartPeriodEnd, $customerScopeId);
        $topProducts = $this->topProductsByVolume($chartPeriodStart, $chartPeriodEnd, $customerScopeId);

        [$pendingStatus, $pendingOrders] = $this->fetchPendingOrders(
            $customerScopeId, $pendingStatus, $pendingPage
        );

        [$totalCustomers, $totalProducts, $totalOrders] = $this->dashboardTotals($customerScopeId);

        return Inertia::render('Dashboard', [
            'kpis' => [
                'total_orders' => $totalOrders,
                'submitted_orders' => $submittedOrders,
                'active_orders' => $activeOrders,
                'completed_orders' => $completedOrders,
                'total_products' => $totalProducts,
                'total_customers' => $customerScopeId ? null : $totalCustomers,
                'new_orders_today' => $newOrdersToday,
            ],
            'recentOrders' => $recentOrders->map(fn (PurchaseOrder $order) => $this->serializeOrder($order))->values(),
            'monthlyVolume' => [
                'months' => $monthlyVolume,
                'periodLabel' => $chartPeriodLabel,
                'totals' => [
                    'orders' => array_sum(array_column($monthlyVolume, 'count')),
                    'units' => array_sum(array_column($monthlyVolume, 'units')),
                ],
            ],
            'topProducts' => $topProducts,
            'pendingOrders' => $pendingOrders->through(fn (PurchaseOrder $order) => $this->serializeOrder($order, withItemTotals: true)),
            'filters' => [
                'range' => $selectedRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_label' => $periodLabel,
                'chart_range' => $chartRange,
                'chart_start_date' => $chartStartDate,
                'chart_end_date' => $chartEndDate,
                'chart_period_label' => $chartPeriodLabel,
                'pending_status' => $pendingStatus,
            ],
        ]);
    }

    private function serializeOrder(PurchaseOrder $order, bool $withItemTotals = false): array
    {
        $item = $order->primary_item;

        $data = [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'status' => $order->status,
            'is_awaiting_fulfillment' => $order->is_awaiting_fulfillment,
            'customer_name' => $order->customer?->company_name,
            'item_display_name' => $item?->display_name,
            'item_quantity' => $item?->quantity,
            'item_count' => $order->items->count(),
        ];

        if ($withItemTotals) {
            $data['ordered_quantity'] = (int) $order->items->sum('quantity');
            $data['delivered_quantity'] = (int) $order->items->sum('delivered_quantity');
            $data['balance_units'] = $order->balance_units;
        }

        return $data;
    }

    /**
     * Carbon::createFromFormat() throws on malformed input rather than
     * returning false (unlike PHP's native DateTime::createFromFormat)
     * -- this normalizes that to null so callers can fall back the same
     * way Flask's `except ValueError` does for bad custom-range input.
     */
    private function parseDateOrNull(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date ?: null;
    }

    private function monthsAgoStart(CarbonImmutable $current, int $monthsAgo): CarbonImmutable
    {
        $monthIndex = $current->year * 12 + $current->month - 1 - $monthsAgo;
        $year = intdiv($monthIndex, 12);
        $zeroBasedMonth = $monthIndex % 12;
        if ($zeroBasedMonth < 0) {
            $zeroBasedMonth += 12;
            $year--;
        }

        return CarbonImmutable::create($year, $zeroBasedMonth + 1, 1, 0, 0, 0, 'UTC');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: CarbonImmutable, 4: CarbonImmutable, 5: string}
     */
    private function dashboardPeriod(Request $request, CarbonImmutable $now): array
    {
        $todayStart = $now->startOfDay();
        $tomorrowStart = $todayStart->addDay();
        $selectedRange = trim((string) $request->query('range', 'month')) ?: 'month';
        $startValue = trim((string) $request->query('start_date', ''));
        $endValue = trim((string) $request->query('end_date', ''));

        $presets = [
            'month' => [$now->startOfMonth(), $tomorrowStart, $now->format('F Y')],
            '3_months' => [$this->monthsAgoStart($now, 2), $tomorrowStart, 'Past 3 Months'],
            '6_months' => [$this->monthsAgoStart($now, 5), $tomorrowStart, 'Past 6 Months'],
            '9_months' => [$this->monthsAgoStart($now, 8), $tomorrowStart, 'Past 9 Months'],
            '12_months' => [$this->monthsAgoStart($now, 11), $tomorrowStart, 'Past 12 Months'],
        ];

        if ($selectedRange !== 'custom') {
            if (! isset($presets[$selectedRange])) {
                $selectedRange = 'month';
            }
            [$periodStart, $periodEnd, $periodLabel] = $presets[$selectedRange];

            return [
                $selectedRange,
                $periodStart->format('Y-m-d'),
                $todayStart->format('Y-m-d'),
                $periodStart,
                $periodEnd,
                $periodLabel,
            ];
        }

        $periodStart = $this->parseDateOrNull($startValue);
        $selectedEnd = $this->parseDateOrNull($endValue);

        if (! $periodStart || ! $selectedEnd || $selectedEnd->lt($periodStart)) {
            [$periodStart, $periodEnd, $periodLabel] = $presets['month'];

            return [
                'month',
                $periodStart->format('Y-m-d'),
                $todayStart->format('Y-m-d'),
                $periodStart,
                $periodEnd,
                $periodLabel,
            ];
        }

        $periodStart = $periodStart->startOfDay();
        $selectedEnd = $selectedEnd->startOfDay();

        return [
            'custom',
            $startValue,
            $endValue,
            $periodStart,
            $selectedEnd->addDay(),
            $periodStart->format('M d, Y').' - '.$selectedEnd->format('M d, Y'),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: CarbonImmutable, 4: CarbonImmutable, 5: string}
     */
    private function chartPeriod(Request $request, CarbonImmutable $now): array
    {
        $selectedRange = trim((string) $request->query('chart_range', '6')) ?: '6';
        $startValue = trim((string) $request->query('chart_start_date', ''));
        $endValue = trim((string) $request->query('chart_end_date', ''));
        $todayStart = $now->startOfDay();
        $tomorrowStart = $todayStart->addDay();
        $presetMonths = ['3' => 3, '6' => 6, '9' => 9, '12' => 12];

        if (isset($presetMonths[$selectedRange])) {
            $monthCount = $presetMonths[$selectedRange];
            $periodStart = $this->monthsAgoStart($now, $monthCount - 1);

            return [
                $selectedRange,
                $periodStart->format('Y-m-d'),
                $todayStart->format('Y-m-d'),
                $periodStart,
                $tomorrowStart,
                "Past {$monthCount} Months",
            ];
        }

        if ($selectedRange === 'custom') {
            $periodStart = $this->parseDateOrNull($startValue);
            $selectedEnd = $this->parseDateOrNull($endValue);

            if ($periodStart && $selectedEnd && $selectedEnd->gte($periodStart)) {
                $periodStart = $periodStart->startOfDay();
                $selectedEnd = $selectedEnd->startOfDay();

                return [
                    'custom',
                    $startValue,
                    $endValue,
                    $periodStart,
                    $selectedEnd->addDay(),
                    $periodStart->format('M d, Y').' - '.$selectedEnd->format('M d, Y'),
                ];
            }
        }

        $periodStart = $this->monthsAgoStart($now, 5);

        return [
            '6',
            $periodStart->format('Y-m-d'),
            $todayStart->format('Y-m-d'),
            $periodStart,
            $tomorrowStart,
            'Past 6 Months',
        ];
    }

    /**
     * @return array<int, array{label: string, full_label: string, count: int, units: int, height: int}>
     */
    private function monthlyOrderVolume(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId): array
    {
        $monthStarts = [];
        $cursor = $periodStart->startOfMonth();
        while ($cursor->lt($periodEnd)) {
            $monthStarts[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
        }

        $counts = [];
        $unitCounts = [];
        foreach ($monthStarts as $monthStart) {
            $key = $monthStart->format('Y-m');
            $counts[$key] = 0;
            $unitCounts[$key] = 0;
        }

        $submittedDatesQuery = PurchaseOrder::query()
            ->where('submitted_at', '>=', $periodStart)
            ->where('submitted_at', '<', $periodEnd);
        if ($customerId) {
            $submittedDatesQuery->where('customer_id', $customerId);
        }
        foreach ($submittedDatesQuery->pluck('submitted_at') as $submittedAt) {
            if (! $submittedAt) {
                continue;
            }
            $key = $submittedAt->format('Y-m');
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        // "Units sold" means units actually delivered, not merely ordered
        // -- an order still pending or partially fulfilled hasn't been
        // sold yet, and a cancelled one never will be.
        $itemRowsQuery = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd)
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED);
        if ($customerId) {
            $itemRowsQuery->where('purchase_orders.customer_id', $customerId);
        }
        $itemRows = $itemRowsQuery->get([
            'purchase_orders.submitted_at as submitted_at',
            'purchase_order_items.delivered_quantity as delivered_quantity',
        ]);
        foreach ($itemRows as $row) {
            if (! $row->submitted_at) {
                continue;
            }
            $key = CarbonImmutable::parse($row->submitted_at)->format('Y-m');
            if (array_key_exists($key, $unitCounts)) {
                $unitCounts[$key] += (int) ($row->delivered_quantity ?? 0);
            }
        }

        $maximum = $counts === [] ? 0 : max($counts);

        return array_map(function (CarbonImmutable $monthStart) use ($counts, $unitCounts, $maximum) {
            $key = $monthStart->format('Y-m');

            return [
                'label' => $monthStart->format('M'),
                'full_label' => $monthStart->format('F Y'),
                'count' => $counts[$key],
                'units' => $unitCounts[$key],
                'height' => $maximum ? (int) round($counts[$key] / $maximum * 100) : 0,
            ];
        }, $monthStarts);
    }

    /**
     * @return array<int, array{product_name: string, generic_name: ?string, ordered_units: int}>
     */
    private function topProductsByVolume(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerId, int $limit = 10): array
    {
        $query = DB::table('purchase_order_items')
            ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.submitted_at', '>=', $periodStart)
            ->where('purchase_orders.submitted_at', '<', $periodEnd);
        if ($customerId) {
            $query->where('purchase_orders.customer_id', $customerId);
        }

        $rows = $query
            ->groupBy('products.id', 'products.product_name', 'products.generic_name')
            ->orderByDesc(DB::raw('SUM(purchase_order_items.quantity)'))
            ->orderBy('products.product_name')
            ->limit($limit)
            ->get([
                'products.product_name as product_name',
                'products.generic_name as generic_name',
                DB::raw('SUM(purchase_order_items.quantity) as ordered_units'),
            ]);

        return $rows->map(fn ($row) => [
            'product_name' => $row->product_name,
            'generic_name' => $row->generic_name,
            'ordered_units' => (int) $row->ordered_units,
        ])->all();
    }

    private function scopeToCustomer($query, ?int $customerScopeId)
    {
        if ($customerScopeId) {
            return $query->where('customer_id', $customerScopeId);
        }

        return $query;
    }

    private function fetchRecentAndSubmittedOrders(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $customerScopeId): array
    {
        $periodOrdersQuery = $this->scopeToCustomer(
            PurchaseOrder::query()
                ->where('submitted_at', '>=', $periodStart)
                ->where('submitted_at', '<', $periodEnd),
            $customerScopeId
        );

        $recentOrders = (clone $periodOrdersQuery)
            ->with(['customer', 'items'])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        $submittedOrders = (clone $periodOrdersQuery)
            ->where('status', PurchaseOrder::STATUS_SUBMITTED)
            ->count();

        return [$recentOrders, $submittedOrders];
    }

    private function countActiveOrders(?int $customerScopeId): int
    {
        return $this->scopeToCustomer(
            PurchaseOrder::query()->whereNotIn('status', PurchaseOrder::TERMINAL_STATUSES),
            $customerScopeId
        )->count();
    }

    private function countCompletedOrders(?int $customerScopeId): int
    {
        return $this->scopeToCustomer(
            PurchaseOrder::query()->where('status', PurchaseOrder::STATUS_COMPLETED),
            $customerScopeId
        )->count();
    }

    private function countNewOrdersToday(?int $customerScopeId, CarbonImmutable $now): int
    {
        $todayStart = $now->startOfDay();
        $tomorrowStart = $todayStart->addDay();

        return $this->scopeToCustomer(
            PurchaseOrder::query()
                ->where('status', PurchaseOrder::STATUS_SUBMITTED)
                ->where('submitted_at', '>=', $todayStart)
                ->where('submitted_at', '<', $tomorrowStart),
            $customerScopeId
        )->count();
    }

    private function fetchPendingOrders(?int $customerScopeId, string $pendingStatus, int $pendingPage): array
    {
        $pendingStatuses = array_merge([PurchaseOrder::STATUS_SUBMITTED], PurchaseOrder::IN_PROGRESS_STATUSES);

        $query = $this->scopeToCustomer(
            PurchaseOrder::query()->with(['customer', 'items'])->whereIn('status', $pendingStatuses),
            $customerScopeId
        );

        if ($pendingStatus === 'submitted') {
            $query->where('status', PurchaseOrder::STATUS_SUBMITTED);
        } elseif ($pendingStatus === 'partial') {
            $query->whereIn('status', PurchaseOrder::IN_PROGRESS_STATUSES);
        } else {
            $pendingStatus = 'all';
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderByDesc('submitted_at')
            ->paginate(self::DEFAULT_PAGE_SIZE, ['*'], 'pending_page', $pendingPage)
            ->withQueryString();

        return [$pendingStatus, $paginator];
    }

    private function dashboardTotals(?int $customerScopeId): array
    {
        $totalCustomers = $customerScopeId ? 1 : Customer::query()->count();
        $totalProducts = \App\Models\Product::query()->count();
        $totalOrders = $customerScopeId
            ? PurchaseOrder::query()->where('customer_id', $customerScopeId)->count()
            : PurchaseOrder::query()->count();

        return [$totalCustomers, $totalProducts, $totalOrders];
    }
}
