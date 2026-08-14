import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Code</th>
                                    <th className="py-2 pr-4">Company</th>
                                    <th className="py-2 pr-4">Channel</th>
                                    <th className="py-2 pr-4">Contact</th>
                                    <th className="py-2 pr-4">Email</th>
                                    <th className="py-2 pr-4">Phone</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {customers.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="py-4 text-center text-gray-400">
                                            No customers match these filters.
                                        </td>
                                    </tr>
                                )}
                                {customers.data.map((customer) => (
                                    <tr key={customer.id}>
                                        <td className="py-2 pr-4">{customer.customer_code}</td>
                                        <td className="py-2 pr-4">{customer.company_name}</td>
                                        <td className="py-2 pr-4">{customer.channel}</td>
                                        <td className="py-2 pr-4">{customer.contact_person ?? '-'}</td>
                                        <td className="py-2 pr-4">{customer.email ?? '-'}</td>
                                        <td className="py-2 pr-4">{customer.phone ?? '-'}</td>
                                        <td className="py-2 pr-4">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${
                                                    customer.is_active
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-gray-200 text-gray-700'
                                                }`}
                                            >
                                                {customer.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="space-x-2 py-2 pr-4 whitespace-nowrap">
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
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
