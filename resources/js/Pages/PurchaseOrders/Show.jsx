import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { statusBadge, formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const TABLE_ROW_HEIGHT = 48;
const TABLE_MAX_HEIGHT = 440;

function autoTableHeight(rowCount) {
    return Math.min(TABLE_MAX_HEIGHT, (Math.max(rowCount, 1) + 1) * TABLE_ROW_HEIGHT + 2);
}

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
            {
                key: 'display_name',
                header: 'Product',
                cell: (item) => (item.__isTotal ? null : item.display_name),
            },
            {
                key: 'quantity',
                header: 'Ordered',
                cell: (item) => (item.__isTotal ? null : item.quantity),
            },
            {
                key: 'delivered_quantity',
                header: 'Delivered',
                cell: (item) => (item.__isTotal ? null : (item.delivered_quantity ?? 0)),
            },
            {
                key: 'line_total',
                header: 'Amount',
                align: showDeliverColumn ? undefined : 'right',
                cell: (item) => {
                    if (!item.__isTotal) return Number(item.line_total ?? 0).toFixed(2);
                    if (showDeliverColumn) return null;

                    return (
                        <span className="font-semibold text-gray-900">
                            Total: {Number(item.line_total).toFixed(2)}
                        </span>
                    );
                },
            },
            ...(showDeliverColumn
                ? [
                      {
                          key: 'deliver_now',
                          header: 'Deliver Now',
                          align: 'right',
                          cell: (item) =>
                              item.__isTotal ? (
                                  <div className="flex items-center justify-end gap-3">
                                      <span className="font-semibold text-gray-900">
                                          Total: {Number(item.line_total).toFixed(2)}
                                      </span>
                                      <Button type="submit" variant="primary" size="compact" disabled={processing}>
                                          Settle
                                      </Button>
                                  </div>
                              ) : (
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

    const itemRows = useMemo(() => {
        if (order.items.length === 0) return [];

        const totals = order.items.reduce(
            (acc, item) => ({
                quantity: acc.quantity + (item.quantity ?? 0),
                delivered_quantity: acc.delivered_quantity + (item.delivered_quantity ?? 0),
                pending_quantity: acc.pending_quantity + (item.pending_quantity ?? 0),
                line_total: acc.line_total + Number(item.line_total ?? 0),
            }),
            { quantity: 0, delivered_quantity: 0, pending_quantity: 0, line_total: 0 },
        );

        return [
            ...order.items,
            { id: '__total', __isTotal: true, ...totals },
        ];
    }, [order.items]);

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

            <div className="mx-auto max-w-7xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    <div className="p-6">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Customer</h3>
                        <p className="mt-2 font-medium text-gray-900">{order.customer.name}</p>
                        <div className="mt-1 space-y-0.5">
                            {order.customer.email && <p className="text-sm text-gray-500">{order.customer.email}</p>}
                            {order.customer.phone && <p className="text-sm text-gray-500">{order.customer.phone}</p>}
                            {order.customer.address && <p className="text-sm text-gray-500">{order.customer.address}</p>}
                        </div>
                        <dl className="mt-4 space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                            <div className="flex gap-2">
                                <dt className="w-28 shrink-0 text-gray-500">Submitted</dt>
                                <dd className="text-gray-900">{formatDateTime(order.submitted_at)}</dd>
                            </div>
                            <div className="flex gap-2">
                                <dt className="w-28 shrink-0 text-gray-500">Last Updated</dt>
                                <dd className="text-gray-900">{formatDateTime(order.updated_at)}</dd>
                            </div>
                        </dl>
                    </div>
                    <div className="p-6">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Order</h3>
                        <div className="mt-2 flex items-center gap-8">
                            <div>
                                <p className="text-xs text-gray-500">Status</p>
                                <span className={`mt-1 inline-block rounded-full px-3 py-1 text-base font-semibold ${badge.className}`}>
                                    {badge.label}
                                </span>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Total</p>
                                <p className="mt-1 text-3xl font-semibold text-gray-900">{Number(order.total).toFixed(2)}</p>
                            </div>
                        </div>
                        <dl className="mt-4 space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                            {order.remarks && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Remarks</dt>
                                    <dd className="text-gray-900">{order.remarks}</dd>
                                </div>
                            )}
                            {order.has_attachment && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Attachment</dt>
                                    <dd>
                                        <a
                                            href={route('purchase-orders.attachment', order.id)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-indigo-600 hover:underline"
                                        >
                                            View File
                                        </a>
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Items and Fulfillment</h3>
                        {showDeliverColumn && (
                            <span className="text-sm text-gray-500">Enter the quantity delivered in this batch.</span>
                        )}
                    </div>
                    <form onSubmit={submitFulfillment}>
                        <Table
                            data={itemRows}
                            columns={itemColumns}
                            getRowId={(item) => String(item.id)}
                            className="rounded-xl border-gray-200 [&_tbody_tr:last-child]:bg-gray-50"
                            height={autoTableHeight(itemRows.length)}
                            resizable
                            reorderable
                            emptyState="No items found for this order."
                        />
                    </form>
                </div>

                <div>
                    <div className="mb-3">
                        <h3 className="text-lg font-semibold text-gray-900">Update History</h3>
                        <p className="text-sm text-gray-500">Remarks and changes recorded by update time.</p>
                    </div>
                    <Table
                        data={auditLogsRows}
                        columns={auditLogColumns}
                        getRowId={(audit) => audit.__rowId}
                        className="rounded-xl border-gray-200"
                        height={autoTableHeight(auditLogsRows.length)}
                        resizable
                        reorderable
                        emptyState="No updates recorded yet."
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
