import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Input } from '@/components/motion/input';
import { Button } from '@/components/ui/button';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw, Search, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

const TABLE_ROW_HEIGHT = 48;
const PAGE_SIZE = 10;

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
                    <AnimatedBadge status={badge.status} size="sm" pulse={false} icon={badge.icon ? <badge.icon className="h-3.5 w-3.5" /> : undefined}>
                        {badge.label}
                    </AnimatedBadge>
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
            width: '220px',
            cell: (order) => (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="tertiary"
                        size="compact"
                        leadingIcon={RotateCcw}
                        onClick={() => setOrderPendingRestore(order)}
                    >
                        Restore
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="compact"
                        leadingIcon={Trash2}
                        onClick={() => setOrderPendingDelete(order)}
                    >
                        Delete forever
                    </Button>
                </div>
            ),
        },
    ], []);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="type-page-heading text-foreground">Archived Orders</h2>
                    <Link
                        href={route('purchase-orders.index')}
                        className="text-sm font-medium text-primary hover:underline"
                    >
                        Back to orders
                    </Link>
                </div>
            }
        >
            <Head title="Archived Orders" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-muted-foreground">
                    Orders archived from the main list. Restore one back to active orders, or delete it
                    forever -- that permanently erases the order and everything tied to it (items, history,
                    notifications, returns, attachment) and can't be undone.
                </p>

                <div className="w-full sm:max-w-80">
                    <Input
                        type="text"
                        value={search}
                        onChange={(value) => { setSearch(value); applySearch(value); }}
                        placeholder="Order number or customer"
                        aria-label="Search archived orders"
                        leftIcon={<Search className="h-4 w-4" />}
                    />
                </div>

                <Table
                    data={orders.data}
                    columns={columns}
                    getRowId={(order) => String(order.id)}
                    className="border-border"
                    height={Math.max(orders.data.length, 1) * TABLE_ROW_HEIGHT + TABLE_ROW_HEIGHT}
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
