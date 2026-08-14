import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';

function statusLabel(status) {
    return status === 'partial' || status === 'processing' ? 'Partial' : status.charAt(0).toUpperCase() + status.slice(1);
}

export default function Print({ order, output, autoPrint }) {
    useEffect(() => {
        if (autoPrint) {
            window.print();
        }
    }, [autoPrint]);

    return (
        <AuthenticatedLayout
            header={
                <div className="no-print flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{order.po_number}</h2>
                    <div className="flex gap-2">
                        <button
                            onClick={() => window.print()}
                            className="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Print / Save PDF
                        </button>
                        <Link
                            href={route('purchase-orders.show', order.id)}
                            className="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Back To Order
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`${order.po_number} - Print`} />
            <style>{'@media print { .no-print { display: none !important; } }'}</style>

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section>
                    <h2 className="text-lg font-semibold text-gray-900">Purchase Order {order.po_number}</h2>
                    <p className="text-sm text-gray-600">
                        Submitted {formatDateTime(order.submitted_at)}
                        {output === 'pdf' && ' | Save destination as PDF.'}
                    </p>
                </section>

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
                        <p className="mt-1 text-sm text-gray-700"><strong>Submitted:</strong> {formatDateTime(order.submitted_at)}</p>
                        <p className="text-sm text-gray-700"><strong>Last Updated:</strong> {formatDateTime(order.updated_at)}</p>
                        <p className="text-sm text-gray-700"><strong>Status:</strong> {statusLabel(order.status)}</p>
                        <p className="text-sm text-gray-700"><strong>Total:</strong> PHP {Number(order.total).toFixed(2)}</p>
                        {order.remarks && <p className="text-sm text-gray-700"><strong>Remarks:</strong> {order.remarks}</p>}
                    </div>
                </div>

                <div className="rounded-lg bg-white p-6 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">Items</h3>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Product</th>
                                <th className="py-2 pr-4">Ordered</th>
                                <th className="py-2 pr-4">Delivered</th>
                                <th className="py-2 pr-4">Pending</th>
                                <th className="py-2 pr-4">Unit Price</th>
                                <th className="py-2 pr-4">Line Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {order.items.length === 0 && (
                                <tr><td colSpan={6} className="py-4 text-center text-gray-400">No items found for this order.</td></tr>
                            )}
                            {order.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-2 pr-4">{item.display_name}</td>
                                    <td className="py-2 pr-4">{item.quantity}</td>
                                    <td className="py-2 pr-4">{item.delivered_quantity ?? 0}</td>
                                    <td className="py-2 pr-4">{item.pending_quantity}</td>
                                    <td className="py-2 pr-4">PHP {Number(item.unit_price ?? 0).toFixed(2)}</td>
                                    <td className="py-2 pr-4">PHP {Number(item.line_total ?? 0).toFixed(2)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-lg bg-white p-6 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">Update History</h3>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Updated At</th>
                                <th className="py-2 pr-4">Action</th>
                                <th className="py-2 pr-4">Details</th>
                                <th className="py-2 pr-4">Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {order.audit_logs.length === 0 && (
                                <tr><td colSpan={4} className="py-4 text-center text-gray-400">No updates recorded yet.</td></tr>
                            )}
                            {order.audit_logs.map((audit, index) => (
                                <tr key={index}>
                                    <td className="py-2 pr-4">{formatDateTime(audit.created_at)}</td>
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
