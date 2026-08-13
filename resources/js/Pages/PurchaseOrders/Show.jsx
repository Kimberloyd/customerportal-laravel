import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';

export default function Show({ order, isCustomerViewer }) {
    const badge = statusBadge(order.status);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {order.po_number}
                    </h2>
                    <Link
                        href={route('purchase-orders.index')}
                        className="text-sm text-gray-600 hover:underline"
                    >
                        Back to Orders
                    </Link>
                </div>
            }
        >
            <Head title={order.po_number} />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-6 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-2">
                    <div>
                        <h3 className="text-sm font-semibold text-gray-500">Customer</h3>
                        <p className="mt-1 text-gray-900">{order.customer.name}</p>
                        {order.customer.email && <p className="text-sm text-gray-500">{order.customer.email}</p>}
                        {order.customer.phone && <p className="text-sm text-gray-500">{order.customer.phone}</p>}
                        {order.customer.address && <p className="text-sm text-gray-500">{order.customer.address}</p>}
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold text-gray-500">Order</h3>
                        <p className="mt-1 text-sm text-gray-700">
                            <strong>Submitted:</strong> {formatDateTime(order.submitted_at)}
                        </p>
                        <p className="text-sm text-gray-700">
                            <strong>Last Updated:</strong> {formatDateTime(order.updated_at)}
                        </p>
                        <p className="text-sm text-gray-700">
                            <strong>Status:</strong>{' '}
                            <span className={`rounded-full px-2 py-0.5 text-xs ${badge.className}`}>
                                {badge.label}
                            </span>
                        </p>
                        <p className="text-sm text-gray-700">
                            <strong>Total:</strong> {Number(order.total).toFixed(2)}
                        </p>
                        {order.remarks && (
                            <p className="text-sm text-gray-700"><strong>Remarks:</strong> {order.remarks}</p>
                        )}
                        {order.has_attachment && (
                            <p className="text-sm text-gray-700">
                                <strong>Attachment:</strong>{' '}
                                <a
                                    href={route('purchase-orders.attachment', order.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 hover:underline"
                                >
                                    View File
                                </a>
                            </p>
                        )}
                    </div>
                </div>

                <div className="rounded-lg bg-white p-6 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">Items and Fulfillment</h3>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Product</th>
                                <th className="py-2 pr-4">Ordered</th>
                                <th className="py-2 pr-4">Delivered</th>
                                <th className="py-2 pr-4">Pending</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {order.items.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="py-4 text-center text-gray-400">
                                        No items found for this order.
                                    </td>
                                </tr>
                            )}
                            {order.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-2 pr-4">{item.display_name}</td>
                                    <td className="py-2 pr-4">{item.quantity}</td>
                                    <td className="py-2 pr-4">{item.delivered_quantity ?? 0}</td>
                                    <td className="py-2 pr-4">{item.pending_quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-lg bg-white p-6 shadow-sm">
                    <h3 className="mb-1 text-lg font-semibold text-gray-900">Update History</h3>
                    <p className="mb-3 text-sm text-gray-500">Remarks and changes recorded by update time.</p>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Updated At</th>
                                {!isCustomerViewer && <th className="py-2 pr-4">By</th>}
                                <th className="py-2 pr-4">Action</th>
                                <th className="py-2 pr-4">Change Details</th>
                                <th className="py-2 pr-4">Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {order.audit_logs.length === 0 && (
                                <tr>
                                    <td colSpan={isCustomerViewer ? 4 : 5} className="py-4 text-center text-gray-400">
                                        No updates recorded yet.
                                    </td>
                                </tr>
                            )}
                            {order.audit_logs.map((audit, index) => (
                                <tr key={index}>
                                    <td className="py-2 pr-4">{formatDateTime(audit.created_at)}</td>
                                    {!isCustomerViewer && (
                                        <td className="py-2 pr-4">
                                            {audit.actor_name ? `${audit.actor_name} (${audit.actor_role})` : '-'}
                                        </td>
                                    )}
                                    <td className="py-2 pr-4">{audit.action}</td>
                                    <td className="py-2 pr-4">{audit.details ?? '-'}</td>
                                    <td className="py-2 pr-4">{audit.remarks ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
