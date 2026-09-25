import { Pagination } from '@/components/interior/pagination';
import { Dropdown } from '@/components/interior/dropdown';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { canShareDownloads, isShareCancelled, shareTextDownload } from '@/lib/native-download';
import { router } from '@inertiajs/react';
import { Download, KeyRound, MoreHorizontal, Pencil, RotateCcw, Search, Trash2 } from 'lucide-react';
import { UserCheck, UserRoundX } from 'lucide';
import { MorphIcon } from 'morphicons/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT =
    (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

const EMPTY_PAGE = { data: [], last_page: 1, current_page: 1 };

// One table + its own pagination -- shared by the Customer accounts and
// Staff accounts sections below, which page independently of each other.
function AccountsTable({ title, description, page, columns, loading, emptyState, onPageChange, pageLabel, onRowClick }) {
    return (
        <div className="space-y-3">
            <div>
                <h3 className="type-section-heading text-foreground">{title}</h3>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <Table
                data={page.data}
                columns={columns}
                getRowId={(user) => String(user.id)}
                className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                rowHeight={TABLE_ROW_HEIGHT}
                height={TABLE_VIEWPORT_HEIGHT}
                loading={loading}
                resizable
                onRowClick={onRowClick}
                emptyState={emptyState}
                emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
            />
            {page.last_page > 1 && (
                <div className="flex justify-end">
                    <Pagination
                        count={page.last_page}
                        page={page.current_page}
                        onPageChange={onPageChange}
                        label={pageLabel}
                    />
                </div>
            )}
        </div>
    );
}

export function AccountsPanel({ customerUsers = EMPTY_PAGE, staffUsers = EMPTY_PAGE, filters, roleLabels = {}, filterRouteName, filterExtraParams = {}, onEdit, onResetPassword, loading = false }) {
    const [search, setSearch] = useState(filters.search);
    const [pendingAction, setPendingAction] = useState(null);
    const [tableLoading, setTableLoading] = useState(false);
    const [exportError, setExportError] = useState('');
    const latestFilterVisit = useRef(0);

    const applyFilters = (overrides = {}) => {
        const visit = ++latestFilterVisit.current;

        router.get(
            route(filterRouteName),
            { search, ...filterExtraParams, ...overrides },
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

        const timeout = setTimeout(() => applyFilters({ search, customer_page: 1, staff_page: 1 }), 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const deleteUser = (user) => {
        setPendingAction({ type: 'delete', user });
    };

    const toggleActive = (user) => {
        if (user.is_active) {
            setPendingAction({ type: 'deactivate', user });
            return;
        }

        router.post(route('admin.users.toggle-active', user.public_id));
    };

    const restoreUser = (user) => {
        setPendingAction({ type: 'restore', user });
    };

    const downloadAccountData = async (user) => {
        const url = route('admin.users.data-export', user.public_id);
        if (!canShareDownloads()) {
            window.location.assign(url);
            return;
        }

        setExportError('');
        try {
            await shareTextDownload(url, `account-data-${user.public_id}.json`);
        } catch (error) {
            if (!isShareCancelled(error)) {
                setExportError("The account data couldn't be prepared. Check your connection and try again.");
            }
        }
    };

    const confirmPendingAction = () => {
        if (!pendingAction) return;

        const { type, user } = pendingAction;
        const options = { onFinish: () => setPendingAction(null) };
        if (type === 'delete') {
            router.delete(route('admin.users.destroy', user.public_id), options);
            return;
        }

        if (type === 'restore') {
            router.post(route('admin.users.restore', user.public_id), {}, options);
            return;
        }

            router.post(route('admin.users.toggle-active', user.public_id), {}, options);
    };

    const columns = useMemo(
        () => [
            // Every column below carries an explicit pixel width, including
            // this one, so the table resolves a fixed total width on the very
            // first render and switches to table-layout: fixed immediately --
            // without it, the table stays in auto-layout with a sticky header
            // until a column is manually resized, a combination some mobile
            // browser engines (seen on a Huawei tablet) render with
            // misaligned/overlapping columns.
            { key: 'full_name', header: 'Name', sortable: true, width: '200px' },
            { key: 'email', header: 'Email', sortable: true, width: '240px' },
            {
                key: 'role',
                header: 'Role',
                sortable: true,
                width: '110px',
                sortValue: (user) => roleLabels[user.role] ?? user.role,
                cell: (user) => roleLabels[user.role] ?? user.role,
            },
            {
                key: 'linked_customer_name',
                header: 'Linked Customer',
                sortable: true,
                width: '200px',
                cell: (user) => user.linked_customer_name ?? '-',
            },
            {
                key: 'is_active',
                header: 'Status',
                width: '140px',
                cell: (user) => (
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={user.deleted_at ? 'warning' : user.is_active ? 'success' : 'neutral'}
                            size="sm"
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {user.deleted_at ? 'Pending deletion' : user.is_active ? 'Active' : 'Inactive'}
                        </AnimatedBadge>
                    </div>
                ),
            },
            {
                key: 'actions',
                header: '',
                width: '56px',
                cell: (user) => {
                    const items = user.deleted_at ? [
                        {
                            value: 'restore',
                            label: 'Restore',
                            icon: <RotateCcw />,
                            onSelect: () => restoreUser(user),
                        },
                        {
                            value: 'export',
                            label: 'Download data',
                            icon: <Download />,
                            onSelect: () => downloadAccountData(user),
                        },
                    ] : [
                        {
                            value: 'export',
                            label: 'Download data',
                            icon: <Download />,
                            onSelect: () => downloadAccountData(user),
                        },
                        {
                            value: 'edit',
                            label: 'Edit',
                            icon: <Pencil />,
                            onSelect: () => onEdit(user),
                        },
                        {
                            value: 'reset-password',
                            label: 'Reset Password',
                            icon: <KeyRound />,
                            onSelect: () => onResetPassword(user),
                        },
                        {
                            value: user.is_active ? 'deactivate' : 'reactivate',
                            label: user.is_active ? 'Deactivate' : 'Reactivate',
                            icon: (
                                <MorphIcon
                                    aria-hidden="true"
                                    icon={user.is_active ? UserRoundX : UserCheck}
                                    spring="snappy"
                                    reducedMotion="user"
                                    size={16}
                                />
                            ),
                            onSelect: () => toggleActive(user),
                        },
                        {
                            value: 'delete',
                            label: 'Delete',
                            icon: <Trash2 />,
                            onSelect: () => deleteUser(user),
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
                                label={`Actions for ${user.full_name}`}
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
        [onEdit, onResetPassword, roleLabels],
    );

    // Staff accounts (admin/office/agent) never have a linked customer --
    // the column would just read "-" on every row.
    const staffColumns = useMemo(
        () => columns.filter((column) => column.key !== 'linked_customer_name'),
        [columns],
    );

    // Customer accounts are always role "Customer" -- the column would
    // never show anything else.
    const customerColumns = useMemo(
        () => columns.filter((column) => column.key !== 'role'),
        [columns],
    );

    return (
        <div className="space-y-8">
            <div className="flex flex-wrap items-center justify-center bg-background">
                <Input
                    type="text"
                    value={search}
                    onChange={setSearch}
                    placeholder="Name or email"
                    aria-label="Search accounts"
                    leftIcon={<Search className="h-4 w-4" />}
                    classNames={{
                        root: 'w-80',
                        field: 'h-9 w-80 rounded-full border-border bg-transparent shadow-none',
                        input: 'text-sm',
                    }}
                />
            </div>

            {exportError && (
                <p role="alert" className="text-center text-sm text-destructive">
                    {exportError}
                </p>
            )}

            <AccountsTable
                title="Customer accounts"
                description="Login accounts linked to a customer company."
                page={customerUsers}
                columns={customerColumns}
                loading={loading || tableLoading}
                emptyState="No customer accounts found. Try a different search."
                onPageChange={(page) => applyFilters({ customer_page: page })}
                pageLabel="Customer accounts pagination"
                // Pending-deletion accounts don't offer Edit from the row
                // menu either (only Restore/Download), so a click shouldn't
                // open it for them.
                onRowClick={(user) => { if (!user.deleted_at) onEdit(user); }}
            />

            <AccountsTable
                title="Staff accounts"
                description="Admin, office, and agent accounts."
                page={staffUsers}
                columns={staffColumns}
                loading={loading || tableLoading}
                emptyState="No staff accounts found. Try a different search or add an account."
                onPageChange={(page) => applyFilters({ staff_page: page })}
                pageLabel="Staff accounts pagination"
            />

            <ConfirmationDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
                title={
                    pendingAction?.type === 'delete'
                        ? `Schedule ${pendingAction.user.full_name}'s account for deletion?`
                        : pendingAction?.type === 'restore'
                            ? `Restore ${pendingAction.user.full_name}'s account?`
                        : `Deactivate ${pendingAction?.user.full_name}'s account?`
                }
                description={
                    pendingAction?.type === 'delete'
                        ? `This immediately blocks sign-in. Personal account data will be permanently erased after ${filters.retention_days} days unless an administrator restores the account first. Orders and other required business records will be retained without the account link.`
                        : pendingAction?.type === 'restore'
                            ? 'This cancels the scheduled deletion and restores sign-in access.'
                        : 'They will not be able to sign in until an administrator reactivates the account.'
                }
                confirmLabel={pendingAction?.type === 'delete' ? 'Schedule deletion' : pendingAction?.type === 'restore' ? 'Restore account' : 'Deactivate account'}
                cancelLabel={pendingAction?.type === 'delete' ? 'Keep account' : pendingAction?.type === 'restore' ? 'Keep scheduled' : 'Keep active'}
                onConfirm={confirmPendingAction}
                confirmationText={pendingAction?.type === 'delete' ? pendingAction.user.full_name : undefined}
                destructive={pendingAction?.type !== 'restore'}
            />
        </div>
    );
}
