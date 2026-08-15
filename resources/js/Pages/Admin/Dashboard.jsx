import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';

function Tile({ label, value }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="text-sm font-medium text-gray-500">{label}</h3>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
        </div>
    );
}

export default function Dashboard({ totalCustomers, totalProducts, totalOrders, openMessages, recentCustomers, recentProducts, recentOrders }) {
    const customerColumns = useMemo(
        () => [
            { key: 'customer_code', header: 'Code', cell: (c) => c.customer_code ?? '-' },
            {
                key: 'company_name',
                header: 'Company',
                cell: (c) => (
                    <Link href={route('customers.edit', c.id)} className="text-indigo-600 hover:underline">
                        {c.company_name}
                    </Link>
                ),
            },
            { key: 'contact_person', header: 'Contact', cell: (c) => c.contact_person ?? '-' },
            { key: 'email', header: 'Email', cell: (c) => c.email ?? '-' },
            { key: 'phone', header: 'Phone', cell: (c) => c.phone ?? '-' },
        ],
        [],
    );

    const productColumns = useMemo(
        () => [
            { key: 'generic_name', header: 'Generic Name', cell: (p) => p.generic_name ?? '-' },
            {
                key: 'product_name',
                header: 'Brand Name',
                cell: (p) => (
                    <Link href={route('products.edit', p.id)} className="text-indigo-600 hover:underline">
                        {p.product_name}
                    </Link>
                ),
            },
            { key: 'sku', header: 'SKU', cell: (p) => p.sku ?? '-' },
            { key: 'unit', header: 'Unit', cell: (p) => p.unit ?? '-' },
            {
                key: 'is_active',
                header: 'Status',
                cell: (p) => (
                    <span className={`rounded-full px-2 py-0.5 text-xs ${p.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'}`}>
                        {p.is_active ? 'Active' : 'Inactive'}
                    </span>
                ),
            },
        ],
        [],
    );

    const orderColumns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                cell: (order) => (
                    <Link href={route('purchase-orders.show', order.id)} className="text-indigo-600 hover:underline">
                        {order.po_number}
                    </Link>
                ),
            },
            { key: 'submitted_at', header: 'Date', cell: (order) => formatDateTime(order.submitted_at) },
            { key: 'customer_name', header: 'Customer' },
            { key: 'ordered_units', header: 'Ordered' },
            { key: 'balance_units', header: 'Balance' },
            {
                key: 'status',
                header: 'Status',
                cell: (order) => {
                    const badge = statusBadge(order.status);
                    return <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>{badge.label}</span>;
                },
            },
        ],
        [],
    );

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Administration</h2>}
        >
            <Head title="Administration" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Tile label="Customers" value={totalCustomers} />
                    <Tile label="Products" value={totalProducts} />
                    <Tile label="Orders" value={totalOrders} />
                    <Tile label="Open Conversations" value={openMessages} />
                </div>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Customers</h3>
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('customers.index')}>View All</Link>
                        </Button>
                    </div>
                </section>
                <Table
                    data={recentCustomers}
                    columns={customerColumns}
                    getRowId={(c) => String(c.id)}
                    resizable
                    reorderable
                    emptyState="No customers found."
                />

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Products</h3>
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('products.index')}>View All</Link>
                        </Button>
                    </div>
                </section>
                <Table
                    data={recentProducts}
                    columns={productColumns}
                    getRowId={(p) => String(p.id)}
                    resizable
                    reorderable
                    emptyState="No products found."
                />

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Orders</h3>
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('purchase-orders.index')}>View All</Link>
                        </Button>
                    </div>
                </section>
                <Table
                    data={recentOrders}
                    columns={orderColumns}
                    getRowId={(order) => String(order.id)}
                    resizable
                    reorderable
                    emptyState="No orders found."
                />

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">System Tools</h3>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <Link href={route('admin.users.index')} className="rounded-md border border-gray-200 p-3 text-sm hover:bg-gray-50">
                            <div className="font-medium text-gray-900">User Catalog</div>
                            <div className="text-gray-500">Create and maintain login credentials</div>
                        </Link>
                        <Link href={route('messages.index')} className="rounded-md border border-gray-200 p-3 text-sm hover:bg-gray-50">
                            <div className="font-medium text-gray-900">Customer Messages</div>
                            <div className="text-gray-500">Manage conversations and replies</div>
                        </Link>
                        <Link href={route('reports.orders')} className="rounded-md border border-gray-200 p-3 text-sm hover:bg-gray-50">
                            <div className="font-medium text-gray-900">Orders Report</div>
                            <div className="text-gray-500">Filter, print PDF, or export spreadsheet</div>
                        </Link>
                        <Link href={route('products.index')} className="rounded-md border border-gray-200 p-3 text-sm hover:bg-gray-50">
                            <div className="font-medium text-gray-900">Product Catalog</div>
                            <div className="text-gray-500">Review and maintain inventory records</div>
                        </Link>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
