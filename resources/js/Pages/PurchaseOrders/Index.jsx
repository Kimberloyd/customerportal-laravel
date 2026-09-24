import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import CreateOrderModal from '@/components/CreateOrderModal';
import OrderMessageLogModal from '@/components/OrderMessageLogModal';
import { Dropdown } from '@/components/interior/dropdown';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { Pagination } from '@/components/interior/pagination';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { BottomSheet } from '@/components/motion/bottom-sheet';
import { SuggestionMenu, suggestFieldKeyDown, useSuggestField } from '@/components/SuggestField';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { statusBadge } from '@/utils/orderDisplay';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { useSavedOrderFilters } from '@/hooks/useSavedOrderFilters';
import { useSearchSelections } from '@/hooks/useSearchSelections';
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
// Compact rows stack the status onto a second line (see the
// responsive-data-tables "2-line stack" pattern below), so they need more
// vertical room than the single-line desktop row.
const COMPACT_ROW_HEIGHT = 60;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT = (rowHeight) => (PAGE_SIZE + 1) * rowHeight + HORIZONTAL_SCROLLBAR_HEIGHT;
const STATUS_TITLE_CLASS = {
    neutral: 'text-muted-foreground',
    info: 'text-info',
    success: 'text-success',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-destructive',
    loading: 'text-primary',
};

export default function Index({
    orders = { data: [], last_page: 1, current_page: 1 },
    filters,
    createOrderCustomers = [],
    createOrderProducts,
    lockedCustomerId,
    openCreateOrder = false,
    canViewMessageLog = false,
    canDeleteOrders = false,
    canViewArchive = false,
}) {
    usePurchaseOrderRealtime();

    // The table owns its own breakpoint (a container query on its own
    // wrapper) rather than reacting to the page's viewport -- so it still
    // drops to PO Number, Date, and actions correctly if this page is ever
    // embedded somewhere narrower than the full viewport.
    const containerRef = useRef(null);
    const isCompactViewport = useContainerBreakpoint(containerRef, 639);
    // Matches the sm breakpoint, same as CreateOrderModal's own modal/sheet
    // switch -- scoped to document.body (not a page container) since there's
    // no pre-existing wrapper to observe before the modal/sheet choice itself
    // is made.
    const bodyRef = useRef(typeof document !== 'undefined' ? document.body : null);
    const isMobileViewport = useContainerBreakpoint(bodyRef, 639);
    const [search, setSearch] = useState(filters.search);
    const [searchFocused, setSearchFocused] = useState(false);
    const orderSearchSelections = useSearchSelections('order_search');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [customerId, setCustomerId] = useState(filters.customer_id ? String(filters.customer_id) : '');
    const [startDate, setStartDate] = useState(filters.start_date ?? '');
    const [endDate, setEndDate] = useState(filters.end_date ?? '');
    const [filterModalOpen, setFilterModalOpen] = useState(false);
    const hasActiveFilters = status !== 'all' || !!customerId || !!startDate || !!endDate;
    const showCustomerFilter = !lockedCustomerId && createOrderCustomers.length > 1;
    const clearAllFilters = () => {
        setSearch('');
        setStatus('all');
        setCustomerId('');
        customerFilterField.setQuery('');
        setStartDate('');
        setEndDate('');
    };

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

    // The field itself is the search input (no separate trigger-then-search-
    // box combo) -- matches the customer/product pickers in CreateOrderModal
    // instead of the click-to-open-then-search Dropdown pattern, which read
    // as two stacked search boxes here.
    const customerFilterField = useSuggestField(true);
    const customerFilterMatches = useMemo(() => {
        const query = customerFilterField.query.trim().toLowerCase();
        const matches = createOrderCustomers
            .filter((customer) => !query || customer.company_name.toLowerCase().includes(query))
            .slice(0, 8)
            .map((customer) => ({ id: String(customer.id), label: customer.company_name }));

        return !query || 'all customers'.includes(query)
            ? [{ id: '', label: 'All customers' }, ...matches]
            : matches;
    }, [createOrderCustomers, customerFilterField.query]);
    const selectCustomerFilter = (item) => {
        setCustomerId(item.id);
        customerFilterField.setQuery(item.id === '' ? '' : item.label);
        customerFilterField.setOpen(false);
    };
    const [createOrderOpen, setCreateOrderOpen] = useState(openCreateOrder);
    const [productsLoading, setProductsLoading] = useState(false);
    const [productsError, setProductsError] = useState(false);
    const [messageLogOrder, setMessageLogOrder] = useState(null);
    const [orderPendingDeletion, setOrderPendingDeletion] = useState(null);
    const [isDeletingOrder, setIsDeletingOrder] = useState(false);
    const [tableLoading, setTableLoading] = useState(false);
    const latestFilterVisit = useRef(0);

    const [selectedOrderIds, setSelectedOrderIds] = useState([]);
    const [bulkArchiveOpen, setBulkArchiveOpen] = useState(false);
    const [isBulkArchiving, setIsBulkArchiving] = useState(false);
    const savedOrderFilters = useSavedOrderFilters();
    const [saveFilterOpen, setSaveFilterOpen] = useState(false);
    const [saveFilterName, setSaveFilterName] = useState('');
    const [isSavingFilter, setIsSavingFilter] = useState(false);

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

    const bulkArchiveOrders = useCallback(() => {
        const publicIds = orders.data
            .filter((order) => selectedOrderIds.includes(String(order.id)))
            .map((order) => order.public_id);

        if (publicIds.length === 0) return;

        router.post(route('purchase-orders.bulk-destroy'), { order_ids: publicIds }, {
            preserveScroll: true,
            onStart: () => setIsBulkArchiving(true),
            onFinish: () => {
                setIsBulkArchiving(false);
                setBulkArchiveOpen(false);
                setSelectedOrderIds([]);
            },
        });
    }, [orders.data, selectedOrderIds]);

    const applySavedFilter = useCallback((preset) => {
        const values = preset.filters ?? {};
        setStatus(values.status ?? 'all');
        setCustomerId(values.customer_id ? String(values.customer_id) : '');
        setStartDate(values.start_date ?? '');
        setEndDate(values.end_date ?? '');
        setFilterModalOpen(false);
        applyFilters({
            status: values.status ?? 'all',
            customer_id: values.customer_id ?? '',
            date_filter: values.date_filter ?? 'all',
            start_date: values.start_date ?? '',
            end_date: values.end_date ?? '',
            page: 1,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const saveCurrentFilter = useCallback(() => {
        const name = saveFilterName.trim();
        if (!name || isSavingFilter) return;

        setIsSavingFilter(true);
        savedOrderFilters
            .save(name, {
                status,
                customer_id: customerId || null,
                date_filter: startDate && endDate ? 'custom' : 'all',
                start_date: startDate || null,
                end_date: endDate || null,
            })
            .then(() => {
                setSaveFilterOpen(false);
                setSaveFilterName('');
            })
            .finally(() => setIsSavingFilter(false));
    }, [saveFilterName, isSavingFilter, savedOrderFilters, status, customerId, startDate, endDate]);

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
        const timeout = setTimeout(() => {
            applyFilters({ search });
            if (search.trim()) orderSearchSelections.record(null, search.trim());
        }, 400);
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
            // Under the table's own container breakpoint, only the
            // transaction number remains in the row; status is available in
            // the actions menu. Always transaction_number, not po_number --
            // po_number is a staff-only reference that's routinely unset
            // (see the order page's own inline field), so it can't double
            // as this column's identity the way it used to.
            {
                key: 'transaction_number',
                header: 'Order Number',
                sortable: true,
                // An explicit pixel width on every column (see the others
                // below) lets the table resolve a fixed total width on the
                // very first render and switch to table-layout: fixed
                // immediately -- without it, the table stays in auto-layout
                // with a sticky header until a column is manually resized,
                // a combination some mobile browser engines (seen on a
                // Huawei tablet) render with misaligned/overlapping columns.
                width: '160px',
                cell: (order) => (
                    isCompactViewport ? (
                        <div className="min-w-0 py-1">
                            <span className="block truncate font-medium text-foreground">{order.transaction_number}</span>
                        </div>
                    ) : (
                        <span className="font-medium text-foreground">{order.transaction_number}</span>
                    )
                ),
            },
            ...(isCompactViewport ? [] : [
                { key: 'customer_name', header: 'Customer', sortable: true, width: '320px' },
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
            ]),
            // Priority 1 -- identity, always shown: when this order was placed.
            {
                key: 'submitted_at',
                header: 'Date',
                sortable: true,
                width: '130px',
                cell: (order) => order.submitted_at
                    ? new Date(order.submitted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                    : '—',
            },
            {
                key: 'actions',
                header: '',
                width: '56px',
                cell: (order) => {
                    const badge = statusBadge(order.display_status ?? order.status);
                    const StatusIcon = badge.icon;
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
                                label: 'Archive',
                                icon: <Archive />,
                                onSelect: () => setOrderPendingDeletion(order),
                                destructive: true,
                            }]
                            : []),
                    ];

                    return (
                        // Stops the click from also bubbling to the row's own
                        // onClick (which navigates to the order) -- opening
                        // this menu shouldn't also navigate away from under it.
                        <div className="flex items-center" onClick={(e) => e.stopPropagation()}>
                            <Dropdown
                                items={items}
                                value=""
                                onChange={(action) => {
                                    const item = items.find((candidate) => candidate.value === action);
                                    item?.onSelect();
                                }}
                                label={`Actions for ${order.transaction_number}`}
                                menuTitle={isCompactViewport ? (
                                    <div className={`flex items-center gap-2 font-normal ${STATUS_TITLE_CLASS[badge.status] ?? STATUS_TITLE_CLASS.neutral}`}>
                                        {StatusIcon ? <StatusIcon aria-hidden="true" className="h-4 w-4" /> : null}
                                        <span>{badge.label}</span>
                                    </div>
                                ) : undefined}
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
                    <h2 className="type-page-heading text-foreground">
                        Orders
                    </h2>
                    <div className="flex items-center gap-2">
                        {canViewArchive && (
                            <Button
                                type="button"
                                variant="tertiary"
                                leadingIcon={Archive}
                                onClick={() => router.visit(route('purchase-orders.archive'))}
                            >
                                Archive
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="primary"
                            onClick={() => setCreateOrderOpen(true)}
                        >
                            Create Order
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title="Orders" />

            <div ref={containerRef} className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center justify-center gap-2 bg-background">
                    <div className="relative w-full sm:max-w-80">
                        <Input
                            type="text"
                            value={search}
                            onChange={setSearch}
                            onFocus={() => setSearchFocused(true)}
                            onBlur={() => setSearchFocused(false)}
                            placeholder="Order number or customer"
                            aria-label="Search orders"
                            leftIcon={<Search className="h-4 w-4" />}
                            classNames={{
                                root: 'w-full',
                                field: 'h-9 w-full rounded-full border-border bg-transparent shadow-none',
                                input: 'text-sm',
                            }}
                        />
                        {searchFocused && !search.trim() && orderSearchSelections.recent.length > 0 && (
                            <div className="absolute left-0 top-full z-20 mt-1.5 w-full rounded-xl border border-border bg-card p-2 shadow-[0_1px_2px_rgba(28,25,23,0.06),0_16px_36px_-18px_rgba(28,25,23,0.5)] dark:shadow-[0_2px_12px_rgba(0,0,0,0.6)]">
                                <p className="px-1 pb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Recent</p>
                                <div className="flex flex-wrap gap-1.5">
                                    {orderSearchSelections.recent.map((entry) => (
                                        <button
                                            key={entry.label}
                                            type="button"
                                            onMouseDown={(event) => event.preventDefault()}
                                            onClick={() => {
                                                setSearch(entry.label);
                                                applyFilters({ search: entry.label });
                                            }}
                                            className="rounded-full border border-border px-3 py-1 text-xs text-foreground outline-none transition-colors hover:bg-hover focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            {entry.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            setStatus(filters.status ?? 'all');
                            setCustomerId(filters.customer_id ? String(filters.customer_id) : '');
                            customerFilterField.setQuery(
                                filters.customer_id
                                    ? customerFilterItems.find((item) => item.value === String(filters.customer_id))?.label ?? ''
                                    : '',
                            );
                            setStartDate(filters.start_date ?? '');
                            setEndDate(filters.end_date ?? '');
                            setFilterModalOpen(true);
                        }}
                        aria-label="Advanced filters"
                        className="relative inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-none outline-none transition-colors hover:bg-hover focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <Funnel aria-hidden="true" className="h-[18px] w-[18px]" />
                    </button>
                </div>

                {savedOrderFilters.filters.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Saved</span>
                        {savedOrderFilters.filters.map((preset) => (
                            <span
                                key={preset.id}
                                className="inline-flex items-center gap-1 rounded-full border border-border py-1 pl-3 pr-1.5 text-xs text-foreground"
                            >
                                <button
                                    type="button"
                                    onClick={() => applySavedFilter(preset)}
                                    className="outline-none hover:underline focus-visible:underline"
                                >
                                    {preset.name}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => savedOrderFilters.remove(preset.id)}
                                    aria-label={`Delete saved filter ${preset.name}`}
                                    className="grid h-4 w-4 place-items-center rounded-full text-muted-foreground outline-none hover:bg-hover hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    &times;
                                </button>
                            </span>
                        ))}
                    </div>
                )}

                <Deferred
                    data="orders"
                    fallback={(
                        <Table
                            data={[]}
                            columns={columns}
                            getRowId={(order) => String(order.id)}
                            className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                            rowHeight={isCompactViewport ? COMPACT_ROW_HEIGHT : TABLE_ROW_HEIGHT}
                            height={TABLE_VIEWPORT_HEIGHT(isCompactViewport ? COMPACT_ROW_HEIGHT : TABLE_ROW_HEIGHT)}
                            loading
                        />
                    )}
                >
                    <>
                        {canDeleteOrders && selectedOrderIds.length > 0 && (
                            <div className="flex items-center justify-between rounded-lg border border-border bg-card px-4 py-2.5 text-sm">
                                <span className="text-muted-foreground">{selectedOrderIds.length} selected</span>
                                <button
                                    type="button"
                                    onClick={() => setBulkArchiveOpen(true)}
                                    className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-destructive outline-none hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)]"
                                >
                                    <Archive className="h-4 w-4" aria-hidden="true" />
                                    Archive selected
                                </button>
                            </div>
                        )}
                        <Table
                            data={orders.data}
                            columns={columns}
                            getRowId={(order) => String(order.id)}
                            defaultSort={{ key: 'submitted_at', direction: 'desc' }}
                            className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                            rowHeight={isCompactViewport ? COMPACT_ROW_HEIGHT : TABLE_ROW_HEIGHT}
                            height={TABLE_VIEWPORT_HEIGHT(isCompactViewport ? COMPACT_ROW_HEIGHT : TABLE_ROW_HEIGHT)}
                            loading={tableLoading}
                            resizable
                            selectable={canDeleteOrders}
                            selectedRowIds={selectedOrderIds}
                            onSelectionChange={setSelectedOrderIds}
                            onRowClick={goToOrder}
                            emptyState={(
                                <div className="flex flex-col items-center gap-2">
                                    <span>No orders found.</span>
                                    {(hasActiveFilters || search) && (
                                        <button
                                            type="button"
                                            onClick={clearAllFilters}
                                            className="font-medium text-primary outline-none hover:underline focus-visible:underline"
                                        >
                                            Clear all filters
                                        </button>
                                    )}
                                </div>
                            )}
                            emptyStateHeight={PAGE_SIZE * (isCompactViewport ? COMPACT_ROW_HEIGHT : TABLE_ROW_HEIGHT)}
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
                const filterFooter = saveFilterOpen ? (
                    <div className="flex w-full items-center gap-2">
                        <Input
                            autoFocus
                            value={saveFilterName}
                            onChange={setSaveFilterName}
                            placeholder="Name this filter"
                            aria-label="Filter name"
                            classNames={{ root: 'flex-1', field: 'h-10 rounded-md', input: 'text-sm' }}
                        />
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-4 text-sm"
                            onClick={() => { setSaveFilterOpen(false); setSaveFilterName(''); }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            className="h-10 rounded-md px-4 text-sm"
                            disabled={!saveFilterName.trim() || isSavingFilter}
                            onClick={saveCurrentFilter}
                        >
                            {isSavingFilter ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                ) : (
                    <>
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-5 text-sm"
                            disabled={!hasActiveFilters}
                            onClick={() => setSaveFilterOpen(true)}
                        >
                            Save as…
                        </Button>
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-5 text-sm"
                            disabled={!hasActiveFilters}
                            onClick={() => {
                                clearAllFilters();
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
                        <div ref={customerFilterField.fieldRef} className="relative w-full">
                            <Input
                                value={customerFilterField.query}
                                onChange={(value) => {
                                    customerFilterField.setQuery(value);
                                    customerFilterField.setActiveIndex(0);
                                    customerFilterField.setOpen(true);
                                    if (value === '') setCustomerId('');
                                }}
                                onFocus={() => customerFilterField.setOpen(true)}
                                onKeyDown={suggestFieldKeyDown(customerFilterField, customerFilterMatches, selectCustomerFilter)}
                                type="text"
                                aria-label="Search customer"
                                placeholder="Search customer"
                                leftIcon={<Search className="h-4 w-4" />}
                                classNames={{ field: 'h-11 rounded-lg', input: 'text-sm' }}
                            />
                            {customerFilterField.visible && customerFilterField.position && (
                                <SuggestionMenu
                                    menuRef={customerFilterField.menuRef}
                                    position={customerFilterField.position}
                                    items={customerFilterMatches}
                                    activeIndex={customerFilterField.activeIndex}
                                    onHover={customerFilterField.setActiveIndex}
                                    radius="lg"
                                    onSelect={selectCustomerFilter}
                                    emptyMessage="No customers found."
                                    onClear={() => customerFilterField.setQuery('')}
                                />
                            )}
                        </div>
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
                                        aria-pressed={status === option.value}
                                        onClick={() => setStatus(option.value)}
                                        className={`inline-flex items-center rounded-full border px-4 py-2 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)] ${
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
                                        className="rounded text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)]"
                                    >
                                        Clear
                                    </button>
                                )}
                            </div>
                            <div className="w-full rounded-xl bg-card">
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

                const closeFilterModal = () => {
                    setFilterModalOpen(false);
                    setSaveFilterOpen(false);
                    setSaveFilterName('');
                };

                return isMobileViewport ? (
                    <BottomSheet
                        open={filterModalOpen}
                        onOpenChange={(next) => { if (!next) closeFilterModal(); }}
                        title="Advanced filters"
                        snapPoints={[0.92]}
                        defaultSnap={0}
                        footer={filterFooter}
                    >
                        {filterBody}
                    </BottomSheet>
                ) : (
                    <Modal
                        open={filterModalOpen}
                        onClose={closeFilterModal}
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
                title={`Archive purchase order ${orderPendingDeletion?.transaction_number ?? ''}?`}
                description="This removes the order from active views while retaining its items, activity history, messages, returns, and attachment for audit purposes."
                confirmLabel="Archive order"
                cancelLabel="Keep order"
                onConfirm={deleteOrder}
                confirmationText={orderPendingDeletion?.transaction_number}
                destructive
                processing={isDeletingOrder}
            />

            <ConfirmationDialog
                open={bulkArchiveOpen}
                onOpenChange={(open) => !open && !isBulkArchiving && setBulkArchiveOpen(false)}
                title={`Archive ${selectedOrderIds.length} ${selectedOrderIds.length === 1 ? 'order' : 'orders'}?`}
                description="This removes them from active views while retaining their items, activity history, messages, returns, and attachments for audit purposes."
                confirmLabel="Archive"
                cancelLabel="Cancel"
                onConfirm={bulkArchiveOrders}
                destructive
                processing={isBulkArchiving}
            />
        </AuthenticatedLayout>
    );
}
