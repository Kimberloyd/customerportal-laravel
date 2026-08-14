import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'partial', label: 'Partial' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function Orders({ orders, filters, customers, summary }) {
    const [dateFilter, setDateFilter] = useState(filters.date_filter);
    const [month, setMonth] = useState(filters.month);
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [customerId, setCustomerId] = useState(filters.customer_id ?? '');
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(
            route('reports.orders'),
            { date_filter: dateFilter, month, start_date: startDate, end_date: endDate, customer_id: customerId, status },
            { preserveState: true },
        );
    };

    const exportUrl = route('reports.orders.export', {
        date_filter: dateFilter,
        month,
        start_date: startDate,
        end_date: endDate,
        customer_id: customerId,
        status,
    });

    const columns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                cell: (order) => (
                    <Link href={`/purchase-orders/${order.id}`} className="font-medium text-indigo-600">
                        {order.po_number}
                    </Link>
                ),
            },
            { key: 'submitted_at', header: 'Date', cell: (order) => formatDateTime(order.submitted_at) },
            { key: 'customer_name', header: 'Customer' },
            { key: 'products', header: 'Products' },
            { key: 'ordered_units', header: 'Ordered' },
            { key: 'delivered_units', header: 'Delivered' },
            { key: 'balance_units', header: 'Balance' },
            {
                key: 'status',
                header: 'Status',
                cell: (order) => {
                    const badge = statusBadge(order.status);
                    return (
                        <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>{badge.label}</span>
                    );
                },
            },
            { key: 'remarks', header: 'Remarks', cell: (order) => order.remarks ?? '-' },
        ],
        [],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="no-print flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Orders Report</h2>
                    <div className="flex gap-2">
                        <button
                            onClick={() => window.print()}
                            className="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Print
                        </button>
                        <a
                            href={exportUrl}
                            className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            Export Spreadsheet
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="Orders Report" />
            <style>{'@media print { .no-print { display: none !important; } }'}</style>

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={applyFilters} className="no-print flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <label className="flex flex-col text-sm text-gray-600">
                        Date filter
                        <select value={dateFilter} onChange={(e) => setDateFilter(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                            <option value="all">All Dates</option>
                            <option value="month">By Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </label>
                    {dateFilter === 'month' && (
                        <label className="flex flex-col text-sm text-gray-600">
                            Month
                            <input type="month" value={month} onChange={(e) => setMonth(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm" />
                        </label>
                    )}
                    {dateFilter === 'custom' && (
                        <>
                            <label className="flex flex-col text-sm text-gray-600">
                                From
                                <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm" />
                            </label>
                            <label className="flex flex-col text-sm text-gray-600">
                                To
                                <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm" />
                            </label>
                        </>
                    )}
                    {customers.length > 1 && (
                        <label className="flex flex-col text-sm text-gray-600">
                            Customer
                            <select value={customerId} onChange={(e) => setCustomerId(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                                <option value="">All Customers</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.company_name}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="flex flex-col text-sm text-gray-600">
                        Status
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Apply
                    </button>
                </form>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="text-sm font-medium text-gray-500">Total Orders</h3>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">{summary.orders}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="text-sm font-medium text-gray-500">Ordered Units</h3>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">{summary.ordered_units}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="text-sm font-medium text-gray-500">Delivered Units</h3>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">{summary.delivered_units}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="text-sm font-medium text-gray-500">Balance Units</h3>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">{summary.balance_units}</p>
                    </div>
                </div>

                <>
                    <Table
                        data={orders.data}
                        columns={columns}
                        getRowId={(order) => String(order.id)}
                        resizable
                        reorderable
                        emptyState="No orders match these filters."
                    />

                    {orders.last_page > 1 && (
                        <nav className="no-print mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {orders.links.map((link, index) => (
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
                </>
            </div>
        </AuthenticatedLayout>
    );
}
