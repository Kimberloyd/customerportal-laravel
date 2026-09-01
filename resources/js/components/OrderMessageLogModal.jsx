import { Modal } from '@/components/interior/modal';
import { Timeline, timeLabel } from '@/components/timelines-activity-feed';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { Bell, CircleAlert, LoaderCircle, MessageCircle, Smartphone } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const CHANNELS = {
    portal: { label: 'Notification', icon: Bell },
    sms: { label: 'SMS', icon: Smartphone },
    facebook: { label: 'Facebook', icon: MessageCircle },
};

const STATUSES = {
    sent: {
        label: 'Sent',
        badgeClassName: 'bg-success/10 text-success ring-success/30',
        tone: 'bg-success/10 text-success',
    },
    skipped: {
        label: 'Not sent',
        badgeClassName: 'bg-amber-500/10 text-amber-600 ring-amber-500/30 dark:text-amber-400',
        tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    failed: {
        label: 'Failed',
        badgeClassName: 'bg-destructive/10 text-destructive ring-destructive/30',
        tone: 'bg-destructive/10 text-destructive',
    },
};
const DEFAULT_STATUS_TONE = 'bg-muted text-muted-foreground';

function toTimelineEntry(entry) {
    const channel = CHANNELS[entry.channel] ?? CHANNELS.portal;
    const status = STATUSES[entry.status] ?? {
        label: entry.status,
        badgeClassName: 'bg-muted text-muted-foreground ring-border',
        tone: DEFAULT_STATUS_TONE,
    };

    return {
        key: String(entry.id),
        createdAt: entry.created_at,
        icon: channel.icon,
        tone: status.tone,
        render: () => (
            <div>
                <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <h4 className="text-sm font-medium text-foreground">{channel.label}</h4>
                    <div className="flex shrink-0 items-center gap-2">
                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${status.badgeClassName}`}>
                            {status.label}
                        </span>
                        <time
                            dateTime={entry.created_at ?? undefined}
                            className="text-[11px] tabular-nums text-muted-foreground"
                        >
                            {timeLabel(entry.created_at)}
                        </time>
                    </div>
                </div>

                {(entry.recipient || entry.external_reference) && (
                    <dl className="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                        {entry.recipient ? (
                            <div>
                                <dt className="text-muted-foreground">Recipient</dt>
                                <dd className="break-all font-medium text-foreground">{entry.recipient}</dd>
                            </div>
                        ) : null}
                        {entry.external_reference ? (
                            <div>
                                <dt className="text-muted-foreground">Provider reference</dt>
                                <dd className="break-all font-medium text-foreground">{entry.external_reference}</dd>
                            </div>
                        ) : null}
                    </dl>
                )}

                {entry.note ? (
                    <div className="mt-2 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                        {entry.note}
                    </div>
                ) : null}
            </div>
        ),
    };
}

export default function OrderMessageLogModal({ order, open, onClose }) {
    const [entries, setEntries] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    const load = useCallback(async () => {
        if (!order) return;

        setLoading(true);
        setError(false);

        try {
            const response = await axios.get(route('purchase-orders.message-log', order.id));
            setEntries(response.data.entries ?? []);
        } catch {
            setEntries([]);
            setError(true);
        } finally {
            setLoading(false);
        }
    }, [order]);

    useEffect(() => {
        if (!open || !order) return;
        setEntries([]);
        load();
    }, [open, order, load]);

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Message Log${order ? ` — ${order.po_number}` : ''}`}
            description="Sent means the portal recorded the notification or the provider accepted the request. SMS delivery receipts are not currently tracked."
            maxWidth={680}
            maxHeight="min(82vh, 720px)"
            footer={
                <Button type="button" variant="tertiary" onClick={onClose}>
                    Close
                </Button>
            }
        >
            {loading ? (
                <div className="flex min-h-52 flex-col items-center justify-center gap-3 text-stone-500">
                    <LoaderCircle aria-hidden="true" className="size-5 animate-spin" />
                    <p>Loading message log…</p>
                </div>
            ) : error ? (
                <div className="flex min-h-52 flex-col items-center justify-center gap-3 text-center">
                    <CircleAlert aria-hidden="true" className="size-6 text-red-500" />
                    <div>
                        <p className="font-medium text-stone-900 dark:text-stone-100">We couldn’t load this message log.</p>
                        <p className="mt-1 text-xs text-stone-500">Check your connection and try again.</p>
                    </div>
                    <Button type="button" variant="tertiary" size="compact" onClick={load}>
                        Try again
                    </Button>
                </div>
            ) : (
                <Timeline
                    entries={entries.map(toTimelineEntry)}
                    emptyTitle="No message attempts recorded"
                    emptyDescription="Notification, SMS, and Facebook attempts for this order will appear here."
                />
            )}
        </Modal>
    );
}
