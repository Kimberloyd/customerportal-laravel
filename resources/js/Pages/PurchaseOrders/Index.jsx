import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import CreateOrderModal from '@/components/CreateOrderModal';
import OrderMessageLogModal from '@/components/OrderMessageLogModal';
import { Dropdown } from '@/components/interior/dropdown';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Deferred, Head, router } from '@inertiajs/react';
import { ListChecks, MoreHorizontal, Search, SquareArrowOutUpRight, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT =
    (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

export default function Index({
    orders = { data: [], last_page: 1, current_page: 1 },
    filters,
    createOrderCustomers = [],
    createOrderProducts,
    lockedCustomerId,
    openCreateOrder = false,
    canViewMessageLog = false,
    canDeleteOrders = false,
}) {
    usePurchaseOrderRealtime();

    const [search, setSearch] = useState(filters.search);
    const [createOrderOpen, setCreateOrderOpen] = useState(openCreateOrder);
    const [productsLoading, setProductsLoading] = useState(false);
    const [productsError, setProductsError] = useState(false);
    const [messageLogOrder, setMessageLogOrder] = useState(null);
    const [orderPendingDeletion, setOrderPendingDeletion] = useState(null);
    const [isDeletingOrder, setIsDeletingOrder] = useState(false);
    const [tableLoading, setTableLoading] = useState(false);
    const latestFilterVisit = useRef(0);

    const deleteOrder = useCallback(() => {
        if (!orderPendingDeletion) return;

        router.delete(route('purchase-orders.destroy', orderPendingDeletion.id), {
            preserveScroll: true,
            onStart: () => setIsDeletingOrder(true),
            onFinish: () => {
                setIsDeletingOrder(false);
                setOrderPendingDeletion(null);
            },
        });
    }, [orderPendingDeletion]);

    const loadCreateOrderProducts = useCallback(() => {
        router.reload({
            only: ['createOrderProducts'],
            preserveScroll: true,
            onStart: () => setProductsLoading(true),
            onSuccess: (page) => setProductsError(page.props.createOrderProducts === undefined),
            onError: () => setProductsError(true),
            onFinish: () => setProductsLoading(false),
        });
    }, []);

    // Clears a stale error so reopening the modal retries automatically
    // instead of leaving the user stuck on the error state from last time.
    useEffect(() => {
        if (createOrderOpen) setProductsError(false);
    }, [createOrderOpen]);

    useEffect(() => {
        if (!createOrderOpen || createOrderProducts !== undefined || productsLoading || productsError) return;
        loadCreateOrderProducts();
    }, [createOrderOpen, createOrderProducts, productsLoading, productsError, loadCreateOrderProducts]);

    const applyFilters = (overrides = {}) => {
        const visit = ++latestFilterVisit.current;

        router.get(
            route('purchase-orders.index'),
            {
                search,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setTableLoading(true),
                onFinish: () => {
                    if (visit === latestFilterVisit.current) {
                        setTableLoading(false);
                    }
                },
            },
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

    const goToOrder = useCallback(
        (order) => router.visit(route('purchase-orders.show', order.id)),
        [],
    );

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
                    const badge = statusBadge(order.display_status ?? order.status);
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
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                cell: (order) => formatDateTime(order.submitted_at),
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
                            onSelect: () => goToOrder(order),
                        },
                        ...(canViewMessageLog
                            ? [{
                                value: 'message-log',
                                label: 'Message Log',
                                icon: <ListChecks />,
                                onSelect: () => setMessageLogOrder(order),
                            }]
                            : []),
                        ...(canDeleteOrders
                            ? [{
                                value: 'delete',
                                label: 'Delete',
                                icon: <Trash2 />,
                                onSelect: () => setOrderPendingDeletion(order),
                                destructive: true,
                            }]
                            : []),
                    ];

                    return (
                        <div className="flex items-center">
                            <Dropdown
                                items={items}
                                value=""
                                onChange={(action) => {
                                    const item = items.find((candidate) => candidate.value === action);
                                    item?.onSelect();
                                }}
                                label={`Actions for ${order.po_number}`}
                                trigger={<MoreHorizontal />}
                                align="right"
                                portal
                                triggerClassName="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [&_svg]:h-5 [&_svg]:w-5"
                            />
                        </div>
                    );
                },
            },
        ],
        [canDeleteOrders, canViewMessageLog, goToOrder],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Orders
                    </h2>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() => setCreateOrderOpen(true)}
                    >
                        Create Order
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
                        placeholder="PO number or customer"
                        aria-label="Search orders"
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{
                            root: 'w-80',
                            field: 'h-9 w-80 rounded-full border-border bg-transparent shadow-none',
                            input: 'text-sm',
                        }}
                    />
                </div>

                <Deferred
                    data="orders"
                    fallback={(
                        <Table
                            data={[]}
                            columns={columns}
                            getRowId={(order) => String(order.id)}
                            className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                            height={TABLE_VIEWPORT_HEIGHT}
                            loading
                        />
                    )}
                >
                    <>
                        <Table
                            data={orders.data}
                            columns={columns}
                            getRowId={(order) => String(order.id)}
                            defaultSort={{ key: 'submitted_at', direction: 'desc' }}
                            className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                            rowHeight={TABLE_ROW_HEIGHT}
                            height={TABLE_VIEWPORT_HEIGHT}
                            loading={tableLoading}
                            resizable
                            emptyState="No orders found. Try a different search."
                            emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
                        />

                        {orders.last_page > 1 && (
                            <div className="mt-4 flex justify-end">
                                <Pagination
                                    count={orders.last_page}
                                    page={orders.current_page}
                                    onPageChange={(page) => applyFilters({ page })}
                                    label="Orders pagination"
                                />
                            </div>
                        )}
                    </>
                </Deferred>
            </div>

            <CreateOrderModal
                open={createOrderOpen}
                onOpenChange={setCreateOrderOpen}
                customers={createOrderCustomers}
                products={createOrderProducts ?? []}
                productsLoading={productsLoading || (createOrderProducts === undefined && !productsError)}
                productsError={productsError}
                onRetryProducts={loadCreateOrderProducts}
                lockedCustomerId={lockedCustomerId}
            />

            <OrderMessageLogModal
                order={messageLogOrder}
                open={messageLogOrder !== null}
                onClose={() => setMessageLogOrder(null)}
            />

            <ConfirmationDialog
                open={orderPendingDeletion !== null}
                onOpenChange={(open) => !open && !isDeletingOrder && setOrderPendingDeletion(null)}
                title={`Delete purchase order ${orderPendingDeletion?.po_number ?? ''}?`}
                description="This permanently deletes this order, its line items, message log, and attachment. This cannot be undone."
                confirmLabel="Delete order"
                cancelLabel="Keep order"
                onConfirm={deleteOrder}
                confirmationText={orderPendingDeletion?.po_number}
                destructive
                processing={isDeletingOrder}
            />
        </AuthenticatedLayout>
    );
}
