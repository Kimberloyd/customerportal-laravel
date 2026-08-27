import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { Checkbox } from '@/components/motion/checkbox';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { ArrowUp, Search } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useMemo, useState } from 'react';

const SLIDE = { type: 'spring', stiffness: 700, damping: 46, mass: 0.5 };
const NONE = { duration: 0 };
const ROW_H = 36;

export default function ComposeModal({ open, onClose, accounts }) {
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState([]);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [result, setResult] = useState(null);
    const [hoverIndex, setHoverIndex] = useState(-1);
    const reduced = useReducedMotion();

    const filtered = useMemo(() => {
        const q = query.trim().toLocaleLowerCase();
        if (!q) return accounts;

        return accounts.filter((account) =>
            [account.label, account.hint].some((value) =>
                String(value ?? '').toLocaleLowerCase().includes(q),
            ),
        );
    }, [accounts, query]);

    const toggle = (value) => {
        setSelected((current) =>
            current.includes(value)
                ? current.filter((existing) => existing !== value)
                : [...current, value],
        );
    };

    const reset = () => {
        setQuery('');
        setSelected([]);
        setBody('');
        setResult(null);
        setHoverIndex(-1);
    };

    const handleClose = () => {
        if (sending) return;
        reset();
        onClose();
    };

    const send = async (e) => {
        e.preventDefault();
        const trimmed = body.trim();
        if (!trimmed || selected.length === 0 || sending) return;

        setSending(true);

        const targets = accounts.filter((account) => selected.includes(account.value));
        let sent = 0;
        let failed = 0;

        await Promise.all(
            targets.map((target) => {
                const endpoint = target.channel === 'facebook'
                    ? route('messages.widget.facebook.send', target.threadId)
                    : route('messages.widget.send', target.customerId);
                const payload = {
                    body: trimmed,
                    ...(target.channel !== 'facebook' && target.staffUserId != null
                        ? { staff_id: target.staffUserId }
                        : {}),
                };

                return axios
                    .post(endpoint, payload)
                    .then(() => {
                        sent += 1;
                    })
                    .catch(() => {
                        failed += 1;
                    });
            }),
        );

        setSending(false);
        setResult({ sent, failed });
    };

    return (
        <Modal
            open={open}
            onClose={handleClose}
            title="New message"
            maxWidth={560}
            footer={
                result ? (
                    <Button variant="primary" size="compact" onClick={handleClose}>
                        Done
                    </Button>
                ) : (
                    <form onSubmit={send} className="relative w-full">
                        <textarea
                            required
                            rows={3}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    send(e);
                                }
                            }}
                            placeholder="Write a message"
                            className="block max-h-40 min-h-20 w-full resize-none rounded-lg border-stone-200 py-2 pl-3 pr-12 text-sm text-stone-900 outline-none focus:border-stone-400 focus:ring-0 dark:border-white/[0.16] dark:bg-transparent dark:text-stone-100"
                        />
                        <Button
                            type="submit"
                            size="icon-compact"
                            aria-label="Send message"
                            disabled={sending || selected.length === 0 || !body.trim()}
                            className="absolute bottom-2 right-2"
                        >
                            <ArrowUp aria-hidden="true" />
                        </Button>
                    </form>
                )
            }
        >
            <AutoHeightReveal>
            {result ? (
                <p className="text-sm text-stone-600 dark:text-stone-300">
                    {result.sent > 0
                        ? `Sent to ${result.sent} account${result.sent === 1 ? '' : 's'}.`
                        : null}
                    {result.failed > 0
                        ? `${result.sent > 0 ? ' ' : ''}${result.failed} failed to send.`
                        : null}
                </p>
            ) : (
                <div className="space-y-3">
                    <div className="flex h-9 items-center gap-2 rounded-full border border-stone-200 px-3 text-stone-500 focus-within:border-stone-400 dark:border-white/[0.16] dark:text-stone-400">
                        <Search className="size-4 shrink-0" aria-hidden="true" />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => {
                                setQuery(e.target.value);
                                setHoverIndex(-1);
                            }}
                            placeholder="Search accounts"
                            aria-label="Search accounts"
                            className="min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] text-stone-900 outline-none placeholder:text-stone-400 focus:ring-0 dark:text-stone-100"
                        />
                    </div>

                    <ul
                        className="relative max-h-48 overflow-y-auto"
                        onMouseLeave={() => setHoverIndex(-1)}
                    >
                        {filtered.length === 0 ? (
                            <li className="px-2 py-1.5 text-sm text-stone-500 dark:text-stone-400">
                                No accounts found
                            </li>
                        ) : (
                            <>
                                <motion.span
                                    aria-hidden
                                    className="pointer-events-none absolute inset-x-0 top-0 h-9 rounded-lg bg-stone-100 dark:bg-white/10"
                                    initial={false}
                                    animate={{
                                        y: hoverIndex < 0 ? 0 : hoverIndex * ROW_H,
                                        opacity: hoverIndex < 0 ? 0 : 1,
                                    }}
                                    transition={reduced ? NONE : SLIDE}
                                />
                                {filtered.map((account, index) => (
                                <li key={account.value}>
                                    <div
                                        onClick={() => toggle(account.value)}
                                        onPointerMove={() => setHoverIndex(index)}
                                        className="relative z-10 flex h-9 cursor-pointer items-center gap-2 rounded-lg px-2"
                                    >
                                        <span onClick={(event) => event.stopPropagation()}>
                                            <Checkbox
                                                checked={selected.includes(account.value)}
                                                onCheckedChange={() => toggle(account.value)}
                                                aria-label={account.label}
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-sm text-stone-700 dark:text-stone-200">
                                            {account.label}
                                        </span>
                                        <span className="shrink-0 font-mono text-[10.5px] text-stone-500 dark:text-stone-400">
                                            {account.hint}
                                        </span>
                                    </div>
                                </li>
                                ))}
                            </>
                        )}
                    </ul>
                </div>
            )}
            </AutoHeightReveal>
        </Modal>
    );
}
