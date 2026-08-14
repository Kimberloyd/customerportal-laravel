import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">PO Number</th>
                                    <th className="py-2 pr-4">Date</th>
                                    <th className="py-2 pr-4">Customer</th>
                                    <th className="py-2 pr-4">Products</th>
                                    <th className="py-2 pr-4">Ordered</th>
                                    <th className="py-2 pr-4">Delivered</th>
                                    <th className="py-2 pr-4">Balance</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Remarks</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.length === 0 && (
                                    <tr><td colSpan={9} className="py-4 text-center text-gray-400">No orders match these filters.</td></tr>
                                )}
                                {orders.data.map((order) => {
                                    const badge = statusBadge(order.status);

                                    return (
                                        <tr key={order.id}>
                                            <td className="py-2 pr-4 font-medium text-indigo-600">
                                                <Link href={`/purchase-orders/${order.id}`}>{order.po_number}</Link>
                                            </td>
                                            <td className="py-2 pr-4">{formatDateTime(order.submitted_at)}</td>
                                            <td className="py-2 pr-4">{order.customer_name}</td>
                                            <td className="py-2 pr-4">{order.products}</td>
                                            <td className="py-2 pr-4">{order.ordered_units}</td>
                                            <td className="py-2 pr-4">{order.delivered_units}</td>
                                            <td className="py-2 pr-4">{order.balance_units}</td>
                                            <td className="py-2 pr-4">
                                                <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>{badge.label}</span>
                                            </td>
                                            <td className="py-2 pr-4">{order.remarks ?? '-'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
