import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'active', label: 'Active (Submitted + In Progress)' },
    { value: 'partial', label: 'Partial' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'completed', label: 'Completed' },
];

export default function Index({ orders, filters }) {
    const [search, setSearch] = useState(filters.search);
    const [dateFilter, setDateFilter] = useState(filters.date_filter);
    const [month, setMonth] = useState(filters.month);
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = () => {
        router.get(
            route('purchase-orders.index'),
            {
                search,
                date_filter: dateFilter,
                month,
                start_date: startDate,
                end_date: endDate,
                status,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Purchase Orders
                    </h2>
                    <Link
                        href={route('purchase-orders.create')}
                        className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        Create Order
                    </Link>
                </div>
            }
        >
            <Head title="Purchase Orders" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                    className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm"
                >
                    <label className="flex flex-col text-sm text-gray-600">
                        Customer search
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Company name"
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        />
                    </label>
                    <label className="flex flex-col text-sm text-gray-600">
                        Date filter
                        <select
                            value={dateFilter}
                            onChange={(e) => setDateFilter(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="all">All Time</option>
                            <option value="month">By Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </label>
                    {dateFilter === 'month' && (
                        <label className="flex flex-col text-sm text-gray-600">
                            Month
                            <input
                                type="month"
                                value={month}
                                onChange={(e) => setMonth(e.target.value)}
                                className="mt-1 rounded-md border-gray-300 text-sm"
                            />
                        </label>
                    )}
                    {dateFilter === 'custom' && (
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
                    <label className="flex flex-col text-sm text-gray-600">
                        Status
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        type="submit"
                        className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Apply
                    </button>
                </form>

                <div className="rounded-lg bg-white p-4 shadow-sm">
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
                                    <th className="py-2 pr-4">Balance</th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="py-4 text-center text-gray-400">
                                            No orders match these filters.
                                        </td>
                                    </tr>
                                )}
                                {orders.data.map((order) => {
                                    const badge = statusBadge(order.status);

                                    return (
                                        <tr key={order.id}>
                                            <td className="py-2 pr-4 font-medium text-indigo-600">
                                                <Link href={route('purchase-orders.show', order.id)}>
                                                    {order.po_number}
                                                </Link>
                                                {order.is_awaiting_fulfillment && (
                                                    <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                                                        {order.is_processing ? 'Processing' : 'New Order'}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4">{formatDateTime(order.submitted_at)}</td>
                                            <td className="py-2 pr-4">{order.customer_name}</td>
                                            <td className="py-2 pr-4">{order.item_display_name ?? '-'}</td>
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

                    {orders.last_page > 1 && (
                        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
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
