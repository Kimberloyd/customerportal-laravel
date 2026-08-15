import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function Index({ users, filters, roleLabels }) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);

    const applyFilters = () => {
        router.get(
            route('admin.users.index'),
            { search, role },
            { preserveState: true, preserveScroll: true },
        );
    };

    const deleteUser = (user) => {
        if (!confirm(`Delete ${user.full_name}?`)) return;
        router.delete(route('admin.users.destroy', user.id));
    };

    const toggleActive = (user) => {
        router.post(route('admin.users.toggle-active', user.id));
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
                cell: (user) => roleLabels[user.role],
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
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                            user.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-200 text-gray-700'
                        }`}
                    >
                        {user.is_active ? 'Active' : 'Inactive'}
                    </span>
                ),
            },
            {
                key: 'actions',
                header: 'Actions',
                cell: (user) => {
                    const isSelf = user.id === auth.user.id;
                    return (
                        <div className="flex items-center gap-1 whitespace-nowrap">
                            <Button asChild variant="ghost" size="compact">
                                <Link href={route('admin.users.edit', user.id)}>Edit</Link>
                            </Button>
                            {!isSelf && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="compact"
                                        onClick={() => toggleActive(user)}
                                    >
                                        {user.is_active ? 'Deactivate' : 'Reactivate'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="compact"
                                        className="text-red-600 hover:text-red-700"
                                        onClick={() => deleteUser(user)}
                                    >
                                        Delete
                                    </Button>
                                </>
                            )}
                        </div>
                    );
                },
            },
        ],
        [auth.user.id, roleLabels],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Users
                    </h2>
                    <Button asChild variant="primary">
                        <Link href={route('admin.users.create')}>Add User</Link>
                    </Button>
                </div>
            }
        >
            <Head title="Users" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters();
                    }}
                    className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm"
                >
                    <Input
                        label="Search"
                        type="text"
                        value={search}
                        onChange={setSearch}
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{ field: 'h-9 rounded-none' }}
                    />
                    <label className="flex flex-col text-sm text-gray-600">
                        Role
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="all">All Roles</option>
                            {Object.entries(roleLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button type="submit" variant="secondary" size="compact">
                        Apply
                    </Button>
                </form>

                <>
                    <Table
                        data={users.data}
                        columns={columns}
                        getRowId={(user) => String(user.id)}
                        resizable
                        reorderable
                        emptyState="No users match these filters."
                    />

                    {users.last_page > 1 && (
                        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {users.links.map((link, index) => (
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
