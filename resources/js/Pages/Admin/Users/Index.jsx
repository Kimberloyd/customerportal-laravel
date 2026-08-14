import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Users
                    </h2>
                    <Link
                        href={route('admin.users.create')}
                        className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        Add User
                    </Link>
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
                    <label className="flex flex-col text-sm text-gray-600">
                        Search
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        />
                    </label>
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
                    <button
                        type="submit"
                        className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Apply
                    </button>
                </form>

                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Name</th>
                                    <th className="py-2 pr-4">Email</th>
                                    <th className="py-2 pr-4">Role</th>
                                    <th className="py-2 pr-4">Linked Customer</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-4 text-center text-gray-400">
                                            No users match these filters.
                                        </td>
                                    </tr>
                                )}
                                {users.data.map((user) => {
                                    const isSelf = user.id === auth.user.id;

                                    return (
                                        <tr key={user.id}>
                                            <td className="py-2 pr-4">{user.full_name}</td>
                                            <td className="py-2 pr-4">{user.email}</td>
                                            <td className="py-2 pr-4">{roleLabels[user.role]}</td>
                                            <td className="py-2 pr-4">{user.linked_customer_name ?? '-'}</td>
                                            <td className="py-2 pr-4">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs ${
                                                        user.is_active
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-gray-200 text-gray-700'
                                                    }`}
                                                >
                                                    {user.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="space-x-2 py-2 pr-4 whitespace-nowrap">
                                                <Link
                                                    href={route('admin.users.edit', user.id)}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    Edit
                                                </Link>
                                                {!isSelf && (
                                                    <>
                                                        <button
                                                            onClick={() => toggleActive(user)}
                                                            className="text-gray-600 hover:underline"
                                                        >
                                                            {user.is_active ? 'Deactivate' : 'Reactivate'}
                                                        </button>
                                                        <button
                                                            onClick={() => deleteUser(user)}
                                                            className="text-red-600 hover:underline"
                                                        >
                                                            Delete
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
