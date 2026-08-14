import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ order, isCustomerViewer, canManageFulfillment, canComplete, canCancel }) {
    const badge = statusBadge(order.status);
    const showDeliverColumn = canManageFulfillment && !order.is_terminal;

    const { data, setData, post, processing } = useForm({
        received: Object.fromEntries(order.items.map((item) => [item.id, 0])),
    });

    const submitFulfillment = (e) => {
        e.preventDefault();
        if (!confirm('Submit this fulfillment update?')) return;

        const formData = new FormData();
        order.items.forEach((item) => {
            formData.append(`received_${item.id}`, data.received[item.id]);
        });

        post(route('purchase-orders.receive', order.id), {
            data: formData,
            forceFormData: true,
        });
    };

    const complete = () => {
        if (!confirm('Mark this order as fully completed?')) return;
        router.post(route('purchase-orders.complete', order.id));
    };

    const cancel = () => {
        if (!confirm('Cancel this order?')) return;
        router.post(route('purchase-orders.cancel', order.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {order.po_number}
                    </h2>
                    <div className="flex flex-wrap items-center gap-2">
                        {canCancel && (
                            <button
                                onClick={cancel}
                                className="rounded-md bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100"
                            >
                                Cancel Order
                            </button>
                        )}
                        {canComplete && (
                            <button
                                onClick={complete}
                                className="rounded-md bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-100"
                            >
                                Mark Completed
                            </button>
                        )}
                        <a
                            href={`${route('purchase-orders.print', order.id)}?output=printer&auto_print=1`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Printer
                        </a>
                        <a
                            href={`${route('purchase-orders.print', order.id)}?output=pdf&auto_print=1`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            PDF
                        </a>
                        <Link
                            href={route('purchase-orders.edit', order.id)}
                            className="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Edit Order
                        </Link>
                        <Link
                            href={route('purchase-orders.index')}
                            className="text-sm text-gray-600 hover:underline"
                        >
                            Back to Orders
                        </Link>
                    </div>
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
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Items and Fulfillment</h3>
                        {showDeliverColumn && (
                            <span className="text-sm text-gray-500">Enter the quantity delivered in this batch.</span>
                        )}
                    </div>
                    <form onSubmit={submitFulfillment}>
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Product</th>
                                    <th className="py-2 pr-4">Ordered</th>
                                    <th className="py-2 pr-4">Delivered</th>
                                    <th className="py-2 pr-4">Pending</th>
                                    {showDeliverColumn && <th className="py-2 pr-4">Deliver Now</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {order.items.length === 0 && (
                                    <tr>
                                        <td colSpan={showDeliverColumn ? 5 : 4} className="py-4 text-center text-gray-400">
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
                                        {showDeliverColumn && (
                                            <td className="py-2 pr-4">
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={item.pending_quantity}
                                                    disabled={item.pending_quantity === 0}
                                                    value={data.received[item.id]}
                                                    onChange={(e) =>
                                                        setData('received', {
                                                            ...data.received,
                                                            [item.id]: e.target.value,
                                                        })
                                                    }
                                                    className="w-20 rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                                                />
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {showDeliverColumn && (
                            <div className="mt-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    Save Fulfillment
                                </button>
                            </div>
                        )}
                    </form>
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
