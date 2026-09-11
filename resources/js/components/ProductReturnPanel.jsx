import { Button } from '@/components/ui/button';
import { AttachmentUpload, ImagePreviewDialog } from '@/components/motion/attachment-upload';
import { Dropdown } from '@/components/interior/dropdown';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Input } from '@/components/motion/input';
import { Table } from '@/components/motion/table';
import { formatDateTime } from '@/utils/orderDisplay';
import { useFieldValidation } from '@/hooks/useFieldValidation';
import { router, useForm } from '@inertiajs/react';
import { Check, ImageIcon, Minus, MoreHorizontal, PackageCheck, Plus, X } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const reasonRules = {
    reason: (value) => {
        const trimmed = (value ?? '').trim();
        if (!trimmed) return 'Describe the reason for the return.';
        return trimmed.length >= 10 ? null : 'Describe the reason for the return in at least 10 characters.';
    },
};

const RETURN_STATUS_COPY = {
    requested: { label: 'Awaiting review', status: 'warning' },
    approved: { label: 'Approved', status: 'info' },
    rejected: { label: 'Not approved', status: 'danger' },
    received: { label: 'Received', status: 'success' },
};

function returnStatusBadge(status) {
    return RETURN_STATUS_COPY[status] ?? { label: status, status: 'neutral' };
}

const TABLE_ROW_HEIGHT = 48;
const TABLE_MAX_HEIGHT = 440;

function autoTableHeight(rowCount) {
    return rowCount === 0
        ? 160
        : Math.min(TABLE_MAX_HEIGHT, (rowCount + 1) * TABLE_ROW_HEIGHT);
}

function RequestReturnModal({ open, onClose, order, presetItemId }) {
    const wasOpenRef = useRef(false);
    const returnableItems = useMemo(
        () => order.items.filter((item) => item.returnable_quantity > 0),
        [order.items],
    );
    const [attachmentItems, setAttachmentItems] = useState([]);
    const [attachmentError, setAttachmentError] = useState('');
    const validation = useFieldValidation(reasonRules);
    const { data, setData, post, processing, errors, clearErrors, transform } = useForm({
        reason: '',
        items: [],
        return_images: [],
    });
    const serverAttachmentError = errors.return_images
        ?? Object.entries(errors).find(([key]) => key.startsWith('return_images.'))?.[1];

    transform((formData) => ({
        ...formData,
        items: formData.items.filter((line) => Number(line.quantity) > 0),
    }));

    useEffect(() => {
        if (!open) {
            wasOpenRef.current = false;
            return;
        }
        if (wasOpenRef.current) return;
        wasOpenRef.current = true;

        setData({
            reason: '',
            items: returnableItems.map((item) => ({
                purchase_order_item_id: item.id,
                quantity: item.id === presetItemId ? item.returnable_quantity : 0,
            })),
            return_images: [],
        });
        setAttachmentItems([]);
        setAttachmentError('');
        clearErrors();
        validation.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, presetItemId, returnableItems]);

    const updateQuantity = (itemId, raw) => {
        const item = returnableItems.find((candidate) => candidate.id === itemId);
        const quantity = raw === '' ? 0 : Math.max(0, Math.min(item.returnable_quantity, Number(raw)));
        clearErrors('return_request');
        setData('items', data.items.map((line) => (
            line.purchase_order_item_id === itemId ? { ...line, quantity } : line
        )));
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('purchase-orders.returns.store', order.public_id), {
            onSuccess: (page) => {
                if (!page.props.flash?.error) onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Request a product return"
            description="Select delivered products to return."
            maxWidth={840}
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            footer={
                <>
                    <Button
                        type="button"
                        variant="tertiary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="return-request-form"
                        className="h-10 rounded-md px-5 text-sm"
                        loading={processing}
                    >
                        Send request
                    </Button>
                </>
            }
        >
            <AutoHeightReveal>
                <form id="return-request-form" onSubmit={submit} className="space-y-5 px-2 pt-2">
                    {errors.return_request && (
                        <div
                            role="alert"
                            className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                        >
                            {errors.return_request}
                        </div>
                    )}
                    <div className="overflow-hidden rounded-lg border border-border">
                        {returnableItems.map((item) => {
                            const line = data.items.find((candidate) => candidate.purchase_order_item_id === item.id);
                            const quantity = Number(line?.quantity) || 0;
                            const product = [item.display_name, item.generic_name, item.dosage]
                                .filter(Boolean)
                                .join(' ');
                            return (
                                <div key={item.id} className="grid grid-cols-[1fr_auto] items-center gap-4 border-b border-border px-4 py-3 last:border-b-0">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-foreground" title={product}>{product}</p>
                                        <p className="text-sm text-muted-foreground">Up to {item.returnable_quantity} delivered unit(s) can be returned.</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <label className="text-sm font-medium text-foreground">
                                            <span className="sr-only">Return quantity for {product}</span>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={item.returnable_quantity}
                                                value={line?.quantity ?? 0}
                                                onChange={(value) => updateQuantity(item.id, value)}
                                                leftIcon={
                                                    <button
                                                        type="button"
                                                        disabled={quantity <= 0}
                                                        onClick={() => updateQuantity(item.id, String(Math.max(0, quantity - 1)))}
                                                        aria-label={`Decrease return quantity for ${product}`}
                                                        className="pointer-events-auto grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                                    >
                                                        <Minus className="h-3.5 w-3.5" />
                                                    </button>
                                                }
                                                rightIcon={
                                                    <button
                                                        type="button"
                                                        disabled={quantity >= item.returnable_quantity}
                                                        onClick={() => updateQuantity(item.id, String(Math.min(item.returnable_quantity, quantity + 1)))}
                                                        aria-label={`Increase return quantity for ${product}`}
                                                        className="grid h-6 w-6 place-items-center rounded text-muted-foreground hover:bg-gray-100 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                    </button>
                                                }
                                                classNames={{
                                                    field: 'h-9 w-auto rounded-md',
                                                    input: 'w-20 min-w-[5rem] !pl-6 !pr-6 text-center [appearance:textfield] [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                                                    leftIcon: 'pointer-events-auto left-0.5',
                                                    rightIcon: 'right-0.5 [&_button]:size-6',
                                                }}
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            disabled={quantity >= item.returnable_quantity}
                                            onClick={() => updateQuantity(item.id, String(item.returnable_quantity))}
                                            className="h-9 shrink-0 rounded px-2 text-xs font-medium text-primary hover:bg-primary/10 disabled:pointer-events-none disabled:opacity-40"
                                        >
                                            Max
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <label className="block space-y-2 text-sm font-medium text-foreground">
                        <span>Reason for return</span>
                        <textarea
                            value={data.reason}
                            onChange={(event) => {
                                setData('reason', event.target.value);
                                clearErrors('return_request');
                                validation.onChange('reason', event.target.value, data);
                            }}
                            onBlur={() => validation.onBlur('reason', data.reason, data)}
                            minLength={10}
                            maxLength={1000}
                            required
                            rows={4}
                            placeholder="Describe the issue with the delivered products."
                            className={`w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 ${
                                validation.clientErrors.reason
                                    ? 'border-destructive focus-visible:ring-destructive/25'
                                    : 'border-input focus-visible:ring-ring'
                            }`}
                        />
                        {validation.clientErrors.reason ? (
                            <span className="block text-xs font-normal text-destructive" role="alert">{validation.clientErrors.reason}</span>
                        ) : validation.validFields.reason ? (
                            <span className="flex items-center gap-1 text-xs font-normal text-emerald-600">
                                <Check aria-hidden="true" className="h-3.5 w-3.5" /> Looks good.
                            </span>
                        ) : (
                            <span className="block text-xs font-normal text-muted-foreground">Minimum 10 characters. Do not include patient information.</span>
                        )}
                    </label>
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Image attachments <span className="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <AttachmentUpload
                            value={attachmentItems}
                            onValueChange={(items) => {
                                const files = items.map((item) => item.file).filter(Boolean);
                                const allowed = files.every((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));

                                if (!allowed) {
                                    setAttachmentItems([]);
                                    setData('return_images', []);
                                    setAttachmentError('Choose JPG, PNG, or WebP images.');
                                    return;
                                }

                                setAttachmentItems(items);
                                setData('return_images', files);
                                setAttachmentError('');
                                clearErrors(
                                    'return_images',
                                    ...Object.keys(errors).filter((key) => key.startsWith('return_images.')),
                                );
                            }}
                            onFilesRejected={(files, reason) => {
                                if (reason === 'too-large') {
                                    setAttachmentError('Each image must be smaller than 5 MB.');
                                } else if (reason === 'max-files') {
                                    setAttachmentError('Add no more than 5 images.');
                                }
                            }}
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            multiple
                            maxFiles={5}
                            maxFileSize={5 * 1024 * 1024}
                            title="Add photos"
                            description="Up to 5 JPG, PNG, or WebP images — 5 MB each"
                            attachmentsLabel="Return images"
                            classNames={{ dropzone: 'min-h-36' }}
                        />
                        {(serverAttachmentError || attachmentError) && (
                            <p role="alert" className="mt-2 text-sm text-destructive">
                                {serverAttachmentError ?? attachmentError}
                            </p>
                        )}
                    </div>
                </form>
            </AutoHeightReveal>
        </Modal>
    );
}

function ReviewReturnModal({ action, onClose }) {
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);
    const isRejecting = action?.status === 'rejected';
    const isReceiving = action?.status === 'received';

    useEffect(() => setNote(''), [action]);

    if (!action) return null;

    const title = isReceiving ? 'Record returned products' : isRejecting ? 'Decline return request' : 'Approve return request';
    const description = isReceiving
        ? 'Confirm that the approved returned products are now with your team.'
        : isRejecting
            ? 'Explain why this request cannot be approved. The customer will see this note.'
            : 'Approve the request so your team can arrange collection or delivery.';
    const confirmLabel = isReceiving ? 'Record as received' : isRejecting ? 'Decline request' : 'Approve request';

    const submit = (event) => {
        event.preventDefault();
        router.put(route('purchase-orders.returns.update', action.returnRequest.public_id), {
            status: action.status,
            review_note: note,
        }, {
            onStart: () => setProcessing(true),
            onSuccess: (page) => {
                if (!page.props.flash?.error) onClose();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Modal
            open
            onClose={onClose}
            title={title}
            description={description}
            maxWidth={560}
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            footer={
                <>
                    <Button
                        type="button"
                        variant="tertiary"
                        className="h-10 rounded-md px-5 text-sm"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="review-return-form"
                        variant={isRejecting ? 'destructive' : 'primary'}
                        className="h-10 rounded-md px-5 text-sm"
                        loading={processing}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <AutoHeightReveal>
                <form id="review-return-form" onSubmit={submit} className="space-y-4 px-2 pt-2">
                    <div className="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                        {action.returnRequest.items.map((item) => `${item.display_name}: ${item.quantity} unit(s)`).join(' · ')}
                    </div>
                    <label className="block space-y-2 text-sm font-medium text-foreground">
                        <span>{isRejecting ? 'Reason for declining' : 'Staff note (optional)'}</span>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            required={isRejecting}
                            maxLength={1000}
                            rows={4}
                            placeholder={isRejecting ? 'Explain the decision clearly.' : 'Add collection or receiving details if helpful.'}
                            className="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </label>
                </form>
            </AutoHeightReveal>
        </Modal>
    );
}

export default function ProductReturnPanel({
    order,
    canRequestReturn,
    canManageReturns,
    openReturnItemId = null,
    onOpenReturnItemHandled,
}) {
    const [requestOpen, setRequestOpen] = useState(false);
    const [presetItemId, setPresetItemId] = useState(null);
    const [action, setAction] = useState(null);
    const [previewItem, setPreviewItem] = useState(null);
    const reduceMotion = useReducedMotion();
    const returns = order.returns ?? [];

    useEffect(() => {
        if (openReturnItemId == null) return;
        setPresetItemId(openReturnItemId === 'blank' ? null : openReturnItemId);
        setRequestOpen(true);
        onOpenReturnItemHandled?.();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [openReturnItemId]);
    const columns = useMemo(() => [
        {
            key: 'items',
            header: 'Products',
            cell: (returnRequest) => {
                const products = returnRequest.items
                    .map((item) => {
                        const product = [item.display_name, item.generic_name, item.dosage]
                            .filter(Boolean)
                            .join(' ');

                        return `${product} × ${item.quantity}`;
                    })
                    .join(', ');

                return <span title={products}>{products}</span>;
            },
        },
        {
            key: 'reason',
            header: 'Reason',
            cell: (returnRequest) => <span title={returnRequest.reason}>{returnRequest.reason}</span>,
        },
        {
            key: 'attachments',
            header: 'Attachment',
            width: '160px',
            cell: (returnRequest) => {
                const attachments = returnRequest.attachment_urls ?? [];

                if (attachments.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="space-y-1">
                        {attachments.map((url, index) => (
                            <button
                                type="button"
                                key={url}
                                onClick={() => setPreviewItem({
                                    id: `return-image-${returnRequest.id}-${index}`,
                                    name: `Return image ${index + 1}`,
                                    kind: 'image',
                                    href: url,
                                })}
                                className="flex w-fit items-center gap-1 text-sm font-medium text-primary hover:underline"
                            >
                                <ImageIcon className="h-4 w-4" />
                                Image {index + 1}
                            </button>
                        ))}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'Status',
            width: '150px',
            cell: (returnRequest) => {
                const badge = returnStatusBadge(returnRequest.status);
                return (
                    <div className="flex justify-start">
                        <AnimatedBadge
                            status={badge.status}
                            size="sm"
                            pulse={false}
                            className="border-0 bg-transparent px-0 shadow-none"
                        >
                            {badge.label}
                        </AnimatedBadge>
                    </div>
                );
            },
        },
        {
            key: 'requested_at',
            header: 'Requested',
            width: '190px',
            cell: (returnRequest) => formatDateTime(returnRequest.requested_at),
        },
        {
            key: 'details',
            header: 'Resolution',
            width: '240px',
            cell: (returnRequest) => {
                const summary = returnRequest.review_note || '—';

                return <span className="text-muted-foreground" title={summary}>{summary}</span>;
            },
        },
        ...(canManageReturns
            ? [{
                key: 'actions',
                header: '',
                width: '56px',
                cell: (returnRequest) => {
                    const items = [];

                    if (returnRequest.status === 'requested') {
                        items.push({
                            value: 'approve',
                            label: 'Approve',
                            icon: <Check />,
                            onSelect: () => setAction({ returnRequest, status: 'approved' }),
                        });
                        items.push({
                            value: 'decline',
                            label: 'Decline',
                            icon: <X />,
                            onSelect: () => setAction({ returnRequest, status: 'rejected' }),
                            destructive: true,
                        });
                    } else if (returnRequest.status === 'approved') {
                        items.push({
                            value: 'received',
                            label: 'Record received',
                            icon: <PackageCheck />,
                            onSelect: () => setAction({ returnRequest, status: 'received' }),
                        });
                    }

                    if (items.length === 0) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    return (
                        <div className="flex items-center">
                            <Dropdown
                                items={items}
                                value=""
                                onChange={(action) => items.find((item) => item.value === action)?.onSelect()}
                                label={`Actions for return request ${returnRequest.id}`}
                                trigger={<MoreHorizontal />}
                                align="right"
                                portal
                                triggerClassName="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [&_svg]:h-5 [&_svg]:w-5"
                            />
                        </div>
                    );
                },
            }]
            : []),
    ], [canManageReturns]);

    return (
        <section>
            <div className="mb-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="type-section-heading text-foreground">Product returns</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {canManageReturns
                            ? 'Review customer return requests and record products once received.'
                            : 'Return requests are available anytime after a product has been delivered.'}
                    </p>
                </div>
            </div>

            <Table
                data={returns}
                columns={columns}
                getRowId={(returnRequest) => String(returnRequest.id)}
                className="border-gray-200 [&>div]:overflow-hidden"
                height={autoTableHeight(returns.length)}
                resizable
                emptyState="No return requests for this order."
            />

            <RequestReturnModal
                open={requestOpen}
                onClose={() => setRequestOpen(false)}
                order={order}
                presetItemId={presetItemId}
            />
            <ReviewReturnModal action={action} onClose={() => setAction(null)} />
            <ImagePreviewDialog
                item={previewItem}
                onClose={() => setPreviewItem(null)}
                reduce={Boolean(reduceMotion)}
            />
        </section>
    );
}
