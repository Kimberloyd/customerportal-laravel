import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';

function Tile({ label, value }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="text-sm font-medium text-gray-500">{label}</h3>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
        </div>
    );
}

export default function Dashboard({ totalCustomers, totalProducts, totalOrders, openMessages, recentCustomers, recentProducts, recentOrders }) {
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
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Customers</h3>
                        <Link href={route('customers.index')} className="text-sm text-indigo-600 hover:underline">View All</Link>
                    </div>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Code</th>
                                <th className="py-2 pr-4">Company</th>
                                <th className="py-2 pr-4">Contact</th>
                                <th className="py-2 pr-4">Email</th>
                                <th className="py-2 pr-4">Phone</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentCustomers.length === 0 && (
                                <tr><td colSpan={5} className="py-4 text-center text-gray-400">No customers found.</td></tr>
                            )}
                            {recentCustomers.map((c) => (
                                <tr key={c.id}>
                                    <td className="py-2 pr-4">{c.customer_code ?? '-'}</td>
                                    <td className="py-2 pr-4">
                                        <Link href={route('customers.edit', c.id)} className="text-indigo-600 hover:underline">{c.company_name}</Link>
                                    </td>
                                    <td className="py-2 pr-4">{c.contact_person ?? '-'}</td>
                                    <td className="py-2 pr-4">{c.email ?? '-'}</td>
                                    <td className="py-2 pr-4">{c.phone ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Products</h3>
                        <Link href={route('products.index')} className="text-sm text-indigo-600 hover:underline">View All</Link>
                    </div>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Generic Name</th>
                                <th className="py-2 pr-4">Brand Name</th>
                                <th className="py-2 pr-4">SKU</th>
                                <th className="py-2 pr-4">Unit</th>
                                <th className="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentProducts.length === 0 && (
                                <tr><td colSpan={5} className="py-4 text-center text-gray-400">No products found.</td></tr>
                            )}
                            {recentProducts.map((p) => (
                                <tr key={p.id}>
                                    <td className="py-2 pr-4">{p.generic_name ?? '-'}</td>
                                    <td className="py-2 pr-4">
                                        <Link href={route('products.edit', p.id)} className="text-indigo-600 hover:underline">{p.product_name}</Link>
                                    </td>
                                    <td className="py-2 pr-4">{p.sku ?? '-'}</td>
                                    <td className="py-2 pr-4">{p.unit ?? '-'}</td>
                                    <td className="py-2 pr-4">
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${p.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'}`}>
                                            {p.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Recent Orders</h3>
                        <Link href={route('purchase-orders.index')} className="text-sm text-indigo-600 hover:underline">View All</Link>
                    </div>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">PO Number</th>
                                <th className="py-2 pr-4">Date</th>
                                <th className="py-2 pr-4">Customer</th>
                                <th className="py-2 pr-4">Ordered</th>
                                <th className="py-2 pr-4">Balance</th>
                                <th className="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentOrders.length === 0 && (
                                <tr><td colSpan={6} className="py-4 text-center text-gray-400">No orders found.</td></tr>
                            )}
                            {recentOrders.map((order) => {
                                const badge = statusBadge(order.status);

                                return (
                                    <tr key={order.id}>
                                        <td className="py-2 pr-4">
                                            <Link href={route('purchase-orders.show', order.id)} className="text-indigo-600 hover:underline">
                                                {order.po_number}
                                            </Link>
                                        </td>
                                        <td className="py-2 pr-4">{formatDateTime(order.submitted_at)}</td>
                                        <td className="py-2 pr-4">{order.customer_name}</td>
                                        <td className="py-2 pr-4">{order.ordered_units}</td>
                                        <td className="py-2 pr-4">{order.balance_units}</td>
                                        <td className="py-2 pr-4">
                                            <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>{badge.label}</span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>

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
