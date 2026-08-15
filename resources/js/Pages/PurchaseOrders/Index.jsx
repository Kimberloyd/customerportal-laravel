import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

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

    const columns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                sortable: true,
                cell: (order) => (
                    <>
                        <Link href={route('purchase-orders.show', order.id)} className="font-medium text-indigo-600">
                            {order.po_number}
                        </Link>
                        {order.is_awaiting_fulfillment && (
                            <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                                {order.is_processing ? 'Processing' : 'New Order'}
                            </span>
                        )}
                    </>
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
                sortable: true,
                cell: (order) => order.item_display_name ?? '-',
            },
            { key: 'ordered_quantity', header: 'Ordered', sortable: true, align: 'right' },
            { key: 'delivered_quantity', header: 'Delivered', sortable: true, align: 'right' },
            { key: 'balance_units', header: 'Balance', sortable: true, align: 'right' },
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
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Orders
                    </h2>
                    <Button asChild variant="primary">
                        <Link href={route('purchase-orders.create')}>Create Order</Link>
                    </Button>
                </div>
            }
        >
            <Head title="Orders" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                    className="flex flex-wrap items-end gap-3 bg-white"
                >
                    <Input
                        label="Customer search"
                        type="text"
                        value={search}
                        onChange={setSearch}
                        placeholder="Company name"
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{ field: 'h-9 rounded-none' }}
                    />
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
                        <Input label="Month" type="month" value={month} onChange={setMonth} />
                    )}
                    {dateFilter === 'custom' && (
                        <>
                            <Input label="From" type="date" value={startDate} onChange={setStartDate} />
                            <Input label="To" type="date" value={endDate} onChange={setEndDate} />
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
                    <Button type="submit" variant="secondary" size="compact">
                        Apply
                    </Button>
                </form>

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
                </>
            </div>
        </AuthenticatedLayout>
    );
}
