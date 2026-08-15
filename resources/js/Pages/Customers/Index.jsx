import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
                    <div className="flex items-center gap-1 whitespace-nowrap">
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('customers.edit', customer.id)}>Edit</Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="compact"
                            onClick={() => toggleActive(customer)}
                        >
                            {customer.is_active ? 'Deactivate' : 'Reactivate'}
                        </Button>
                        <Button
                            variant="ghost"
                            size="compact"
                            className="text-red-600 hover:text-red-700"
                            onClick={() => deleteCustomer(customer)}
                        >
                            Delete
                        </Button>
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
                    <Button asChild variant="primary">
                        <Link href={route('customers.create')}>Add Customer</Link>
                    </Button>
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
                    <Input
                        label="Search"
                        type="text"
                        value={search}
                        onChange={setSearch}
                        leftIcon={<Search className="h-4 w-4" />}
                        classNames={{ field: 'h-9 rounded-none' }}
                    />
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
                    <Button type="submit" variant="secondary" size="compact">
                        Apply
                    </Button>
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
