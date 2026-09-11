import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import CreateOrderModal from '@/components/CreateOrderModal';
import OrderMessageLogModal from '@/components/OrderMessageLogModal';
import { Dropdown } from '@/components/interior/dropdown';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { statusBadge } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Deferred, Head, router } from '@inertiajs/react';
import { parseDate } from '@internationalized/date';
import { useContainerBreakpoint } from '@/lib/hooks/use-container-breakpoint';
import { Archive, Funnel, ListChecks, MoreHorizontal, Search, SquareArrowOutUpRight } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const STATUS_FILTER_OPTIONS = [
    { value: 'all', label: 'All orders' },
    { value: 'active', label: 'Active' },
    { value: 'pending', label: 'Pending' },
    { value: 'partial', label: 'Partially delivered' },
    { value: 'processed', label: 'Processed' },
    { value: 'completed', label: 'Completed' },
    { value: 'returned', label: 'Needs redelivery' },
    { value: 'cancelled', label: 'Cancelled' },
];

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

    // The table owns its own breakpoint (a container query on its own
    // wrapper) rather than reacting to the page's viewport -- so it still
    // drops to PO Number, Date, and actions correctly if this page is ever
    // embedded somewhere narrower than the full viewport.
    const containerRef = useRef(null);
    const isCompactViewport = useContainerBreakpoint(containerRef, 639);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [customerId, setCustomerId] = useState(filters.customer_id ? String(filters.customer_id) : '');
    const [startDate, setStartDate] = useState(filters.start_date ?? '');
    const [endDate, setEndDate] = useState(filters.end_date ?? '');
    const [filterModalOpen, setFilterModalOpen] = useState(false);
    const hasActiveFilters = status !== 'all' || !!customerId || !!startDate || !!endDate;
    const showCustomerFilter = !lockedCustomerId && createOrderCustomers.length > 1;

    const customerFilterItems = useMemo(
        () => [
            { value: '', label: 'All customers' },
            ...createOrderCustomers.map((customer) => ({
                value: String(customer.id),
                label: customer.company_name,
            })),
        ],
        [createOrderCustomers],
    );
    const selectedCustomerFilter = customerFilterItems.find((item) => item.value === customerId);
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

        router.delete(route('purchase-orders.destroy', orderPendingDeletion.public_id), {
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
                date_filter: filters.date_filter,
                month: filters.month,
                start_date: filters.start_date ?? '',
                end_date: filters.end_date ?? '',
                status: filters.status ?? 'all',
                customer_id: filters.customer_id ?? '',
                ...overrides,
            },
            {
                only: ['orders', 'filters'],
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
        (order) => router.visit(route('purchase-orders.show', order.public_id)),
        [],
    );

    const columns = useMemo(
        () => [
            // Priority 1 -- identity, always shown: which order is this.
            {
                key: 'po_number',
                header: 'PO Number',
                sortable: true,
                cell: (order) => (
                    <span className="font-medium text-gray-900">{order.po_number}</span>
                ),
            },
            // Priority 2 -- state, dropped under the table's own container
            // breakpoint so the row fits without horizontal scroll; still
            // reachable there via the "..." menu below (see the informational
            // items prepended to `items` in the actions column).
            ...(isCompactViewport ? [] : [
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
                                    icon={badge.icon ? <badge.icon className="h-3.5 w-3.5" /> : undefined}
                                    className="border-0 bg-transparent px-0 shadow-none"
                                >
                                    {badge.label}
                                </AnimatedBadge>
                            </div>
                        );
                    },
                },
            ]),
            // Priority 1 -- identity, always shown: when this order was placed.
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                cell: (order) => order.submitted_at
                    ? new Date(order.submitted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                    : '—',
            },
            {
                key: 'actions',
                header: '',
                width: '56px',
                cell: (order) => {
                    // Customer and status were dropped from their own columns
                    // above at this width -- surface them here (disabled,
                    // display-only rows) so they're still reachable in place,
                    // instead of forcing a full navigation to the order page
                    // just to see them.
                    const items = [
                        ...(isCompactViewport ? [
                            { value: 'customer', label: 'Customer', hint: order.customer_name || '—', disabled: true },
                            { value: 'status', label: 'Status', hint: statusBadge(order.display_status ?? order.status).label, disabled: true },
                        ] : []),
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
                                label: 'Archive',
                                icon: <Archive />,
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
                                menuTitle={isCompactViewport ? order.po_number : undefined}
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
        [canDeleteOrders, canViewMessageLog, goToOrder, isCompactViewport],
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

            <div ref={containerRef} className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center justify-center gap-2 bg-white">
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
                    <button
                        type="button"
                        onClick={() => {
                            setStatus(filters.status ?? 'all');
                            setCustomerId(filters.customer_id ? String(filters.customer_id) : '');
                            setStartDate(filters.start_date ?? '');
                            setEndDate(filters.end_date ?? '');
                            setFilterModalOpen(true);
                        }}
                        aria-label="Advanced filters"
                        className="relative inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-white text-[#868593] shadow-none outline-none transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <Funnel aria-hidden="true" className="h-[18px] w-[18px]" />
                    </button>
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

            {(() => {
                const filterFooter = (
                    <>
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-5 text-sm"
                            disabled={!hasActiveFilters}
                            onClick={() => {
                                setStatus('all');
                                setCustomerId('');
                                setStartDate('');
                                setEndDate('');
                            }}
                        >
                            Reset all filters
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            className="h-10 rounded-md px-5 text-sm"
                            onClick={() => {
                                applyFilters({
                                    status,
                                    customer_id: customerId,
                                    date_filter: startDate && endDate ? 'custom' : 'all',
                                    start_date: startDate,
                                    end_date: endDate,
                                    page: 1,
                                });
                                setFilterModalOpen(false);
                            }}
                        >
                            Apply filters
                        </Button>
                    </>
                );

                const filterBody = (
                <div className="space-y-6 py-2">
                    {showCustomerFilter && (
                        <Dropdown
                            items={customerFilterItems}
                            value={customerId}
                            onChange={setCustomerId}
                            label="Search customer"
                            placeholder="Search customer"
                            emptyLabel="No customers found"
                            className="block w-full"
                            triggerClassName="flex h-11 w-full items-center gap-2 rounded-lg border border-border bg-white px-4 text-left text-sm text-foreground outline-none transition-colors hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20"
                            trigger={(
                                <>
                                    <Search aria-hidden="true" className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span className="truncate text-foreground">
                                        {customerId ? selectedCustomerFilter?.label : 'Search customer'}
                                    </span>
                                </>
                            )}
                            matchTriggerWidth
                            portal
                        />
                    )}

                    <div className="space-y-6">
                        <section>
                            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                Status
                            </h3>
                            <div className="flex flex-wrap gap-2.5">
                                {STATUS_FILTER_OPTIONS.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setStatus(option.value)}
                                        className={`inline-flex items-center rounded-full border px-4 py-2 text-sm transition-colors ${
                                            status === option.value
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border text-foreground hover:bg-muted'
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        </section>

                        <section>
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                    Date range
                                </h3>
                                {(startDate || endDate) && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setStartDate('');
                                            setEndDate('');
                                        }}
                                        className="text-sm font-medium text-primary hover:underline"
                                    >
                                        Clear
                                    </button>
                                )}
                            </div>
                            <div className="w-full rounded-xl bg-white">
                                <RangeCalendar
                                    aria-label="Order date range"
                                    value={
                                        startDate && endDate
                                            ? { start: parseDate(startDate), end: parseDate(endDate) }
                                            : null
                                    }
                                    onChange={(range) => {
                                        const start = range.start.toString();
                                        const end = range.end.toString();
                                        setStartDate(start);
                                        setEndDate(end);
                                    }}
                                />
                            </div>
                        </section>
                    </div>
                </div>
                );

                return (
                    <Modal
                        open={filterModalOpen}
                        onClose={() => setFilterModalOpen(false)}
                        title="Advanced filters"
                        maxWidth={800}
                        maxHeight="min(85vh, 720px)"
                        footer={filterFooter}
                    >
                        <AutoHeightReveal>
                        {filterBody}
                        </AutoHeightReveal>
                    </Modal>
                );
            })()}

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
                title={`Archive purchase order ${orderPendingDeletion?.po_number ?? ''}?`}
                description="This removes the order from active views while retaining its items, activity history, messages, returns, and attachment for audit purposes."
                confirmLabel="Archive order"
                cancelLabel="Keep order"
                onConfirm={deleteOrder}
                confirmationText={orderPendingDeletion?.po_number}
                destructive
                processing={isDeletingOrder}
            />
        </AuthenticatedLayout>
    );
}
