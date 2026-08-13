import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MonthlyVolumeChart from '@/Components/MonthlyVolumeChart';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
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
                    <section className="rounded-lg bg-white p-4 shadow-sm lg:col-span-2">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Recent Orders</h2>
                                <span className="text-sm text-gray-500">{filters.period_label}</span>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr className="text-left text-gray-500">
                                        <th className="py-2 pr-4">PO Number</th>
                                        <th className="py-2 pr-4">Date</th>
                                        <th className="py-2 pr-4">Customer</th>
                                        <th className="py-2 pr-4">Product</th>
                                        <th className="py-2 pr-4">Qty</th>
                                        <th className="py-2 pr-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {recentOrders.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="py-4 text-center text-gray-400">
                                                No orders found for {filters.period_label}.
                                            </td>
                                        </tr>
                                    )}
                                    {recentOrders.map((order) => {
                                        const badge = statusBadge(order.status);

                                        return (
                                            <tr key={order.id}>
                                                <td className="py-2 pr-4 font-medium text-indigo-600">
                                                    {order.po_number}
                                                </td>
                                                <td className="py-2 pr-4">{formatDateTime(order.submitted_at)}</td>
                                                <td className="py-2 pr-4">
                                                    {order.customer_name}
                                                    {order.is_awaiting_fulfillment && (
                                                        <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                                                            {order.status === 'partial' || order.status === 'processing'
                                                                ? 'Processing'
                                                                : 'New Order'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2 pr-4">{order.item_display_name ?? '-'}</td>
                                                <td className="py-2 pr-4">{order.item_quantity ?? '-'}</td>
                                                <td className="py-2 pr-4">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>
                                                        {badge.label}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
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
                            <table className="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr className="text-left text-gray-500">
                                        <th className="py-1 pr-2">#</th>
                                        <th className="py-1 pr-2">Product</th>
                                        <th className="py-1 pr-2">Units</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {topProducts.length === 0 && (
                                        <tr>
                                            <td colSpan={3} className="py-3 text-center text-gray-400">
                                                No product volume for this period.
                                            </td>
                                        </tr>
                                    )}
                                    {topProducts.map((product, index) => (
                                        <tr key={product.product_name + index}>
                                            <td className="py-1 pr-2">{index + 1}</td>
                                            <td className="py-1 pr-2">
                                                <div className="font-medium text-gray-900">{product.product_name}</div>
                                                {product.generic_name && product.generic_name !== product.product_name && (
                                                    <div className="text-xs text-gray-500">{product.generic_name}</div>
                                                )}
                                            </td>
                                            <td className="py-1 pr-2">{product.ordered_units}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    </div>
                </div>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
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

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">PO Number</th>
                                    <th className="py-2 pr-4">Date</th>
                                    <th className="py-2 pr-4">Customer</th>
                                    <th className="py-2 pr-4">Product</th>
                                    <th className="py-2 pr-4">Ordered</th>
                                    <th className="py-2 pr-4">Delivered</th>
                                    <th className="py-2 pr-4">Balance Units</th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {pendingOrders.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="py-4 text-center text-gray-400">
                                            No pending orders match this status.
                                        </td>
                                    </tr>
                                )}
                                {pendingOrders.data.map((order) => {
                                    const badge = statusBadge(order.status);

                                    return (
                                        <tr key={order.id}>
                                            <td className="py-2 pr-4 font-medium text-indigo-600">
                                                <Link href={`/purchase-orders/${order.id}`}>{order.po_number}</Link>
                                            </td>
                                            <td className="py-2 pr-4">{formatDateTime(order.submitted_at)}</td>
                                            <td className="py-2 pr-4">{order.customer_name}</td>
                                            <td className="py-2 pr-4">
                                                {order.item_display_name ?? '-'}
                                                {order.item_count > 1 && (
                                                    <span className="ml-1 text-xs text-gray-400">
                                                        +{order.item_count - 1} more
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4">{order.ordered_quantity}</td>
                                            <td className="py-2 pr-4">{order.delivered_quantity}</td>
                                            <td className="py-2 pr-4">{order.balance_units}</td>
                                            <td className="py-2 pr-4">
                                                <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>
                                                    {badge.label}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

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
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
