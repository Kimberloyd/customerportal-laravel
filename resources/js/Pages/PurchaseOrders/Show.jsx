import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

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

    const itemColumns = useMemo(
        () => [
            { key: 'display_name', header: 'Product' },
            { key: 'quantity', header: 'Ordered' },
            {
                key: 'delivered_quantity',
                header: 'Delivered',
                cell: (item) => item.delivered_quantity ?? 0,
            },
            { key: 'pending_quantity', header: 'Pending' },
            ...(showDeliverColumn
                ? [
                      {
                          key: 'deliver_now',
                          header: 'Deliver Now',
                          cell: (item) => (
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
                          ),
                      },
                  ]
                : []),
        ],
        [showDeliverColumn, data.received],
    );

    const auditLogsRows = useMemo(
        () => order.audit_logs.map((audit, index) => ({ ...audit, __rowId: String(index) })),
        [order.audit_logs],
    );

    const auditLogColumns = useMemo(
        () => [
            { key: 'created_at', header: 'Updated At', cell: (audit) => formatDateTime(audit.created_at) },
            ...(!isCustomerViewer
                ? [
                      {
                          key: 'actor_name',
                          header: 'By',
                          cell: (audit) => (audit.actor_name ? `${audit.actor_name} (${audit.actor_role})` : '-'),
                      },
                  ]
                : []),
            { key: 'action', header: 'Action' },
            { key: 'details', header: 'Change Details', cell: (audit) => audit.details ?? '-' },
            { key: 'remarks', header: 'Remarks', cell: (audit) => audit.remarks ?? '-' },
        ],
        [isCustomerViewer],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {order.po_number}
                    </h2>
                    <div className="flex flex-wrap items-center gap-2">
                        {canCancel && (
                            <Button
                                variant="tertiary"
                                size="compact"
                                className="text-red-600 hover:text-red-700"
                                onClick={cancel}
                            >
                                Cancel Order
                            </Button>
                        )}
                        {canComplete && (
                            <Button
                                variant="tertiary"
                                size="compact"
                                className="text-green-700 hover:text-green-800"
                                onClick={complete}
                            >
                                Mark Completed
                            </Button>
                        )}
                        <Button asChild variant="tertiary" size="compact">
                            <a
                                href={`${route('purchase-orders.print', order.id)}?output=printer&auto_print=1`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Printer
                            </a>
                        </Button>
                        <Button asChild variant="tertiary" size="compact">
                            <a
                                href={`${route('purchase-orders.print', order.id)}?output=pdf&auto_print=1`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                PDF
                            </a>
                        </Button>
                        <Button asChild variant="tertiary" size="compact">
                            <Link href={route('purchase-orders.edit', order.id)}>Edit Order</Link>
                        </Button>
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('purchase-orders.index')}>Back to Orders</Link>
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={order.po_number} />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
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
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Items and Fulfillment</h3>
                        {showDeliverColumn && (
                            <span className="text-sm text-gray-500">Enter the quantity delivered in this batch.</span>
                        )}
                    </div>
                </div>
                <form onSubmit={submitFulfillment}>
                    <Table
                        data={order.items}
                        columns={itemColumns}
                        getRowId={(item) => String(item.id)}
                        resizable
                        reorderable
                        emptyState="No items found for this order."
                    />
                    {showDeliverColumn && (
                        <div className="mt-4">
                            <Button type="submit" variant="primary" disabled={processing}>
                                Save Fulfillment
                            </Button>
                        </div>
                    )}
                </form>

                <div className="rounded-lg bg-white p-6 shadow-sm">
                    <h3 className="mb-1 text-lg font-semibold text-gray-900">Update History</h3>
                    <p className="text-sm text-gray-500">Remarks and changes recorded by update time.</p>
                </div>
                <Table
                    data={auditLogsRows}
                    columns={auditLogColumns}
                    getRowId={(audit) => audit.__rowId}
                    resizable
                    reorderable
                    emptyState="No updates recorded yet."
                />
            </div>
        </AuthenticatedLayout>
    );
}
