import { Pagination } from '@/components/interior/pagination';
import { Dropdown } from '@/components/interior/dropdown';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { router } from '@inertiajs/react';
import { KeyRound, MoreHorizontal, Pencil, Search, Trash2, UserCheck, UserRoundX } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const PAGE_SIZE = 10;
const TABLE_ROW_HEIGHT = 48;
const HORIZONTAL_SCROLLBAR_HEIGHT = 20;
const TABLE_VIEWPORT_HEIGHT =
    (PAGE_SIZE + 1) * TABLE_ROW_HEIGHT + HORIZONTAL_SCROLLBAR_HEIGHT;

export function AccountsPanel({ users, filters, roleLabels, filterRouteName, filterExtraParams = {}, onEdit, onResetPassword }) {
    const [search, setSearch] = useState(filters.search);
    const [pendingAction, setPendingAction] = useState(null);

    const applyFilters = (overrides = {}) => {
        router.get(
            route(filterRouteName),
            { search, role: filters.role, ...filterExtraParams, ...overrides },
            { preserveState: true, preserveScroll: true },
        );
    };

    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timeout = setTimeout(() => applyFilters({ search, page: 1 }), 400);
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

        router.post(route('admin.users.toggle-active', user.id));
    };

    const confirmPendingAction = () => {
        if (!pendingAction) return;

        const { type, user } = pendingAction;
        const options = { onFinish: () => setPendingAction(null) };
        if (type === 'delete') {
            router.delete(route('admin.users.destroy', user.id), options);
            return;
        }

        router.post(route('admin.users.toggle-active', user.id), {}, options);
    };

    const columns = useMemo(
        () => [
            { key: 'full_name', header: 'Name', sortable: true },
            { key: 'email', header: 'Email', sortable: true },
            {
                key: 'role',
                header: 'Role',
                sortable: true,
                sortValue: (user) => roleLabels[user.role] ?? user.role,
                cell: (user) => roleLabels[user.role] ?? user.role,
            },
            {
                key: 'linked_customer_name',
                header: 'Linked Customer',
                sortable: true,
                cell: (user) => user.linked_customer_name ?? '-',
            },
            {
                key: 'is_active',
                header: 'Status',
                cell: (user) => (
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={user.is_active ? 'success' : 'neutral'}
                            size="sm"
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {user.is_active ? 'Active' : 'Inactive'}
                        </AnimatedBadge>
                    </div>
                ),
            },
            {
                key: 'actions',
                header: '',
                width: '56px',
                reorderable: false,
                cell: (user) => {
                    const items = [
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
                            icon: user.is_active ? <UserRoundX /> : <UserCheck />,
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
                        <div className="flex items-center">
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

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-center bg-white">
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

            <Table
                data={users.data}
                columns={columns}
                getRowId={(user) => String(user.id)}
                className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                rowHeight={TABLE_ROW_HEIGHT}
                height={TABLE_VIEWPORT_HEIGHT}
                resizable
                reorderable
                emptyState="No accounts found. Try a different search or add an account."
                emptyStateHeight={PAGE_SIZE * TABLE_ROW_HEIGHT}
            />

            <div className="flex justify-end">
                <Pagination
                    count={users.last_page}
                    page={users.current_page}
                    onPageChange={(page) => applyFilters({ page })}
                    label="Accounts pagination"
                />
            </div>

            <ConfirmationDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
                title={
                    pendingAction?.type === 'delete'
                        ? `Delete ${pendingAction.user.full_name}'s account?`
                        : `Deactivate ${pendingAction?.user.full_name}'s account?`
                }
                description={
                    pendingAction?.type === 'delete'
                        ? 'This permanently removes their sign-in access and unlinks any customer account. This cannot be undone.'
                        : 'They will not be able to sign in until an administrator reactivates the account.'
                }
                confirmLabel={pendingAction?.type === 'delete' ? 'Delete account' : 'Deactivate account'}
                cancelLabel={pendingAction?.type === 'delete' ? 'Keep account' : 'Keep active'}
                onConfirm={confirmPendingAction}
                confirmationText={pendingAction?.type === 'delete' ? pendingAction.user.full_name : undefined}
                destructive
            />
        </div>
    );
}
