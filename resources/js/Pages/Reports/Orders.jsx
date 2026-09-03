import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
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
    const [tableLoading, setTableLoading] = useState(false);

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(
            route('reports.orders'),
            { date_filter: dateFilter, month, start_date: startDate, end_date: endDate, customer_id: customerId, status },
            {
                preserveState: true,
                onStart: () => setTableLoading(true),
                onFinish: () => setTableLoading(false),
            },
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
                    <Link href={`/purchase-orders/${order.id}`} className="font-medium text-primary">
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
                        <div className="flex justify-start">
                            <AnimatedBadge
                                status={badge.status}
                                size="sm"
                                pulse={false}
                                className="border-0 bg-transparent px-0 shadow-none"
                            >
                                {badge.label}
                            </AnimatedBadge>
                        </div>
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
                <div className="no-print flex flex-wrap items-center justify-between gap-3">
                    <h2 className="type-page-heading text-foreground">Orders Report</h2>
                    <div className="flex gap-2">
                        <Button variant="tertiary" size="compact" onClick={() => window.print()}>
                            Print
                        </Button>
                        <Button asChild variant="primary" size="compact">
                            <a href={exportUrl}>Export Spreadsheet</a>
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title="Orders Report" />
            <style>{'@media print { .no-print { display: none !important; } }'}</style>

            <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-8 sm:px-6 lg:grid-cols-12 lg:px-8">
                <nav className="no-print space-y-4 lg:col-span-2">
                    <Link
                        href={route('reports.overview')}
                        className={`block text-sm ${
                            route().current('reports.overview')
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Overview
                    </Link>
                    <Link
                        href={route('reports.orders')}
                        className={`block text-sm ${
                            route().current('reports.orders')
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Reports
                    </Link>
                </nav>

                <div className="space-y-6 lg:col-span-10">
                <form onSubmit={applyFilters} className="no-print flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <label className="flex flex-col text-sm text-muted-foreground">
                        Date filter
                        <select value={dateFilter} onChange={(e) => setDateFilter(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                            <option value="all">All Dates</option>
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
                    {customers.length > 1 && (
                        <label className="flex flex-col text-sm text-muted-foreground">
                            Customer
                            <select value={customerId} onChange={(e) => setCustomerId(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                                <option value="">All Customers</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.company_name}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="flex flex-col text-sm text-muted-foreground">
                        Status
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </label>
                    <Button type="submit" variant="secondary" size="compact">
                        Apply
                    </Button>
                </form>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <p className="type-label text-muted-foreground">Total Orders</p>
                        <p className="mt-1 text-2xl font-semibold text-foreground">{summary.orders}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <p className="type-label text-muted-foreground">Ordered Units</p>
                        <p className="mt-1 text-2xl font-semibold text-foreground">{summary.ordered_units}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <p className="type-label text-muted-foreground">Delivered Units</p>
                        <p className="mt-1 text-2xl font-semibold text-foreground">{summary.delivered_units}</p>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <p className="type-label text-muted-foreground">Balance Units</p>
                        <p className="mt-1 text-2xl font-semibold text-foreground">{summary.balance_units}</p>
                    </div>
                </div>

                <>
                    <Table
                        data={orders.data}
                        columns={columns}
                        getRowId={(order) => String(order.id)}
                        loading={tableLoading}
                        resizable
                        emptyState="No orders found. Try a different date range or filter."
                    />

                    {orders.last_page > 1 && (
                        <nav className="no-print mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {orders.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    onStart={() => setTableLoading(true)}
                                    onFinish={() => setTableLoading(false)}
                                    className={`rounded px-3 py-1 ${
                                        link.active
                                            ? 'bg-gray-800 text-white'
                                            : link.url
                                              ? 'text-muted-foreground hover:bg-muted'
                                              : 'cursor-not-allowed text-muted-foreground/50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    )}
                </>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
