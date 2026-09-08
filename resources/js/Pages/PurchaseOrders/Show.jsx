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
import ProductReturnPanel from '@/components/ProductReturnPanel';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Minus, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

const TABLE_ROW_HEIGHT = 48;

function autoTableHeight(rowCount) {
    // A zero-row table doesn't render a normal 48px row -- it renders the
    // empty-state cell instead, which is much taller (p-10 padding around
    // its message). Sizing this case off TABLE_ROW_HEIGHT like the normal
    // rows do reserves too little height and clips that message.
    if (rowCount === 0) {
        return 160;
    }

    // No max-height cap: the trailing row can hold the Cancel/Edit/Settle
    // actions, and capping height pushed that row into the table's own
    // internal scroll area, making it silently unreachable.
    return (rowCount + 1) * TABLE_ROW_HEIGHT;
}

export default function Show({
    order,
    canManageFulfillment,
    canComplete,
    canCancel,
    canRequestReturn,
    canManageReturns,
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
    const [returnItemId, setReturnItemId] = useState(null);
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

    const confirmPendingAction = () => {
        if (pendingAction === 'fulfillment') {
            confirmFulfillment();
            return;
        }

        const routeName = {
            complete: 'purchase-orders.complete',
            cancel: 'purchase-orders.cancel',
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
            title: 'Close this order?',
            description: 'This closes the fully delivered order. This cannot be undone.',
            confirmLabel: 'Close order',
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
                cell: (item) => {
                    if (item.__isTotal) return null;

                    const product = [item.display_name, item.generic_name, item.dosage]
                        .filter(Boolean)
                        .join(' ');

                    return <span title={product}>{product}</span>;
                },
            },
            {
                key: 'quantity',
                header: 'Ordered',
                cell: (item) => (item.__isTotal ? null : item.quantity),
            },
            {
                key: 'delivered_quantity',
                header: 'Delivered',
                spanRow: (item) => (item.__isTotal ? (showDeliverColumn ? 3 : 2) : undefined),
                cell: (item) =>
                    item.__isTotal ? (
                        <div className="flex flex-wrap justify-end gap-2">
                            {canCancel && (
                                <Button
                                    type="button"
                                    variant="tertiary"
                                    size="compact"
                                    className="rounded-md text-red-600 hover:text-red-700"
                                    onClick={cancel}
                                >
                                    Cancel
                                </Button>
                            )}
                            {order.can_edit_items && (
                                <Button
                                    type="button"
                                    variant="tertiary"
                                    size="compact"
                                    className="rounded-md"
                                    onClick={() => setEditOrderOpen(true)}
                                >
                                    Edit
                                </Button>
                            )}
                            {canRequestReturn && (
                                <Button
                                    type="button"
                                    variant="warning"
                                    size="compact"
                                    className="rounded-md"
                                    onClick={() => setReturnItemId('blank')}
                                >
                                    Return
                                </Button>
                            )}
                            {showDeliverColumn && (
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="compact"
                                    className="rounded-md"
                                    disabled={processing}
                                >
                                    Settle
                                </Button>
                            )}
                            {canComplete && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    size="compact"
                                    className="rounded-md"
                                    onClick={complete}
                                >
                                    Close Order
                                </Button>
                            )}
                        </div>
                    ) : (
                        item.delivered_quantity ?? 0
                    ),
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
                          cell: (item) => {
                              if (item.__isTotal) return null;

                              const raw = data.received[item.id];
                              const numeric = raw === '' || raw == null ? 0 : Number(raw);
                              const setQty = (next) => {
                                  const clamped = Math.max(0, Math.min(item.pending_quantity, next));
                                  setData('received', {
                                      ...data.received,
                                      [item.id]: clamped,
                                  });
                              };

                              return (
                                  <div className="flex items-center gap-1">
                                      <Input
                                          type="number"
                                          min={0}
                                          max={item.pending_quantity}
                                          disabled={item.pending_quantity === 0}
                                          value={data.received[item.id]}
                                          onChange={(value) => {
                                              const clamped =
                                                  value === ''
                                                      ? ''
                                                      : Math.max(0, Math.min(item.pending_quantity, Number(value)));
                                              setData('received', {
                                                  ...data.received,
                                                  [item.id]: clamped,
                                              });
                                          }}
                                          leftIcon={
                                              <button
                                                  type="button"
                                                  disabled={item.pending_quantity === 0 || numeric <= 0}
                                                  onClick={() => setQty(numeric - 1)}
                                                  className="pointer-events-auto grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                              >
                                                  <Minus className="h-3.5 w-3.5" />
                                              </button>
                                          }
                                          rightIcon={
                                              <button
                                                  type="button"
                                                  disabled={item.pending_quantity === 0 || numeric >= item.pending_quantity}
                                                  onClick={() => setQty(numeric + 1)}
                                                  className="grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                              >
                                                  <Plus className="h-3.5 w-3.5" />
                                              </button>
                                          }
                                          classNames={{
                                              root: 'flex-1',
                                              field: 'h-8 w-auto rounded-none',
                                              input: 'min-w-[1.5rem] text-center [field-sizing:content] [appearance:textfield] [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                                              leftIcon: 'pointer-events-auto left-0.5',
                                              rightIcon: 'right-0.5 [&_button]:size-6',
                                          }}
                                      />
                                      <button
                                          type="button"
                                          disabled={item.pending_quantity === 0 || numeric >= item.pending_quantity}
                                          onClick={() => setQty(item.pending_quantity)}
                                          className="h-8 shrink-0 rounded px-2 text-xs font-medium text-primary hover:bg-primary/10 disabled:pointer-events-none disabled:opacity-40"
                                      >
                                          Max
                                      </button>
                                  </div>
                              );
                          },
                      },
                  ]
                : []),
        ],
        [showDeliverColumn, data.received, processing, canCancel, order.can_edit_items, canRequestReturn, canComplete, complete],
    );

    const showActionsRow = showDeliverColumn || canCancel || order.can_edit_items || canRequestReturn || canComplete;

    const itemRows = order.items.length
        ? [...order.items, ...(showActionsRow ? [{ id: '__spacer', __isTotal: true }] : [])]
        : [];

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
                </div>
            }
        >
            <Head title={order.po_number} />

            <div className="mx-auto max-w-7xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-xl border border-gray-200 bg-white">
                    <div className="grid gap-6 p-6 md:grid-cols-2">
                        <div>
                        <p className="type-label uppercase tracking-wide text-muted-foreground">Customer</p>
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
                        </dl>
                        </div>

                        <div className="flex min-h-32 items-center justify-center border-t border-gray-100 pt-6 md:min-h-0 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            <AnimatedBadge
                                status={currentStatus.status}
                                size="md"
                                pulse={false}
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
                    <div className="mb-3 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Items and Fulfillment</h3>
                            <p className="mt-1 text-sm text-gray-500">Track ordered, delivered, and remaining quantities.</p>
                        </div>
                        {showDeliverColumn && (
                            <span className="self-end text-sm text-gray-500">Enter the quantity delivered in this batch.</span>
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
                    </form>
                </div>

                <ProductReturnPanel
                    order={order}
                    canRequestReturn={canRequestReturn}
                    canManageReturns={canManageReturns}
                    openReturnItemId={returnItemId}
                    onOpenReturnItemHandled={() => setReturnItemId(null)}
                />

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
