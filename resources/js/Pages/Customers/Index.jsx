import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'all', label: 'All' },
];

export default function Index({ customers, filters }) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = () => {
        router.get(
            route('customers.index'),
            { search, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const deleteCustomer = (customer) => {
        if (!confirm(`Delete ${customer.company_name}?`)) return;
        router.delete(route('customers.destroy', customer.id));
    };

    const toggleActive = (customer) => {
        router.post(route('customers.toggle-active', customer.id));
    };

    const columns = useMemo(
        () => [
            { key: 'customer_code', header: 'Code', sortable: true },
            { key: 'company_name', header: 'Company', sortable: true },
            { key: 'channel', header: 'Channel', sortable: true },
            {
                key: 'contact_person',
                header: 'Contact',
                sortable: true,
                cell: (customer) => customer.contact_person ?? '-',
            },
            { key: 'email', header: 'Email', cell: (customer) => customer.email ?? '-' },
            { key: 'phone', header: 'Phone', cell: (customer) => customer.phone ?? '-' },
            {
                key: 'is_active',
                header: 'Status',
                cell: (customer) => (
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                            customer.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-200 text-gray-700'
                        }`}
                    >
                        {customer.is_active ? 'Active' : 'Inactive'}
                    </span>
                ),
            },
            {
                key: 'actions',
                header: 'Actions',
                cell: (customer) => (
                    <div className="space-x-2 whitespace-nowrap">
                        <Link
                            href={route('customers.edit', customer.id)}
                            className="text-indigo-600 hover:underline"
                        >
                            Edit
                        </Link>
                        <button
                            onClick={() => toggleActive(customer)}
                            className="text-gray-600 hover:underline"
                        >
                            {customer.is_active ? 'Deactivate' : 'Reactivate'}
                        </button>
                        <button
                            onClick={() => deleteCustomer(customer)}
                            className="text-red-600 hover:underline"
                        >
                            Delete
                        </button>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Customers
                    </h2>
                    <Link
                        href={route('customers.create')}
                        className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        Add Customer
                    </Link>
                </div>
            }
        >
            <Head title="Customers" />

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
                        Status
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
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

                <>
                    <Table
                        data={customers.data}
                        columns={columns}
                        getRowId={(customer) => String(customer.id)}
                        resizable
                        reorderable
                        emptyState="No customers match these filters."
                    />

                    {customers.last_page > 1 && (
                        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {customers.links.map((link, index) => (
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
