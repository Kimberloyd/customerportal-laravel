import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function Index({ orders, filters }) {
    usePurchaseOrderRealtime();

    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        void import('./Create.jsx');
    }, []);

    const applyFilters = (overrides = {}) => {
        router.get(
            route('purchase-orders.index'),
            {
                search,
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
                key: 'po_number',
                header: 'PO Number',
                sortable: true,
                cell: (order) => (
                    <span className="font-medium text-gray-900">{order.po_number}</span>
                ),
            },
            { key: 'customer_name', header: 'Customer', sortable: true },
            {
                key: 'status',
                header: 'Status',
                cell: (order) => {
                    const badge = statusBadge(order.status);
                    return (
                        <div className="flex justify-start">
                            <AnimatedBadge status={badge.status} pulse={badge.pulse} size="sm">
                                {badge.label}
                            </AnimatedBadge>
                        </div>
                    );
                },
            },
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                cell: (order) => formatDateTime(order.submitted_at),
            },
        ],
        [],
    );

    const goToOrder = (order) => router.visit(route('purchase-orders.show', order.id));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Orders
                    </h2>
                    <Button asChild variant="primary">
                        <Link
                            href={route('purchase-orders.create')}
                            prefetch="mount"
                            cacheFor="30s"
                        >
                            Create Order
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title="Orders" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex justify-center bg-white">
                    <Input
                        type="text"
                        value={search}
                        onChange={setSearch}
                        placeholder="Search by PO Number, Customer"
                        aria-label="Search orders"
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{
                            root: 'w-80',
                            field: 'h-9 w-80 rounded-full border-border bg-transparent shadow-none',
                            input: 'text-sm',
                        }}
                    />
                </div>

                <>
                    <Table
                        data={orders.data}
                        columns={columns}
                        getRowId={(order) => String(order.id)}
                        defaultSort={{ key: 'submitted_at', direction: 'desc' }}
                        className="[&>div]:overflow-hidden [&_td:not(:nth-last-child(-n+2))]:border-r [&_td:not(:nth-last-child(-n+2))]:border-border/60 [&_th:not(:nth-last-child(-n+2))]:border-r [&_th:not(:nth-last-child(-n+2))]:border-border/60"
                        resizable
                        reorderable
                        onRowClick={goToOrder}
                        emptyState="No orders found. Try a different search."
                    />

                    <div className="mt-4 flex justify-end">
                        <Pagination
                            count={orders.last_page}
                            page={orders.current_page}
                            onPageChange={(page) => applyFilters({ page })}
                            label="Orders pagination"
                        />
                    </div>
                </>
            </div>
        </AuthenticatedLayout>
    );
}
