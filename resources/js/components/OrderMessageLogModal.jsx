import { Modal } from '@/components/interior/modal';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';
import axios from 'axios';
import { Bell, CheckCircle2, CircleAlert, LoaderCircle, MessageCircle, Smartphone } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const CHANNELS = {
    portal: { label: 'Notification', icon: Bell },
    sms: { label: 'SMS', icon: Smartphone },
    facebook: { label: 'Facebook', icon: MessageCircle },
};

const STATUSES = {
    sent: {
        label: 'Sent',
        className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    },
    skipped: {
        label: 'Not sent',
        className: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    },
    failed: {
        label: 'Failed',
        className: 'bg-red-50 text-red-700 ring-red-600/20',
    },
};

function LogEntry({ entry }) {
    const channel = CHANNELS[entry.channel] ?? CHANNELS.portal;
    const status = STATUSES[entry.status] ?? {
        label: entry.status,
        className: 'bg-stone-100 text-stone-700 ring-stone-500/20',
    };
    const ChannelIcon = channel.icon;

    return (
        <li className="rounded-xl border border-stone-200 p-4 dark:border-white/[0.12]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-stone-100 text-stone-600 dark:bg-white/10 dark:text-stone-300">
                        <ChannelIcon aria-hidden="true" className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="font-medium text-stone-900 dark:text-stone-100">{channel.label}</p>
                        <p className="text-xs text-stone-500 dark:text-stone-400">{formatDateTime(entry.created_at)}</p>
                    </div>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${status.className}`}>
                    {status.label}
                </span>
            </div>

            <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                {entry.recipient ? (
                    <div>
                        <dt className="text-stone-500">Recipient</dt>
                        <dd className="break-all font-medium text-stone-800 dark:text-stone-200">{entry.recipient}</dd>
                    </div>
                ) : null}
                {entry.external_reference ? (
                    <div>
                        <dt className="text-stone-500">Provider reference</dt>
                        <dd className="break-all font-medium text-stone-800 dark:text-stone-200">{entry.external_reference}</dd>
                    </div>
                ) : null}
            </dl>

            {entry.note ? (
                <p className="mt-3 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 dark:bg-white/[0.06] dark:text-stone-300">
                    {entry.note}
                </p>
            ) : null}
        </li>
    );
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
            ) : entries.length === 0 ? (
                <div className="flex min-h-52 flex-col items-center justify-center gap-3 text-center">
                    <CheckCircle2 aria-hidden="true" className="size-6 text-stone-400" />
                    <div>
                        <p className="font-medium text-stone-900 dark:text-stone-100">No message attempts recorded</p>
                        <p className="mt-1 text-xs text-stone-500">Notification, SMS, and Facebook attempts for this order will appear here.</p>
                    </div>
                </div>
            ) : (
                <ul className="space-y-3">
                    {entries.map((entry) => <LogEntry key={entry.id} entry={entry} />)}
                </ul>
            )}
        </Modal>
    );
}
