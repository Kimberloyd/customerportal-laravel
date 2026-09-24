import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CreateOrderModal from '@/components/CreateOrderModal';
import { Input } from '@/components/motion/input';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Tooltip } from '@/components/motion/tooltip';
import UpdateHistoryTable from '@/components/UpdateHistoryTable';
import { Button } from '@/components/ui/button';
import { NotificationBell } from '@/components/ui/notification-bell';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { PdfPreview } from '@/components/PdfPreview';
import ConfirmationDialog from '@/components/ConfirmationDialog';
import ProductReturnPanel from '@/components/ProductReturnPanel';
import RemarksTimeline from '@/components/RemarksTimeline';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, FileText, Minus, Plus } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { spring } from '@/lib/springs';

const TABLE_ROW_HEIGHT = 48;

function autoTableHeight(rowCount) {
    // A zero-row table doesn't render a normal 48px row -- it renders the
    // empty-state cell instead, which is much taller (p-10 padding around
    // its message). Sizing this case off TABLE_ROW_HEIGHT like the normal
    // rows do reserves too little height and clips that message.
    if (rowCount === 0) {
        return 160;
    }

    // No max-height cap: the trailing row can hold the Cancel/Edit/Deliver
    // actions, and capping height pushed that row into the table's own
    // internal scroll area, making it silently unreachable. maxHeight is
    // a ceiling, not a fixed size -- the container still shrinks to fit
    // shorter content -- so a full extra row of slack costs nothing
    // visually and absorbs any row that renders a bit taller than the
    // nominal rowHeight (borders, a stepper control, etc.) without
    // tipping the table into its own internal scroll.
    return (rowCount + 2) * TABLE_ROW_HEIGHT;
}

export default function Show({
    order,
    canManageFulfillment,
    canComplete,
    canCancel,
    canRequestReturn,
    canManageReturns,
    canRestore = false,
    canDeleteForever = false,
    editOrderCustomers = [],
    editOrderProducts,
    lockedCustomerId = null,
}) {
    usePurchaseOrderRealtime(order.id);

    const isCustomer = usePage().props.auth.user.role === 'customer';
    // The page's identity is always the transaction number -- po_number is
    // often not set yet (staff fill it in after the fact, see the inline
    // field below), so it can't double as the thing this page is titled by.
    const orderNumber = order.transaction_number;
    const showDeliverColumn = canManageFulfillment && !order.is_terminal;
    const currentStatus = statusBadge(order.display_status ?? order.status);
    const [attachmentPreviewOpen, setAttachmentPreviewOpen] = useState(false);
    const [restoreConfirmOpen, setRestoreConfirmOpen] = useState(false);
    const [isRestoring, setIsRestoring] = useState(false);
    const [deleteForeverConfirmOpen, setDeleteForeverConfirmOpen] = useState(false);
    const [isDeletingForever, setIsDeletingForever] = useState(false);
    const restoreOrder = useCallback(() => {
        router.post(route('purchase-orders.restore', order.public_id), {}, {
            preserveScroll: true,
            onStart: () => setIsRestoring(true),
            onFinish: () => { setIsRestoring(false); setRestoreConfirmOpen(false); },
        });
    }, [order.public_id]);
    const deleteOrderForever = useCallback(() => {
        router.delete(route('purchase-orders.force-destroy', order.public_id), {
            preserveScroll: true,
            onStart: () => setIsDeletingForever(true),
            onFinish: () => setIsDeletingForever(false),
        });
    }, [order.public_id]);
    const [pendingAction, setPendingAction] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);
    const [editOrderOpen, setEditOrderOpen] = useState(false);
    const [returnItemId, setReturnItemId] = useState(null);
    const [editProductsLoading, setEditProductsLoading] = useState(false);
    const [editProductsError, setEditProductsError] = useState(false);
    const remarksForm = useForm({ remarks: '' });
    const saveRemarks = () => {
        remarksForm.patch(route('purchase-orders.remarks.update', order.public_id), {
            preserveScroll: true,
            onSuccess: () => remarksForm.setData('remarks', ''),
        });
    };
    const attachmentUrl = order.has_attachment ? route('purchase-orders.attachment', order.public_id) : null;
    const attachmentKind = order.attachment_kind;
    const attachmentPreviewable = attachmentKind === 'image' || attachmentKind === 'pdf';
    const followUpLabels = {
        awaiting_fulfillment: 'Waiting for first delivery',
        stalled_partial: 'Partial delivery follow-up',
        awaiting_customer_close: 'Waiting for customer closure',
        return_review: 'Return waiting for review',
        return_receipt: 'Approved return still open',
    };
    const followUpColumns = useMemo(() => [
        { key: 'kind', header: 'Follow-up', cell: (followUp) => followUpLabels[followUp.kind] ?? followUp.kind },
        { key: 'level', header: 'Next step', cell: (followUp) => <span className="capitalize">{followUp.status === 'escalated' ? 'Escalated' : followUp.level}</span> },
        { key: 'next_due_at', header: 'Due', cell: (followUp) => followUp.next_due_at ? `Due ${formatDateTime(followUp.next_due_at)}` : '—' },
        { key: 'last_dispatched_at', header: 'Last sent', cell: (followUp) => followUp.last_dispatched_at ? `Sent ${formatDateTime(followUp.last_dispatched_at)}` : '—' },
    ], []);

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

        post(route('purchase-orders.receive', order.public_id), {
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

        router.post(route(routeName, order.public_id), {}, {
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

    // Shared by itemColumns' desktop in-table row and the mobile-only
    // duplicate rendered below the table -- same conditions, same buttons,
    // just two different places in the DOM depending on viewport width.
    const renderOrderActionButtons = () => (
        <>
            {canCancel && (
                <Button
                    type="button"
                    variant="destructive"
                    size="compact"
                    className="rounded-md"
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
                <Button type="submit" variant="primary" size="compact" className="rounded-md" disabled={processing}>
                    Deliver
                </Button>
            )}
            {canComplete && (
                <Button type="button" variant="primary" size="compact" className="rounded-md" onClick={complete}>
                    Close Order
                </Button>
            )}
            {canRestore && (
                <Button
                    type="button"
                    variant="tertiary"
                    size="compact"
                    className="rounded-md"
                    onClick={() => setRestoreConfirmOpen(true)}
                >
                    Restore
                </Button>
            )}
            {canDeleteForever && (
                <Button
                    type="button"
                    variant="destructive"
                    size="compact"
                    className="rounded-md"
                    onClick={() => setDeleteForeverConfirmOpen(true)}
                >
                    Delete forever
                </Button>
            )}
        </>
    );

    const itemColumns = useMemo(
        () => [
            {
                key: 'display_name',
                header: 'Product',
                cell: (item) => {
                    if (item.__isTotal) return null;

                    const product = [item.display_name, item.generic_name, item.dosage, item.unit]
                        .filter(Boolean)
                        .join(' ');

                    return <span title={product}>{product}</span>;
                },
            },
            {
                key: 'quantity',
                header: 'Quantity',
                align: 'right',
                cell: (item) => (item.__isTotal ? null : item.quantity),
            },
            {
                key: 'delivered_quantity',
                header: 'Delivered',
                align: 'right',
                spanRow: (item) => (item.__isTotal ? (showDeliverColumn ? 3 : 2) : undefined),
                cell: (item) =>
                    item.__isTotal ? (
                        // Hidden below md: this row lives inside a table that scrolls
                        // horizontally on narrow viewports, and CSS position:sticky
                        // doesn't reliably stick inside a real <table> across browsers
                        // -- confirmed broken, not just untried. The mobile-visible
                        // duplicate of these same buttons renders below the table
                        // instead (outside the scroll area entirely).
                        <div className="hidden flex-wrap justify-end gap-2 md:flex">
                            {renderOrderActionButtons()}
                        </div>
                    ) : (
                        item.delivered_quantity ?? 0
                    ),
            },
            {
                key: 'pending_quantity',
                header: 'Balance',
                align: 'right',
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
                                                  className="pointer-events-auto grid h-6 w-6 place-items-center rounded text-muted-foreground outline-none hover:bg-hover hover:text-foreground focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)] disabled:pointer-events-none disabled:opacity-40"
                                              >
                                                  <Minus className="h-3.5 w-3.5" />
                                              </button>
                                          }
                                          rightIcon={
                                              <button
                                                  type="button"
                                                  disabled={item.pending_quantity === 0 || numeric >= item.pending_quantity}
                                                  onClick={() => setQty(numeric + 1)}
                                                  className="grid h-6 w-6 place-items-center rounded text-muted-foreground outline-none hover:bg-hover hover:text-foreground focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)] disabled:pointer-events-none disabled:opacity-40"
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
                                          className="h-8 shrink-0 rounded px-2 text-xs font-medium text-primary outline-none hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)] disabled:pointer-events-none disabled:opacity-40"
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

    const showActionsRow = showDeliverColumn || canCancel || order.can_edit_items || canRequestReturn || canComplete || canRestore || canDeleteForever;

    const itemRows = order.items.length
        ? [...order.items, ...(showActionsRow ? [{ id: '__spacer', __isTotal: true }] : [])]
        : [];

    const [poNumberDraft, setPoNumberDraft] = useState(order.po_number ?? '');
    useEffect(() => {
        setPoNumberDraft(order.po_number ?? '');
    }, [order.po_number]);
    const [savingPoNumber, setSavingPoNumber] = useState(false);
    const poNumberDirty = poNumberDraft.trim() !== (order.po_number ?? '');
    const savePoNumber = useCallback(() => {
        router.patch(route('purchase-orders.po-number.update', order.public_id), {
            po_number: poNumberDraft.trim(),
        }, {
            preserveScroll: true,
            onStart: () => setSavingPoNumber(true),
            onFinish: () => setSavingPoNumber(false),
        });
    }, [order.public_id, poNumberDraft]);

    const [detailsCopied, setDetailsCopied] = useState(false);
    const copyOrderDetails = useCallback(async () => {
        const lines = [
            `Order ${orderNumber}`,
            `Customer: ${order.customer.name}`,
            `Status: ${currentStatus.label}`,
            `Submitted: ${formatDateTime(order.submitted_at)}`,
            `Last updated: ${formatDateTime(order.updated_at)}`,
            '',
            'Items:',
            ...order.items.map((item, index) => {
                const name = [item.display_name, item.generic_name, item.dosage, item.unit]
                    .filter(Boolean)
                    .join(' ');

                return `${index + 1}. ${name} — ${item.quantity}`;
            }),
            ...(order.remarks ? ['', `Remarks: ${order.remarks}`] : []),
            '',
            `Via ${window.location.hostname}`,
        ];

        try {
            await navigator.clipboard.writeText(lines.join('\n'));
            setDetailsCopied(true);
            setTimeout(() => setDetailsCopied(false), 2000);
        } catch {
            // Clipboard blocked (insecure context, denied permission): no
            // fallback exists worth building for what's a convenience action.
        }
    }, [order, currentStatus.label, orderNumber]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav aria-label="Breadcrumb">
                        <h2 className="flex items-center gap-2 text-xl font-semibold leading-tight">
                            {order.is_archived ? (
                                <>
                                    <Link
                                        href={route('purchase-orders.index')}
                                        className="text-muted-foreground transition-colors hover:text-primary"
                                    >
                                        Orders
                                    </Link>
                                    <span aria-hidden="true" className="text-muted-foreground">/</span>
                                    <Link
                                        href={route('purchase-orders.archive')}
                                        className="text-muted-foreground transition-colors hover:text-primary"
                                    >
                                        Archived
                                    </Link>
                                    <span aria-hidden="true" className="text-muted-foreground">/</span>
                                    <span aria-current="page" className="text-foreground">{orderNumber}</span>
                                </>
                            ) : (
                                <>
                                    <Link
                                        href={route('purchase-orders.index')}
                                        className="text-muted-foreground transition-colors hover:text-primary"
                                    >
                                        Order
                                    </Link>
                                    <span aria-hidden="true" className="text-muted-foreground">/</span>
                                    <span aria-current="page" className="text-foreground">{orderNumber}</span>
                                </>
                            )}
                        </h2>
                    </nav>
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {detailsCopied ? 'Copied' : 'Copy order details'}
                        </span>
                        <NotificationBell
                            count={0}
                            size={36}
                            label={detailsCopied ? 'Copied' : 'Copy order details'}
                            icon={
                                <AnimatePresence mode="wait" initial={false}>
                                    {detailsCopied ? (
                                        <motion.span
                                            key="check"
                                            initial={{ opacity: 0, scale: 0.6 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            exit={{ opacity: 0, scale: 0.8 }}
                                            transition={spring.fast}
                                            className="flex items-center justify-center"
                                        >
                                            <Check aria-hidden="true" className="h-5 w-5" strokeWidth={2} />
                                        </motion.span>
                                    ) : (
                                        <motion.span
                                            key="copy"
                                            initial={{ opacity: 0, scale: 0.6 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            exit={{ opacity: 0, scale: 0.8 }}
                                            transition={spring.fast}
                                            className="flex items-center justify-center"
                                        >
                                            <Copy aria-hidden="true" className="h-5 w-5" strokeWidth={1.5} />
                                        </motion.span>
                                    )}
                                </AnimatePresence>
                            }
                            onClick={copyOrderDetails}
                            className="bg-transparent border border-border hover:bg-hover"
                        />
                    </div>
                </div>
            }
        >
            <Head title={orderNumber} />

            <div className="mx-auto max-w-7xl space-y-10 px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-xl border border-border bg-card">
                    <div className="grid gap-6 p-6 md:grid-cols-2">
                        <div>
                        <p className="type-label uppercase tracking-wide text-muted-foreground">Customer</p>
                        <p className="mt-2 font-medium text-foreground">{order.customer.name}</p>
                        <dl className="mt-4 space-y-1.5 border-t border-border pt-4 text-sm">
                            {order.has_attachment && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-muted-foreground">Attachment</dt>
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
                                                    className="rounded text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)]"
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
                                <dt className="w-28 shrink-0 text-muted-foreground">Submitted</dt>
                                <dd className="text-foreground">{formatDateTime(order.submitted_at)}</dd>
                            </div>
                            <div className="flex gap-2">
                                <dt className="w-28 shrink-0 text-muted-foreground">Last Updated</dt>
                                <dd className="text-foreground">{formatDateTime(order.updated_at)}</dd>
                            </div>
                            {order.customer_received_at && (
                                <div className="flex gap-2">
                                    <dt className="w-28 shrink-0 text-muted-foreground">Order Received</dt>
                                    <dd className="font-medium text-success">
                                        {formatDateTime(order.customer_received_at)}
                                    </dd>
                                </div>
                            )}
                        </dl>
                        </div>

                        <div className="flex min-h-32 items-center justify-center border-t border-border pt-6 md:min-h-0 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            <AnimatedBadge
                                status={currentStatus.status}
                                size="md"
                                pulse={false}
                                icon={currentStatus.icon ? <currentStatus.icon className="h-4 w-4" /> : undefined}
                                className="border-0 bg-transparent px-0 text-2xl font-semibold shadow-none [&_svg]:!h-6 [&_svg]:!w-6"
                            >
                                {currentStatus.label}
                            </AnimatedBadge>
                        </div>
                    </div>
                </div>

                {!isCustomer && !order.is_archived && (
                    <div className="max-w-xs">
                        <Input
                            type="text"
                            label="PO Number"
                            value={poNumberDraft}
                            onChange={setPoNumberDraft}
                            onBlur={() => {
                                if (poNumberDirty) savePoNumber();
                            }}
                            placeholder="Not set"
                            disabled={savingPoNumber}
                            classNames={{ field: 'rounded-md' }}
                        />
                    </div>
                )}

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
                            <h3 className="type-section-heading text-foreground">Items and Fulfillment</h3>
                            <p className="mt-1 text-sm text-muted-foreground">Track ordered, delivered, and remaining quantities.</p>
                        </div>
                        {showDeliverColumn && (
                            <span className="self-end text-sm text-muted-foreground">Enter the quantity delivered in this batch.</span>
                        )}
                    </div>
                    <form onSubmit={submitFulfillment}>
                        <Table
                            data={itemRows}
                            columns={itemColumns}
                            getRowId={(item) => String(item.id)}
                            className="border-border"
                            height={autoTableHeight(itemRows.length)}
                            resizable
                            emptyState="No products have been added to this order."
                        />
                        {itemRows.length > 0 && (
                            <div className="mt-3 flex flex-wrap justify-end gap-2 md:hidden">
                                {renderOrderActionButtons()}
                            </div>
                        )}
                    </form>
                </div>

                <ProductReturnPanel
                    order={order}
                    canRequestReturn={canRequestReturn}
                    canManageReturns={canManageReturns}
                    openReturnItemId={returnItemId}
                    onOpenReturnItemHandled={() => setReturnItemId(null)}
                />

                {order.follow_ups?.length > 0 && (
                    <div>
                        <div className="mb-3">
                            <h3 className="type-section-heading text-foreground">Automatic follow-up</h3>
                            <p className="text-sm text-muted-foreground">Current reminder and escalation timing for this order.</p>
                        </div>
                        <Table
                            data={order.follow_ups}
                            columns={followUpColumns}
                            getRowId={(followUp) => String(followUp.id)}
                            className="border-border"
                            height={order.follow_ups.length * TABLE_ROW_HEIGHT + 60}
                        />
                    </div>
                )}

                <RemarksTimeline
                    order={order}
                    form={remarksForm}
                    onSave={saveRemarks}
                    canEdit={!order.is_terminal}
                />

                <div>
                    <div className="mb-3">
                        <h3 className="type-section-heading text-foreground">Update History</h3>
                        <p className="text-sm text-muted-foreground">Remarks and changes recorded by update time.</p>
                    </div>
                    <UpdateHistoryTable activities={order.audit_logs} />
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

            <ConfirmationDialog
                open={restoreConfirmOpen}
                onOpenChange={(open) => !open && !isRestoring && setRestoreConfirmOpen(false)}
                title={`Restore order ${orderNumber}?`}
                description="This puts the order back in the active Orders list."
                confirmLabel="Restore"
                cancelLabel="Cancel"
                onConfirm={restoreOrder}
                processing={isRestoring}
            />

            <ConfirmationDialog
                open={deleteForeverConfirmOpen}
                onOpenChange={(open) => !open && !isDeletingForever && setDeleteForeverConfirmOpen(false)}
                title={`Permanently delete order ${orderNumber}?`}
                description="This can't be undone. The order, its items, activity history, notifications, returns, and attachment are all erased for good."
                confirmLabel="Delete forever"
                cancelLabel="Keep archived"
                onConfirm={deleteOrderForever}
                confirmationText={orderNumber}
                destructive
                processing={isDeletingForever}
            />
        </AuthenticatedLayout>
    );
}
