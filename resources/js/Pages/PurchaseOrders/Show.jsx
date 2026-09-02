import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CreateOrderModal from '@/components/CreateOrderModal';
import { Input } from '@/components/motion/input';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Tooltip } from '@/components/motion/tooltip';
import { OrderActivityFeed } from '@/components/timelines-activity-feed';
import { Button } from '@/components/ui/button';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { PdfPreview } from '@/components/PdfPreview';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

const TABLE_ROW_HEIGHT = 48;
const TABLE_MAX_HEIGHT = 440;

function autoTableHeight(rowCount) {
    // A zero-row table doesn't render a normal 48px row -- it renders the
    // empty-state cell instead, which is much taller (p-10 padding around
    // its message). Sizing this case off TABLE_ROW_HEIGHT like the normal
    // rows do reserves too little height and clips that message.
    if (rowCount === 0) {
        return 160;
    }

    return Math.min(TABLE_MAX_HEIGHT, (rowCount + 1) * TABLE_ROW_HEIGHT);
}

export default function Show({
    order,
    canManageFulfillment,
    canComplete,
    canConfirmReceived,
    canCancel,
    editOrderCustomers = [],
    editOrderProducts,
    lockedCustomerId = null,
}) {
    usePurchaseOrderRealtime(order.id);

    const showDeliverColumn = canManageFulfillment && !order.is_terminal;
    const currentStatus = statusBadge(order.display_status ?? order.status);
    const [attachmentPreviewOpen, setAttachmentPreviewOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);
    const [editOrderOpen, setEditOrderOpen] = useState(false);
    const [editProductsLoading, setEditProductsLoading] = useState(false);
    const [editProductsError, setEditProductsError] = useState(false);
    const attachmentUrl = order.has_attachment ? route('purchase-orders.attachment', order.id) : null;
    const attachmentKind = order.attachment_kind;
    const attachmentPreviewable = attachmentKind === 'image' || attachmentKind === 'pdf';

    const loadEditOrderProducts = useCallback(() => {
        router.reload({
            only: ['editOrderProducts'],
            preserveScroll: true,
            onStart: () => setEditProductsLoading(true),
            onSuccess: (page) => setEditProductsError(page.props.editOrderProducts === undefined),
            onError: () => setEditProductsError(true),
            onFinish: () => setEditProductsLoading(false),
        });
    }, []);

    useEffect(() => {
        if (editOrderOpen) setEditProductsError(false);
    }, [editOrderOpen]);

    useEffect(() => {
        if (
            !editOrderOpen
            || !order.can_edit_items
            || editOrderProducts !== undefined
            || editProductsLoading
            || editProductsError
        ) return;

        loadEditOrderProducts();
    }, [
        editOrderOpen,
        editOrderProducts,
        editProductsError,
        editProductsLoading,
        loadEditOrderProducts,
        order.can_edit_items,
    ]);

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

    const confirmReceived = () => {
        setPendingAction('received');
    };

    const confirmPendingAction = () => {
        if (pendingAction === 'fulfillment') {
            confirmFulfillment();
            return;
        }

        const routeName = {
            complete: 'purchase-orders.complete',
            cancel: 'purchase-orders.cancel',
            received: 'purchase-orders.confirm-received',
        }[pendingAction];

        if (!routeName) return;

        router.post(route(routeName, order.id), {}, {
            onStart: () => setActionProcessing(true),
            onFinish: () => {
                setActionProcessing(false);
                setPendingAction(null);
            },
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
        received: {
            title: 'Mark this order as received?',
            description: `Confirm that all items in order ${order.po_number} have arrived. This confirmation cannot be undone.`,
            confirmLabel: 'Mark as received',
            cancelLabel: 'Not yet',
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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav aria-label="Breadcrumb">
                        <h2 className="flex items-center gap-2 text-xl font-semibold leading-tight">
                            <Link
                                href={route('purchase-orders.index')}
                                className="text-gray-500 transition-colors hover:text-primary"
                            >
                                Order
                            </Link>
                            <span aria-hidden="true" className="text-gray-400">/</span>
                            <span aria-current="page" className="text-gray-800">{order.po_number}</span>
                        </h2>
                    </nav>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {canConfirmReceived && (
                            <Button
                                variant="primary"
                                onClick={confirmReceived}
                            >
                                Order Received
                            </Button>
                        )}
                        {canCancel && (
                            <Button
                                variant="tertiary"
                                className="text-red-600 hover:text-red-700"
                                onClick={cancel}
                            >
                                Cancel Order
                            </Button>
                        )}
                        {canComplete && (
                            <Button
                                variant="tertiary"
                                className="text-green-700 hover:text-green-800"
                                onClick={complete}
                            >
                                Mark Completed
                            </Button>
                        )}
                        {order.can_edit_items && (
                            <Button
                                type="button"
                                variant="tertiary"
                                onClick={() => setEditOrderOpen(true)}
                            >
                                Edit Order
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={order.po_number} />

            <div className="mx-auto max-w-7xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-xl border border-gray-200 bg-white">
                    <div className="grid gap-6 p-6 md:grid-cols-2">
                        <div>
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
                                                    className="text-primary hover:underline"
                                                >
                                                    View File
                                                </button>
                                            ) : (
                                                <a
                                                    href={attachmentUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary hover:underline"
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
                            {order.customer_received_at && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Order Received</dt>
                                    <dd className="font-medium text-green-700">
                                        {formatDateTime(order.customer_received_at)}
                                    </dd>
                                </div>
                            )}
                            {order.remarks && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-gray-500">Remarks</dt>
                                    <dd className="text-gray-900">{order.remarks}</dd>
                                </div>
                            )}
                        </dl>
                        </div>

                        <div className="flex min-h-32 items-center justify-center border-t border-gray-100 pt-6 md:min-h-0 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            <AnimatedBadge
                                status={currentStatus.status}
                                size="md"
                                pulse={currentStatus.pulse ?? false}
                                className="border-0 bg-transparent px-0 text-2xl font-semibold shadow-none [&_svg]:!h-6 [&_svg]:!w-6"
                            >
                                {currentStatus.label}
                            </AnimatedBadge>
                        </div>
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
                        <AutoHeightReveal>
                            {attachmentKind === 'image' ? (
                                <img
                                    src={attachmentUrl}
                                    alt="Attachment preview"
                                    className="max-h-[78vh] w-full rounded-lg object-contain"
                                />
                            ) : (
                                <PdfPreview url={attachmentUrl} className="max-h-[78vh] overflow-auto" />
                            )}
                        </AutoHeightReveal>
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
                    <OrderActivityFeed activities={order.audit_logs} />
                </div>
            </div>

            <CreateOrderModal
                open={editOrderOpen}
                onOpenChange={setEditOrderOpen}
                customers={editOrderCustomers}
                products={editOrderProducts ?? []}
                productsLoading={editProductsLoading || (
                    order.can_edit_items
                    && editOrderProducts === undefined
                    && !editProductsError
                )}
                productsError={editProductsError}
                onRetryProducts={loadEditOrderProducts}
                lockedCustomerId={lockedCustomerId}
                initialOrder={order}
            />

            <ConfirmationDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
                title={confirmationCopy[pendingAction]?.title}
                description={confirmationCopy[pendingAction]?.description}
                confirmLabel={confirmationCopy[pendingAction]?.confirmLabel}
                cancelLabel={confirmationCopy[pendingAction]?.cancelLabel}
                onConfirm={confirmPendingAction}
                destructive={confirmationCopy[pendingAction]?.destructive}
                processing={(pendingAction === 'fulfillment' && processing) || actionProcessing}
            />
        </AuthenticatedLayout>
    );
}
