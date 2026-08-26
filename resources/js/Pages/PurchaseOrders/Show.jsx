import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { Tooltip } from '@/components/motion/tooltip';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/interior/modal';
import { formatDateTime } from '@/utils/orderDisplay';
import { PdfPreview } from '@/components/PdfPreview';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useMemo, useState } from 'react';

const TABLE_ROW_HEIGHT = 48;
const TABLE_MAX_HEIGHT = 440;

function autoTableHeight(rowCount) {
    return Math.min(TABLE_MAX_HEIGHT, (Math.max(rowCount, 1) + 1) * TABLE_ROW_HEIGHT);
}

export default function Show({ order, isCustomerViewer, canManageFulfillment, canComplete, canCancel }) {
    usePurchaseOrderRealtime(order.id);

    const showDeliverColumn = canManageFulfillment && !order.is_terminal;
    const [attachmentPreviewOpen, setAttachmentPreviewOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState(null);
    const attachmentUrl = order.has_attachment ? route('purchase-orders.attachment', order.id) : null;
    const attachmentKind = order.attachment_kind;
    const attachmentPreviewable = attachmentKind === 'image' || attachmentKind === 'pdf';

    const { data, setData, post, transform, processing } = useForm({
        received: Object.fromEntries(order.items.map((item) => [item.id, 0])),
    });

    const submitFulfillment = (e) => {
        e.preventDefault();
        setPendingAction('fulfillment');
    };

    const confirmFulfillment = () => {
        transform((formData) => {
            const { received, ...rest } = formData;
            return {
                ...rest,
                ...Object.fromEntries(
                    Object.entries(received).map(([itemId, quantity]) => [`received_${itemId}`, quantity]),
                ),
            };
        });

        post(route('purchase-orders.receive', order.id), {
            forceFormData: true,
            onFinish: () => setPendingAction(null),
        });
    };

    const complete = () => {
        setPendingAction('complete');
    };

    const cancel = () => {
        setPendingAction('cancel');
    };

    const confirmPendingAction = () => {
        if (pendingAction === 'fulfillment') {
            confirmFulfillment();
            return;
        }

        const routeName = pendingAction === 'complete'
            ? 'purchase-orders.complete'
            : 'purchase-orders.cancel';
        router.post(route(routeName, order.id), {}, {
            onFinish: () => setPendingAction(null),
        });
    };

    const confirmationCopy = {
        fulfillment: {
            title: 'Record these delivered quantities?',
            description: 'This adds the entered quantities to the order’s delivery totals. Delivered quantities cannot be reduced later.',
            confirmLabel: 'Record delivery',
            cancelLabel: 'Review quantities',
            destructive: false,
        },
        complete: {
            title: 'Complete this order?',
            description: 'This marks every remaining item as delivered and closes the order. This cannot be undone.',
            confirmLabel: 'Complete order',
            cancelLabel: 'Keep order open',
            destructive: false,
        },
        cancel: {
            title: 'Cancel this order?',
            description: 'This closes the order and prevents future delivery updates. This cannot be undone.',
            confirmLabel: 'Cancel order',
            cancelLabel: 'Keep order open',
            destructive: true,
        },
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
                key: 'pending_quantity',
                header: 'Balance',
                align: showDeliverColumn ? undefined : 'right',
                cell: (item) => (item.__isTotal ? null : item.pending_quantity),
            },
            ...(showDeliverColumn
                ? [
                      {
                          key: 'deliver_now',
                          header: 'Deliver Now',
                          width: '140px',
                          cell: (item) =>
                              item.__isTotal ? null : (
                                  <Input
                                      type="number"
                                      min={0}
                                      max={item.pending_quantity}
                                      disabled={item.pending_quantity === 0}
                                      value={data.received[item.id]}
                                      onChange={(raw) => {
                                          const clamped =
                                              raw === ''
                                                  ? ''
                                                  : Math.max(0, Math.min(item.pending_quantity, Number(raw)));
                                          setData('received', {
                                              ...data.received,
                                              [item.id]: clamped,
                                          });
                                      }}
                                      classNames={{
                                          field: 'h-8 w-auto rounded-none',
                                          input: 'min-w-[2.75rem] [field-sizing:content]',
                                      }}
                                  />
                              ),
                      },
                  ]
                : []),
        ],
        [showDeliverColumn, data.received],
    );

    const itemRows = order.items;

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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {order.po_number}
                </h2>
            }
        >
            <Head title={order.po_number} />

            <div className="mx-auto max-w-7xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    <div className="p-6">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Customer</h3>
                        <p className="mt-2 font-medium text-gray-900">{order.customer.name}</p>
                        <dl className="mt-4 space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                            {order.has_attachment && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Attachment</dt>
                                    <dd>
                                        <Tooltip
                                            side="right"
                                            content={
                                                <div className="w-32">
                                                    {attachmentKind === 'image' ? (
                                                        <img
                                                            src={attachmentUrl}
                                                            alt=""
                                                            className="h-20 w-full rounded-lg object-cover"
                                                        />
                                                    ) : attachmentKind === 'pdf' ? (
                                                        <PdfPreview
                                                            url={attachmentUrl}
                                                            firstPageOnly
                                                            className="h-20 w-full overflow-hidden rounded-lg bg-muted"
                                                        />
                                                    ) : (
                                                        <div className="flex h-20 w-full items-center justify-center rounded-lg bg-muted">
                                                            <FileText className="h-8 w-8 text-muted-foreground" />
                                                        </div>
                                                    )}
                                                    <span className="block px-1 pb-0.5 pt-1 text-center text-[10px] font-medium text-muted-foreground">
                                                        {attachmentPreviewable ? 'Click to preview' : 'Click to open'}
                                                    </span>
                                                </div>
                                            }
                                        >
                                            {attachmentPreviewable ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setAttachmentPreviewOpen(true)}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    View File
                                                </button>
                                            ) : (
                                                <a
                                                    href={attachmentUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    View File
                                                </a>
                                            )}
                                        </Tooltip>
                                    </dd>
                                </div>
                            )}
                            <div className="flex gap-2">
                                <dt className="w-28 shrink-0 text-gray-500">Submitted</dt>
                                <dd className="text-gray-900">{formatDateTime(order.submitted_at)}</dd>
                            </div>
                            <div className="flex gap-2">
                                <dt className="w-28 shrink-0 text-gray-500">Last Updated</dt>
                                <dd className="text-gray-900">{formatDateTime(order.updated_at)}</dd>
                            </div>
                            {order.remarks && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Remarks</dt>
                                    <dd className="text-gray-900">{order.remarks}</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                    <div className="flex flex-wrap items-start justify-end gap-2 p-6">
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

                {attachmentPreviewable && (
                    <Modal
                        open={attachmentPreviewOpen}
                        onClose={() => setAttachmentPreviewOpen(false)}
                        title="Attachment preview"
                        maxWidth={900}
                        maxHeight="90vh"
                        className="[&>div:nth-child(2)]:px-2 [&>div:nth-child(2)]:pb-2"
                    >
                        {attachmentKind === 'image' ? (
                            <img
                                src={attachmentUrl}
                                alt="Attachment preview"
                                className="max-h-[78vh] w-full rounded-lg object-contain"
                            />
                        ) : (
                            <PdfPreview url={attachmentUrl} className="max-h-[78vh] overflow-auto" />
                        )}
                    </Modal>
                )}

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
                            className="border-gray-200 [&>div]:overflow-hidden"
                            height={autoTableHeight(itemRows.length)}
                            resizable
                            reorderable
                            emptyState="No products have been added to this order."
                        />
                        {showDeliverColumn && (
                            <div className="mt-3 flex justify-end">
                                <Button type="submit" variant="primary" size="compact" disabled={processing}>
                                    Settle
                                </Button>
                            </div>
                        )}
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
                        className="border-gray-200 [&>div]:overflow-hidden"
                        height={autoTableHeight(auditLogsRows.length)}
                        resizable
                        reorderable
                        emptyState="No updates yet. Order changes will appear here."
                    />
                </div>
            </div>

            <ConfirmationDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
                title={confirmationCopy[pendingAction]?.title}
                description={confirmationCopy[pendingAction]?.description}
                confirmLabel={confirmationCopy[pendingAction]?.confirmLabel}
                cancelLabel={confirmationCopy[pendingAction]?.cancelLabel}
                onConfirm={confirmPendingAction}
                destructive={confirmationCopy[pendingAction]?.destructive}
                processing={pendingAction === 'fulfillment' && processing}
            />
        </AuthenticatedLayout>
    );
}
