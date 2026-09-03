import { Button } from '@/components/ui/button';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { formatDateTime } from '@/utils/orderDisplay';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const STATUS_COPY = {
    requested: { label: 'Awaiting review', className: 'bg-amber-50 text-amber-800 ring-amber-200' },
    approved: { label: 'Approved', className: 'bg-blue-50 text-blue-800 ring-blue-200' },
    rejected: { label: 'Not approved', className: 'bg-red-50 text-red-800 ring-red-200' },
    received: { label: 'Received', className: 'bg-green-50 text-green-800 ring-green-200' },
};

function StatusPill({ status }) {
    const copy = STATUS_COPY[status] ?? { label: status, className: 'bg-gray-50 text-gray-700 ring-gray-200' };

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${copy.className}`}>
            {copy.label}
        </span>
    );
}

function RequestReturnModal({ open, onClose, order, returnPolicy }) {
    const returnableItems = useMemo(
        () => order.items.filter((item) => item.returnable_quantity > 0),
        [order.items],
    );
    const { data, setData, post, processing, reset } = useForm({
        reason: '',
        items: [],
    });

    useEffect(() => {
        if (!open) return;
        reset('reason');
        setData('items', returnableItems.map((item) => ({ purchase_order_item_id: item.id, quantity: 0 })));
    }, [open, reset, returnableItems, setData]);

    const updateQuantity = (itemId, raw) => {
        const item = returnableItems.find((candidate) => candidate.id === itemId);
        const quantity = raw === '' ? 0 : Math.max(0, Math.min(item.returnable_quantity, Number(raw)));
        setData('items', data.items.map((line) => (
            line.purchase_order_item_id === itemId ? { ...line, quantity } : line
        )));
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('purchase-orders.returns.store', order.id), {
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
            description={`Select delivered products to return. Requests must be made within ${returnPolicy.window_days} days after receipt.`}
            maxWidth={620}
            closeOnBackdrop={!processing}
            closeOnEscape={!processing}
            footer={
                <>
                    <Button type="button" variant="tertiary" onClick={onClose} disabled={processing}>Cancel</Button>
                    <Button type="submit" form="return-request-form" loading={processing}>Send request</Button>
                </>
            }
        >
            <AutoHeightReveal>
                <form id="return-request-form" onSubmit={submit} className="space-y-5 px-2 pt-2">
                    <div className="overflow-hidden rounded-lg border border-border">
                        {returnableItems.map((item) => {
                            const line = data.items.find((candidate) => candidate.purchase_order_item_id === item.id);
                            return (
                                <div key={item.id} className="grid grid-cols-[1fr_96px] items-center gap-4 border-b border-border px-4 py-3 last:border-b-0">
                                    <div>
                                        <p className="font-medium text-foreground">{item.display_name}</p>
                                        <p className="text-sm text-muted-foreground">Up to {item.returnable_quantity} delivered unit(s) can be returned.</p>
                                    </div>
                                    <label className="space-y-1 text-sm font-medium text-foreground">
                                        <span className="sr-only">Return quantity for {item.display_name}</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max={item.returnable_quantity}
                                            value={line?.quantity ?? 0}
                                            onChange={(event) => updateQuantity(item.id, event.target.value)}
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                    <label className="block space-y-2 text-sm font-medium text-foreground">
                        <span>Reason for return</span>
                        <textarea
                            value={data.reason}
                            onChange={(event) => setData('reason', event.target.value)}
                            minLength={10}
                            maxLength={1000}
                            required
                            rows={4}
                            placeholder="Describe the issue with the delivered products."
                            className="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                        <span className="block text-xs font-normal text-muted-foreground">Minimum 10 characters. Do not include patient information.</span>
                    </label>
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
        router.put(route('purchase-orders.returns.update', action.returnRequest.id), {
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
                    <Button type="button" variant="tertiary" onClick={onClose} disabled={processing}>Cancel</Button>
                    <Button type="submit" form="review-return-form" variant={isRejecting ? 'destructive' : 'primary'} loading={processing}>
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

export default function ProductReturnPanel({ order, canRequestReturn, canManageReturns, returnPolicy }) {
    const [requestOpen, setRequestOpen] = useState(false);
    const [action, setAction] = useState(null);
    const returns = order.returns ?? [];

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-border px-6 py-5">
                <div>
                    <h3 className="type-section-heading text-foreground">Product returns</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {canManageReturns
                            ? 'Review customer return requests and record products once received.'
                            : `Return requests are available for ${returnPolicy.window_days} days after delivery is confirmed.`}
                    </p>
                </div>
                {canRequestReturn && <Button onClick={() => setRequestOpen(true)}>Request return</Button>}
            </div>

            {returns.length === 0 ? (
                <p className="px-6 py-6 text-sm text-muted-foreground">No return requests for this order.</p>
            ) : (
                <div className="divide-y divide-border">
                    {returns.map((returnRequest) => (
                        <article key={returnRequest.id} className="space-y-3 px-6 py-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <StatusPill status={returnRequest.status} />
                                    <p className="text-sm text-muted-foreground">Requested {formatDateTime(returnRequest.requested_at)}</p>
                                </div>
                                {canManageReturns && returnRequest.status === 'requested' && (
                                    <div className="flex gap-2">
                                        <Button size="compact" variant="tertiary" onClick={() => setAction({ returnRequest, status: 'rejected' })}>Decline</Button>
                                        <Button size="compact" onClick={() => setAction({ returnRequest, status: 'approved' })}>Approve</Button>
                                    </div>
                                )}
                                {canManageReturns && returnRequest.status === 'approved' && (
                                    <Button size="compact" onClick={() => setAction({ returnRequest, status: 'received' })}>Record received</Button>
                                )}
                            </div>
                            <p className="text-sm text-foreground"><span className="font-medium">Reason:</span> {returnRequest.reason}</p>
                            <p className="text-sm text-muted-foreground">
                                {returnRequest.items.map((item) => `${item.display_name}: ${item.quantity} unit(s)`).join(' · ')}
                            </p>
                            {returnRequest.review_note && (
                                <p className="rounded-md bg-muted px-3 py-2 text-sm text-foreground">
                                    <span className="font-medium">Staff note:</span> {returnRequest.review_note}
                                </p>
                            )}
                            {canManageReturns && returnRequest.reviewed_at && (
                                <p className="text-xs text-muted-foreground">Reviewed {formatDateTime(returnRequest.reviewed_at)}{returnRequest.reviewed_by_name ? ` by ${returnRequest.reviewed_by_name}` : ''}</p>
                            )}
                            {canManageReturns && returnRequest.received_at && (
                                <p className="text-xs text-muted-foreground">Received {formatDateTime(returnRequest.received_at)}{returnRequest.received_by_name ? ` by ${returnRequest.received_by_name}` : ''}</p>
                            )}
                        </article>
                    ))}
                </div>
            )}

            <RequestReturnModal
                open={requestOpen}
                onClose={() => setRequestOpen(false)}
                order={order}
                returnPolicy={returnPolicy}
            />
            <ReviewReturnModal action={action} onClose={() => setAction(null)} />
        </section>
    );
}
