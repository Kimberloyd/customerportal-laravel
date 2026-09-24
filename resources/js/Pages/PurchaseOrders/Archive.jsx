import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { Dropdown } from '@/components/interior/dropdown';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, RotateCcw, Search, SquareArrowOutUpRight, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT = (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

export default function Archive({ orders = { data: [], last_page: 1, current_page: 1 }, filters }) {
    usePurchaseOrderRealtime(null, { only: ['orders'] });

    const [search, setSearch] = useState(filters.search ?? '');
    const [orderPendingRestore, setOrderPendingRestore] = useState(null);
    const [orderPendingDelete, setOrderPendingDelete] = useState(null);
    const [isRestoring, setIsRestoring] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const applySearch = useCallback((value) => {
        router.get(route('purchase-orders.archive'), { search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    const restoreOrder = useCallback(() => {
        if (!orderPendingRestore) return;

        router.post(route('purchase-orders.restore', orderPendingRestore.public_id), {}, {
            preserveScroll: true,
            onStart: () => setIsRestoring(true),
            onFinish: () => {
                setIsRestoring(false);
                setOrderPendingRestore(null);
            },
        });
    }, [orderPendingRestore]);

    const deleteOrderForever = useCallback(() => {
        if (!orderPendingDelete) return;

        router.delete(route('purchase-orders.force-destroy', orderPendingDelete.public_id), {
            preserveScroll: true,
            onStart: () => setIsDeleting(true),
            onFinish: () => {
                setIsDeleting(false);
                setOrderPendingDelete(null);
            },
        });
    }, [orderPendingDelete]);

    const columns = useMemo(() => [
        {
            key: 'transaction_number',
            header: 'Order Number',
            width: '160px',
            cell: (order) => <span className="font-medium text-foreground">{order.transaction_number}</span>,
        },
        { key: 'customer_name', header: 'Customer', width: '320px' },
        {
            key: 'status',
            header: 'Status',
            width: '120px',
            cell: (order) => {
                const badge = statusBadge(order.display_status ?? order.status);
                return (
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={badge.status}
                            size="sm"
                            pulse={false}
                            icon={badge.icon ? <badge.icon className="h-3.5 w-3.5" /> : undefined}
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {badge.label}
                        </AnimatedBadge>
                    </div>
                );
            },
        },
        {
            key: 'deleted_at',
            header: 'Archived',
            width: '160px',
            cell: (order) => formatDateTime(order.deleted_at),
        },
        {
            key: 'actions',
            header: '',
            width: '56px',
            cell: (order) => {
                const items = [
                    {
                        value: 'view',
                        label: 'Open',
                        icon: <SquareArrowOutUpRight />,
                        onSelect: () => router.visit(route('purchase-orders.show', order.public_id)),
                    },
                    {
                        value: 'restore',
                        label: 'Restore',
                        icon: <RotateCcw />,
                        onSelect: () => setOrderPendingRestore(order),
                    },
                    {
                        value: 'delete',
                        label: 'Delete forever',
                        icon: <Trash2 />,
                        onSelect: () => setOrderPendingDelete(order),
                        destructive: true,
                    },
                ];

                return (
                    <div className="flex items-center" onClick={(e) => e.stopPropagation()}>
                        <Dropdown
                            items={items}
                            value=""
                            onChange={(action) => {
                                const item = items.find((candidate) => candidate.value === action);
                                item?.onSelect();
                            }}
                            label={`Actions for ${order.transaction_number}`}
                            trigger={<MoreHorizontal />}
                            align="right"
                            portal
                            triggerClassName="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [&_svg]:h-5 [&_svg]:w-5"
                        />
                    </div>
                );
            },
        },
    ], []);

    return (
        <AuthenticatedLayout
            header={
                <nav aria-label="Breadcrumb">
                    <h2 className="flex items-center gap-2 text-xl font-semibold leading-tight">
                        <Link
                            href={route('purchase-orders.index')}
                            className="text-muted-foreground transition-colors hover:text-primary"
                        >
                            Orders
                        </Link>
                        <span aria-hidden="true" className="text-muted-foreground">/</span>
                        <span aria-current="page" className="text-foreground">Archived</span>
                    </h2>
                </nav>
            }
        >
            <Head title="Archived Orders" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center justify-center gap-2 bg-background">
                    <div className="relative w-full sm:max-w-80">
                        <Input
                            type="text"
                            value={search}
                            onChange={(value) => { setSearch(value); applySearch(value); }}
                            placeholder="Order number or customer"
                            aria-label="Search archived orders"
                            leftIcon={<Search className="h-4 w-4" />}
                            classNames={{
                                root: 'w-full',
                                field: 'h-9 w-full rounded-full border-border bg-transparent shadow-none',
                                input: 'text-sm',
                            }}
                        />
                    </div>
                </div>

                <Table
                    data={orders.data}
                    columns={columns}
                    getRowId={(order) => String(order.id)}
                    className="border-border"
                    height={TABLE_VIEWPORT_HEIGHT}
                    onRowClick={(order) => router.visit(route('purchase-orders.show', order.public_id))}
                    resizable
                    emptyState="No archived orders."
                    emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
                />

                {orders.last_page > 1 && (
                    <div className="flex justify-end">
                        <Pagination
                            count={orders.last_page}
                            page={orders.current_page}
                            onPageChange={(page) => router.get(route('purchase-orders.archive'), { search, page }, { preserveScroll: true })}
                            label="Archived orders pagination"
                        />
                    </div>
                )}
            </div>

            <ConfirmationDialog
                open={orderPendingRestore !== null}
                onOpenChange={(open) => !open && !isRestoring && setOrderPendingRestore(null)}
                title={`Restore order ${orderPendingRestore?.transaction_number ?? ''}?`}
                description="This puts the order back in the active Orders list."
                confirmLabel="Restore"
                cancelLabel="Cancel"
                onConfirm={restoreOrder}
                processing={isRestoring}
            />

            <ConfirmationDialog
                open={orderPendingDelete !== null}
                onOpenChange={(open) => !open && !isDeleting && setOrderPendingDelete(null)}
                title={`Permanently delete order ${orderPendingDelete?.transaction_number ?? ''}?`}
                description="This can't be undone. The order, its items, activity history, notifications, returns, and attachment are all erased for good."
                confirmLabel="Delete forever"
                cancelLabel="Keep archived"
                onConfirm={deleteOrderForever}
                confirmationText={orderPendingDelete?.transaction_number}
                destructive
                processing={isDeleting}
            />
        </AuthenticatedLayout>
    );
}
