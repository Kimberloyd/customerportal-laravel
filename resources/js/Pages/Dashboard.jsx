import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import MonthlyVolumeChart from '@/Components/MonthlyVolumeChart';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const RANGE_OPTIONS = [
    { value: 'month', label: 'This Month' },
    { value: '3_months', label: 'Past 3 Months' },
    { value: '6_months', label: 'Past 6 Months' },
    { value: '9_months', label: 'Past 9 Months' },
    { value: '12_months', label: 'Past 12 Months' },
    { value: 'custom', label: 'Custom Period' },
];

const CHART_RANGE_OPTIONS = [
    { value: '3', label: '3 Months' },
    { value: '6', label: '6 Months' },
    { value: '9', label: '9 Months' },
    { value: '12', label: '12 Months' },
    { value: 'custom', label: 'Custom Range' },
];

const PENDING_STATUS_OPTIONS = [
    { value: 'all', label: 'All Pending' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'partial', label: 'Partial' },
];

function KpiCard({ label, value, context }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="text-sm font-medium text-gray-500">{label}</h3>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
            {context && <p className="mt-1 text-xs text-gray-400">{context}</p>}
        </div>
    );
}

export default function Dashboard({ kpis, recentOrders, monthlyVolume, topProducts, pendingOrders, filters }) {
    const [range, setRange] = useState(filters.range);
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [chartRange, setChartRange] = useState(filters.chart_range);
    const [chartStartDate, setChartStartDate] = useState(filters.chart_start_date);
    const [chartEndDate, setChartEndDate] = useState(filters.chart_end_date);
    const [pendingStatus, setPendingStatus] = useState(filters.pending_status);

    const applyFilters = (overrides = {}) => {
        router.get(
            route('dashboard'),
            {
                range,
                start_date: startDate,
                end_date: endDate,
                chart_range: chartRange,
                chart_start_date: chartStartDate,
                chart_end_date: chartEndDate,
                pending_status: pendingStatus,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const recentOrdersColumns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                cell: (order) => (
                    <span className="font-medium text-indigo-600">{order.po_number}</span>
                ),
            },
            { key: 'submitted_at', header: 'Date', cell: (order) => formatDateTime(order.submitted_at) },
            {
                key: 'customer_name',
                header: 'Customer',
                cell: (order) => (
                    <>
                        {order.customer_name}
                        {order.is_awaiting_fulfillment && (
                            <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                                {order.status === 'partial' || order.status === 'processing'
                                    ? 'Processing'
                                    : 'New Order'}
                            </span>
                        )}
                    </>
                ),
            },
            {
                key: 'item_display_name',
                header: 'Product',
                cell: (order) => order.item_display_name ?? '-',
            },
            { key: 'item_quantity', header: 'Qty', cell: (order) => order.item_quantity ?? '-' },
            {
                key: 'status',
                header: 'Status',
                cell: (order) => {
                    const badge = statusBadge(order.status);
                    return (
                        <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>
                            {badge.label}
                        </span>
                    );
                },
            },
        ],
        [],
    );

    const topProductsRows = useMemo(
        () => topProducts.map((product, index) => ({ ...product, __rank: index + 1, __rowId: `${product.product_name}-${index}` })),
        [topProducts],
    );

    const topProductsColumns = useMemo(
        () => [
            { key: '__rank', header: '#' },
            {
                key: 'product_name',
                header: 'Product',
                cell: (product) => (
                    <>
                        <div className="font-medium text-gray-900">{product.product_name}</div>
                        {product.generic_name && product.generic_name !== product.product_name && (
                            <div className="text-xs text-gray-500">{product.generic_name}</div>
                        )}
                    </>
                ),
            },
            { key: 'ordered_units', header: 'Units' },
        ],
        [],
    );

    const pendingOrdersColumns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                sortable: true,
                cell: (order) => (
                    <Link href={`/purchase-orders/${order.id}`} className="font-medium text-indigo-600">
                        {order.po_number}
                    </Link>
                ),
            },
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                cell: (order) => formatDateTime(order.submitted_at),
            },
            { key: 'customer_name', header: 'Customer', sortable: true },
            {
                key: 'item_display_name',
                header: 'Product',
                cell: (order) => (
                    <>
                        {order.item_display_name ?? '-'}
                        {order.item_count > 1 && (
                            <span className="ml-1 text-xs text-gray-400">
                                +{order.item_count - 1} more
                            </span>
                        )}
                    </>
                ),
            },
            { key: 'ordered_quantity', header: 'Ordered', sortable: true },
            { key: 'delivered_quantity', header: 'Delivered', sortable: true },
            { key: 'balance_units', header: 'Balance Units', sortable: true },
            {
                key: 'status',
                header: 'Status',
                cell: (order) => {
                    const badge = statusBadge(order.status);
                    return (
                        <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>
                            {badge.label}
                        </span>
                    );
                },
            },
        ],
        [],
    );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                    className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm"
                >
                    <label className="flex flex-col text-sm text-gray-600">
                        Date period
                        <select
                            value={range}
                            onChange={(e) => setRange(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            {RANGE_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {range === 'custom' && (
                        <>
                            <label className="flex flex-col text-sm text-gray-600">
                                From
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="mt-1 rounded-md border-gray-300 text-sm"
                                />
                            </label>
                            <label className="flex flex-col text-sm text-gray-600">
                                To
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="mt-1 rounded-md border-gray-300 text-sm"
                                />
                            </label>
                        </>
                    )}
                    <button
                        type="submit"
                        className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Apply
                    </button>
                </form>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <KpiCard label="Total Orders" value={kpis.total_orders} />
                    <KpiCard
                        label="Total New Orders"
                        value={kpis.submitted_orders}
                        context={filters.period_label}
                    />
                    <KpiCard label="Awaiting Fulfillment" value={kpis.active_orders} />
                    <KpiCard label="Total Orders Completed" value={kpis.completed_orders} />
                    <KpiCard label="Products" value={kpis.total_products} />
                    {kpis.total_customers !== null && (
                        <KpiCard label="Customers" value={kpis.total_customers} />
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <section className="lg:col-span-2">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>
                                <span className="text-sm text-gray-500">{filters.period_label}</span>
                            </div>
                        </div>
                        <Table
                            data={recentOrders}
                            columns={recentOrdersColumns}
                            getRowId={(order) => String(order.id)}
                            resizable
                            reorderable
                            emptyState={`No orders found for ${filters.period_label}.`}
                        />
                    </section>

                    <div className="space-y-6">
                        <section className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="mb-2 flex items-start justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900">Monthly Volume</h2>
                                    <span className="text-sm text-gray-500">{monthlyVolume.periodLabel}</span>
                                </div>
                                <div className="text-right text-sm text-gray-600">
                                    <div><strong>{monthlyVolume.totals.orders}</strong> orders</div>
                                    <div><strong>{monthlyVolume.totals.units}</strong> units sold</div>
                                </div>
                            </div>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    applyFilters();
                                }}
                                className="mb-3 flex flex-wrap items-end gap-2"
                            >
                                <label className="flex flex-col text-xs text-gray-600">
                                    Period
                                    <select
                                        value={chartRange}
                                        onChange={(e) => setChartRange(e.target.value)}
                                        className="mt-1 rounded-md border-gray-300 text-sm"
                                    >
                                        {CHART_RANGE_OPTIONS.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                {chartRange === 'custom' && (
                                    <>
                                        <label className="flex flex-col text-xs text-gray-600">
                                            From
                                            <input
                                                type="date"
                                                value={chartStartDate}
                                                onChange={(e) => setChartStartDate(e.target.value)}
                                                className="mt-1 rounded-md border-gray-300 text-sm"
                                            />
                                        </label>
                                        <label className="flex flex-col text-xs text-gray-600">
                                            To
                                            <input
                                                type="date"
                                                value={chartEndDate}
                                                onChange={(e) => setChartEndDate(e.target.value)}
                                                className="mt-1 rounded-md border-gray-300 text-sm"
                                            />
                                        </label>
                                    </>
                                )}
                                <button
                                    type="submit"
                                    className="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700"
                                >
                                    Apply
                                </button>
                            </form>

                            <MonthlyVolumeChart months={monthlyVolume.months} periodLabel={monthlyVolume.periodLabel} />
                        </section>

                        <section className="rounded-lg bg-white p-4 shadow-sm">
                            <h2 className="text-lg font-semibold text-gray-900">Top 10 Products by Volume</h2>
                            <span className="text-sm text-gray-500">{monthlyVolume.periodLabel}</span>
                        </section>
                        <Table
                            data={topProductsRows}
                            columns={topProductsColumns}
                            getRowId={(product) => product.__rowId}
                            resizable
                            reorderable
                            emptyState="No product volume for this period."
                        />
                    </div>
                </div>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900">Pending Orders</h2>
                            <span className="text-sm text-gray-500">Submitted and partially fulfilled orders</span>
                        </div>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                applyFilters();
                            }}
                            className="flex items-end gap-2"
                        >
                            <label className="flex flex-col text-xs text-gray-600">
                                Status
                                <select
                                    value={pendingStatus}
                                    onChange={(e) => setPendingStatus(e.target.value)}
                                    className="mt-1 rounded-md border-gray-300 text-sm"
                                >
                                    {PENDING_STATUS_OPTIONS.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <button
                                type="submit"
                                className="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700"
                            >
                                Apply
                            </button>
                        </form>
                    </div>
                </section>

                <Table
                    data={pendingOrders.data}
                    columns={pendingOrdersColumns}
                    getRowId={(order) => String(order.id)}
                    resizable
                    reorderable
                    emptyState="No pending orders match this status."
                />

                {pendingOrders.last_page > 1 && (
                    <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
                        {pendingOrders.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded px-3 py-1 ${
                                    link.active
                                        ? 'bg-gray-800 text-white'
                                        : link.url
                                          ? 'text-gray-600 hover:bg-gray-100'
                                          : 'cursor-not-allowed text-gray-300'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
