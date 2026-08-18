import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Dropdown } from '@/Components/interior/dropdown';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const DATE_FILTER_OPTIONS = [
    { value: 'all', label: 'All Time', hint: 'default' },
    { value: 'month', label: 'By Month' },
    { value: 'custom', label: 'Custom Range' },
];

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses', hint: 'default' },
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

    const applyFilters = (overrides = {}) => {
        router.get(
            route('purchase-orders.index'),
            {
                search,
                date_filter: dateFilter,
                month,
                start_date: startDate,
                end_date: endDate,
                status,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timeout = setTimeout(() => applyFilters({ search }), 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = useMemo(
        () => [
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                cell: (order) => formatDateTime(order.submitted_at),
            },
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
            { key: 'customer_name', header: 'Customer', sortable: true },
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
            {
                key: 'actions',
                header: 'Actions',
                cell: (order) => (
                    <Button asChild variant="ghost" size="compact">
                        <Link href={route('purchase-orders.show', order.id)}>View</Link>
                    </Button>
                ),
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
                <div className="flex flex-wrap items-end gap-3 bg-white">
                    <Input
                        label="Search"
                        type="text"
                        value={search}
                        onChange={setSearch}
                        placeholder="Search by PO Number, Customer"
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{
                            field: 'h-9 w-80 rounded-[9px] border-border ring-0 shadow-none',
                        }}
                    />
                    <div className="flex flex-col text-sm text-gray-600">
                        <span>Date filter</span>
                        <Dropdown
                            items={DATE_FILTER_OPTIONS}
                            value={dateFilter}
                            onChange={(value) => {
                                setDateFilter(value);
                                applyFilters({ date_filter: value });
                            }}
                            label={
                                DATE_FILTER_OPTIONS.find((option) => option.value === dateFilter)
                                    ?.label ?? 'Select date filter'
                            }
                            className="mt-1"
                        />
                    </div>
                    {dateFilter === 'month' && (
                        <Input
                            label="Month"
                            type="month"
                            value={month}
                            onChange={(value) => {
                                setMonth(value);
                                applyFilters({ month: value });
                            }}
                        />
                    )}
                    {dateFilter === 'custom' && (
                        <>
                            <Input
                                label="From"
                                type="date"
                                value={startDate}
                                onChange={(value) => {
                                    setStartDate(value);
                                    applyFilters({ start_date: value });
                                }}
                            />
                            <Input
                                label="To"
                                type="date"
                                value={endDate}
                                onChange={(value) => {
                                    setEndDate(value);
                                    applyFilters({ end_date: value });
                                }}
                            />
                        </>
                    )}
                    <div className="flex flex-col text-sm text-gray-600">
                        <span>Status</span>
                        <Dropdown
                            items={STATUS_OPTIONS}
                            value={status}
                            onChange={(value) => {
                                setStatus(value);
                                applyFilters({ status: value });
                            }}
                            label={
                                STATUS_OPTIONS.find((option) => option.value === status)?.label ??
                                'Select status'
                            }
                            className="mt-1"
                        />
                    </div>
                </div>

                <>
                    <Table
                        data={orders.data}
                        columns={columns}
                        getRowId={(order) => String(order.id)}
                        defaultSort={{ key: 'submitted_at', direction: 'desc' }}
                        className="rounded-[9px] [&_td:not(:last-child)]:border-r [&_td:not(:last-child)]:border-border/60 [&_th:not(:last-child)]:border-r [&_th:not(:last-child)]:border-border/60"
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
