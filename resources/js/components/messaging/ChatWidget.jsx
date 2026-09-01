import {
    Message,
    MessageContent,
    MessageFooter,
    MessageGroup,
    MessageHeader,
    MessageMarker,
} from '@/components/agents/message';
import { MessageBubble, MessageBubbleContent } from '@/components/agents/message-bubble';
import { MessageScroller } from '@/components/agents/message-scroller';
import { Dropdown } from '@/components/interior/dropdown';
import { AutoHeightReveal, Modal } from '@/components/interior/modal';
import { Input } from '@/components/motion/input';
import { Tooltip } from '@/components/motion/tooltip';
import { Button } from '@/components/ui/button';
import echo from '@/echo';
import { useChatWidget } from '@/lib/chat-widget-context';
import { SPRING_PANEL } from '@/lib/ease';
import { formatDateTime } from '@/utils/orderDisplay';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import axios from 'axios';
import { ArrowDown, ArrowUp, Minus, Pencil, SquarePen, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const PANEL_WIDTH = 420;
const PANEL_GAP = 16;
const MINIMIZED_SLOT_HEIGHT = 72;
const MINIMIZED_GAP = 12;

function ChatWidgetPanel({ chat, minimized, position, onClose, onMinimizeChange }) {
    const reduce = useReducedMotion();
    const { notifyRead, renameChat } = useChatWidget();

    const [loading, setLoading] = useState(false);
    const [messages, setMessages] = useState([]);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const [following, setFollowing] = useState(true);
    const [unreadIds, setUnreadIds] = useState([]);
    const [linkedAgent, setLinkedAgent] = useState(null);
    const [agentOptions, setAgentOptions] = useState([]);
    const [linking, setLinking] = useState(false);
    const [renameOpen, setRenameOpen] = useState(false);
    const [renameValue, setRenameValue] = useState(chat.name);
    const [renameError, setRenameError] = useState(null);
    const [renaming, setRenaming] = useState(false);
    const viewportRef = useRef(null);

    const isFacebook = chat.channel === 'facebook';

    // The staff member this conversation is assigned to server-side: the
    // staff being talked to (when a customer opened this), or the viewer
    // themselves (when a staff member opened it). Facebook threads aren't
    // split per staff member, so this doesn't apply there.
    const assignedUserId = chat.staffUserId ?? chat.viewerUserId;

    const fetchMessages = () =>
        isFacebook
            ? axios.get(route('messages.widget.facebook.show', chat.threadId))
            : axios.get(route('messages.widget.show', chat.customerId), {
                  params: chat.staffUserId != null ? { staff_id: chat.staffUserId } : undefined,
              });

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);
        setMessages([]);
        setUnreadIds([]);

        fetchMessages()
            .then((response) => {
                if (!active) return;
                setMessages(response.data.messages);
                // Only the initial load's snapshot draws the "new messages"
                // divider/dots -- anything that streams in afterward is being
                // watched live, so it doesn't need a missed-it marker.
                setUnreadIds(response.data.unread_message_ids ?? []);
                setLinkedAgent(response.data.thread?.assigned_user ?? null);
                // Opening this panel just marked messages read server-side
                // (this thread's own, and possibly other orphaned ones too)
                // -- tell the header to refresh instead of leaving the badge
                // stale until the next poll.
                notifyRead();
            })
            .catch(() => {
                if (active) setError('Unable to load this conversation.');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [chat.channel, chat.customerId, chat.staffUserId, chat.threadId]);

    useEffect(() => {
        if (!echo || !chat.viewerUserId) return undefined;

        const channel = echo.private(`users.${chat.viewerUserId}`);
        const eventName = '.customer-message.created';

        const handleIncoming = (event) => {
            if (isFacebook) {
                if (String(event.thread_id) !== String(chat.threadId)) return;
            } else {
                if (String(event.customer_id) !== String(chat.customerId)) return;
                if (String(event.assigned_user_id) !== String(assignedUserId)) return;
            }

            fetchMessages()
                .then((response) => {
                    setMessages(response.data.messages);
                    setLinkedAgent(response.data.thread?.assigned_user ?? null);
                })
                .catch(() => {});
        };

        channel.listen(eventName, handleIncoming);

        // Only stop listening here -- `users.{id}` is shared with the header's
        // unread badge, and leaving the channel would tear down that
        // subscription too.
        return () => {
            channel.stopListening(eventName, handleIncoming);
        };
    }, [chat.channel, chat.customerId, chat.staffUserId, chat.threadId, chat.viewerUserId, assignedUserId]);

    useEffect(() => {
        if (!isFacebook) return undefined;
        let active = true;

        axios
            .get(route('messages.users-search'))
            .then((response) => {
                if (!active) return;
                setAgentOptions(
                    response.data.users.map((agent) => ({
                        value: String(agent.id),
                        label: agent.full_name,
                    })),
                );
            })
            .catch(() => {});

        return () => {
            active = false;
        };
    }, [isFacebook]);

    const linkAgent = (userId) => {
        setLinking(true);
        axios
            .post(route('messages.widget.facebook.link', chat.threadId), { user_id: userId })
            .then((response) => setLinkedAgent(response.data.thread?.assigned_user ?? null))
            .catch(() => {})
            .finally(() => setLinking(false));
    };

    const openRename = () => {
        setRenameValue(chat.name);
        setRenameError(null);
        setRenameOpen(true);
    };

    const submitRename = (event) => {
        event.preventDefault();
        const name = renameValue.trim();

        if (!name) {
            setRenameError('Enter a conversation name.');
            return;
        }
        if (name === chat.name) {
            setRenameOpen(false);
            return;
        }

        setRenaming(true);
        setRenameError(null);

        axios
            .patch(route('messages.widget.facebook.rename', chat.threadId), { name })
            .then((response) => {
                const savedName = response.data.thread.name;
                renameChat(chat.key, savedName);
                setRenameOpen(false);
                notifyRead();
            })
            .catch((error) => {
                setRenameError(
                    error.response?.data?.errors?.name?.[0] ??
                        'Unable to rename this conversation. Try again.',
                );
            })
            .finally(() => setRenaming(false));
    };

    const submit = (e) => {
        e.preventDefault();
        const trimmed = body.trim();
        if (!trimmed || sending) return;

        setSending(true);
        setError(null);

        const request = isFacebook
            ? axios.post(route('messages.widget.facebook.send', chat.threadId), { body: trimmed })
            : axios.post(route('messages.widget.send', chat.customerId), {
                  body: trimmed,
                  ...(chat.staffUserId != null ? { staff_id: chat.staffUserId } : {}),
              });

        request
            .then((response) => {
                setMessages(response.data.messages);
                setBody('');
            })
            .catch((err) => {
                setError(
                    err.response?.data?.errors?.body?.[0] ??
                        'Unable to send that message. Try again.',
                );
            })
            .finally(() => {
                setSending(false);
            });
    };

    return (
        <motion.div
            key={chat.key}
            role="dialog"
            aria-label={`Conversation with ${chat.name}`}
            initial={reduce ? { opacity: 0 } : { opacity: 0, y: 24, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduce ? { opacity: 0 } : { opacity: 0, y: 24, scale: 0.96 }}
            transition={reduce ? { duration: 0.15 } : SPRING_PANEL}
            style={position}
            className={`group/panel fixed z-50 flex max-w-[calc(100vw-2rem)] flex-col rounded-2xl border border-stone-200 bg-white dark:border-white/[0.16] dark:bg-[#1D1D1A] ${
                minimized
                    ? 'h-auto w-auto overflow-visible'
                    : 'h-[560px] w-[420px] max-h-[calc(100vh-2rem)] overflow-hidden'
            }`}
        >
            <div
                className={`relative flex shrink-0 items-center justify-between gap-2 px-4 py-3 ${
                    minimized
                        ? 'cursor-pointer'
                        : 'border-b border-stone-200 dark:border-white/[0.16]'
                }`}
                onClick={minimized ? () => onMinimizeChange(false) : undefined}
            >
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-stone-900 dark:text-stone-100">
                        {chat.name}
                    </p>
                    {chat.hint || (isFacebook && !minimized) ? (
                        <div className="flex min-w-0 items-center gap-1 text-xs">
                            {chat.hint ? (
                                <span className="shrink-0 text-stone-500 dark:text-stone-400">
                                    {chat.hint}
                                </span>
                            ) : null}
                            {isFacebook && !minimized ? (
                                <>
                                    {chat.hint ? (
                                        <span
                                            aria-hidden="true"
                                            className="shrink-0 text-stone-400 dark:text-stone-500"
                                        >
                                            -
                                        </span>
                                    ) : null}
                                    <Dropdown
                                        items={[
                                            { value: '', label: 'No sales agent (unlink)' },
                                            ...agentOptions,
                                        ]}
                                        value={linkedAgent ? String(linkedAgent.id) : ''}
                                        onChange={(value) => linkAgent(value ? Number(value) : null)}
                                        disabled={linking}
                                        searchable
                                        searchPlaceholder="Search sales agents"
                                        emptyLabel="No sales agents found"
                                        label="Link to sales agent"
                                        portal
                                        className="min-w-0"
                                        triggerClassName="block max-w-full truncate bg-transparent p-0 text-left text-xs font-medium text-primary outline-none hover:underline disabled:opacity-50"
                                        trigger={
                                            <span className="truncate">
                                                {linkedAgent
                                                    ? `Linked to ${linkedAgent.full_name}`
                                                    : 'Link to sales agent'}
                                            </span>
                                        }
                                    />
                                </>
                            ) : null}
                        </div>
                    ) : null}
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {isFacebook && !minimized ? (
                        <Tooltip content="Rename conversation">
                            <Button
                                variant="ghost"
                                size="icon-compact"
                                aria-label="Rename conversation"
                                onClick={openRename}
                            >
                                <Pencil aria-hidden="true" />
                            </Button>
                        </Tooltip>
                    ) : null}
                    {minimized ? null : (
                        <Tooltip content="Minimize conversation">
                            <Button
                                variant="ghost"
                                size="icon-compact"
                                aria-label="Minimize conversation"
                                onClick={() => onMinimizeChange(true)}
                            >
                                <Minus aria-hidden="true" />
                            </Button>
                        </Tooltip>
                    )}
                    {minimized ? (
                        <Button
                            variant="ghost"
                            size="icon-compact"
                            aria-label="Close conversation"
                            onClick={(e) => {
                                e.stopPropagation();
                                onClose();
                            }}
                            className="absolute -right-2 -top-2 rounded-full border border-stone-200 bg-white opacity-0 shadow-sm transition-opacity duration-150 group-hover/panel:opacity-100 dark:border-white/[0.16] dark:bg-[#1D1D1A]"
                        >
                            <X aria-hidden="true" />
                        </Button>
                    ) : (
                        <Button
                            variant="ghost"
                            size="icon-compact"
                            aria-label="Close conversation"
                            onClick={(e) => {
                                e.stopPropagation();
                                onClose();
                            }}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    )}
                </div>
            </div>

            {isFacebook ? (
                <Modal
                    open={renameOpen}
                    onClose={() => !renaming && setRenameOpen(false)}
                    title="Rename conversation"
                    maxWidth={560}
                    className="[&>div:first-child]:px-6 [&>div:first-child]:pb-5 [&>div:first-child]:pt-6 [&>div:first-child_h2]:!text-lg [&>div:last-child]:mt-auto [&>div:last-child]:px-6 [&>div:last-child]:py-5"
                    closeOnBackdrop={!renaming}
                    closeOnEscape={!renaming}
                    footer={
                        <>
                            <Button
                                variant="tertiary"
                                className="h-10 rounded-md px-5 text-sm"
                                onClick={() => setRenameOpen(false)}
                                disabled={renaming}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                form={`rename-facebook-thread-${chat.threadId}`}
                                className="h-10 rounded-md px-5 text-sm"
                                loading={renaming}
                            >
                                Save name
                            </Button>
                        </>
                    }
                >
                    <AutoHeightReveal>
                        <form
                            id={`rename-facebook-thread-${chat.threadId}`}
                            onSubmit={submitRename}
                            className="space-y-3 px-2 pb-2 pt-2"
                        >
                            <p className="text-sm text-stone-600 dark:text-stone-300">
                                Choose a name that helps your team recognize this Facebook contact.
                            </p>
                            <Input
                                label="Conversation name"
                                value={renameValue}
                                onChange={setRenameValue}
                                error={renameError}
                                maxLength={200}
                                autoComplete="off"
                                autoFocus
                                classNames={{
                                    label: 'px-0',
                                    field: 'h-10 rounded-md',
                                    input: 'text-sm',
                                }}
                            />
                        </form>
                    </AutoHeightReveal>
                </Modal>
            ) : null}

            {minimized ? null : (
                <>
                    <div className="relative min-h-0 flex-1">
                        <MessageScroller
                            label={`Conversation with ${chat.name}`}
                            followOutput
                            onFollowChange={setFollowing}
                            viewportRef={viewportRef}
                            navigation="rail"
                            navigationLabel={`Jump to a message with ${chat.name}`}
                            className="h-full"
                            viewportClassName="px-3 py-3"
                            contentClassName={`flex min-h-full flex-col ${
                                !loading && messages.length === 0
                                    ? 'items-center justify-center text-center'
                                    : 'justify-end'
                            }`}
                        >
                            {loading ? (
                                <p className="px-1 text-sm text-stone-500 dark:text-stone-400">
                                    Loading conversation...
                                </p>
                            ) : messages.length === 0 ? (
                                <p className="px-1 text-sm text-stone-500 dark:text-stone-400">
                                    No messages yet. Say hello!
                                </p>
                            ) : (
                                <MessageGroup spacing="default">
                                    {(() => {
                                        const viewerIsCompany = chat.viewerIsCompany ?? true;
                                        const unreadIdSet = new Set(unreadIds);
                                        const firstUnreadIndex = messages.findIndex((m) =>
                                            unreadIdSet.has(m.id),
                                        );
                                        const lastSelfMessage = [...messages]
                                            .reverse()
                                            .find((m) =>
                                                viewerIsCompany
                                                    ? m.sender_type === 'company'
                                                    : m.sender_type === 'customer',
                                            );

                                        return messages.map((message, index) => {
                                            const fromSelf = viewerIsCompany
                                                ? message.sender_type === 'company'
                                                : message.sender_type === 'customer';
                                            const isUnread = !fromSelf && unreadIdSet.has(message.id);
                                            const showReadReceipt =
                                                fromSelf && lastSelfMessage?.id === message.id;

                                            return (
                                                <div key={message.id}>
                                                    {index === firstUnreadIndex ? (
                                                        <MessageMarker className="my-2">
                                                            New messages
                                                        </MessageMarker>
                                                    ) : null}
                                                    <Message from={fromSelf ? 'user' : 'assistant'}>
                                                        <MessageContent>
                                                            <MessageHeader>
                                                                {isUnread ? (
                                                                    <span
                                                                        aria-label="Unread"
                                                                        className="size-1.5 rounded-full bg-primary"
                                                                    />
                                                                ) : null}
                                                                <span className="font-medium text-foreground/70">
                                                                    {fromSelf ? 'You' : chat.name}
                                                                </span>
                                                            </MessageHeader>
                                                            <MessageBubble variant="soft">
                                                                <MessageBubbleContent
                                                                    className={
                                                                        fromSelf
                                                                            ? 'bg-primary/10 text-foreground'
                                                                            : undefined
                                                                    }
                                                                >
                                                                    <span className="whitespace-pre-wrap">
                                                                        {message.body}
                                                                    </span>
                                                                </MessageBubbleContent>
                                                            </MessageBubble>
                                                            <MessageFooter>
                                                                {formatDateTime(message.created_at)}
                                                                {showReadReceipt ? (
                                                                    <>
                                                                        <span aria-hidden="true">
                                                                            {' '}
                                                                            ·{' '}
                                                                        </span>
                                                                        {message.is_read ? 'Read' : 'Sent'}
                                                                    </>
                                                                ) : null}
                                                            </MessageFooter>
                                                        </MessageContent>
                                                    </Message>
                                                </div>
                                            );
                                        });
                                    })()}
                                </MessageGroup>
                            )}
                        </MessageScroller>

                        {following || messages.length === 0 ? null : (
                            <button
                                type="button"
                                onClick={() => {
                                    setFollowing(true);
                                    viewportRef.current?.scrollTo({
                                        top: viewportRef.current.scrollHeight,
                                        behavior: reduce ? 'auto' : 'smooth',
                                    });
                                }}
                                className="absolute bottom-2 left-1/2 inline-flex h-7 -translate-x-1/2 items-center gap-1 rounded-full border border-stone-200 bg-white px-3 text-xs font-medium text-stone-600 shadow-sm outline-none transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-ring dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:text-stone-300"
                            >
                                <ArrowDown className="size-3.5" aria-hidden="true" />
                                Jump to latest
                            </button>
                        )}
                    </div>

                    {error ? (
                        <p className="shrink-0 px-3 pb-1 text-xs text-rose-500">{error}</p>
                    ) : null}

                    <form
                        onSubmit={submit}
                        className="relative shrink-0 border-t border-stone-200 p-3 dark:border-white/[0.16]"
                    >
                        <textarea
                            required
                            rows={3}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    submit(e);
                                }
                            }}
                            placeholder="Write a message"
                            className="block max-h-40 min-h-20 w-full resize-none rounded-lg border-stone-200 py-2 pl-3 pr-12 text-sm outline-none focus:border-stone-400 focus:ring-0 dark:border-white/[0.16] dark:bg-transparent dark:text-stone-100"
                        />
                        <Button
                            type="submit"
                            size="icon-compact"
                            aria-label="Send message"
                            disabled={sending || !body.trim()}
                            className="absolute bottom-5 right-5"
                        >
                            <ArrowUp aria-hidden="true" />
                        </Button>
                    </form>
                </>
            )}
        </motion.div>
    );
}

export default function ChatWidget() {
    const { chats, closeChat, setChatMinimized, setComposeOpen, isAuthenticated } = useChatWidget();

    const ordered = [...chats].reverse();
    const expanded = ordered.filter((chat) => !chat.minimized);
    const minimizedList = ordered.filter((chat) => chat.minimized);
    // Minimized chats stack in their own vertical column, to the left of
    // however many expanded panels are open, so the two groups never overlap.
    const minimizedColumnRight = 16 + expanded.length * (PANEL_WIDTH + PANEL_GAP);

    return createPortal(
        <AnimatePresence>
            {expanded.map((chat, index) => (
                <ChatWidgetPanel
                    key={chat.key}
                    chat={chat}
                    minimized={false}
                    position={{ right: 16 + index * (PANEL_WIDTH + PANEL_GAP), bottom: 16 }}
                    onClose={() => closeChat(chat.key)}
                    onMinimizeChange={(value) => setChatMinimized(chat.key, value)}
                />
            ))}
            {minimizedList.map((chat, index) => (
                <ChatWidgetPanel
                    key={chat.key}
                    chat={chat}
                    minimized
                    position={{
                        right: minimizedColumnRight,
                        bottom: 16 + index * (MINIMIZED_SLOT_HEIGHT + MINIMIZED_GAP),
                    }}
                    onClose={() => closeChat(chat.key)}
                    onMinimizeChange={(value) => setChatMinimized(chat.key, value)}
                />
            ))}
            {isAuthenticated ? (
                <motion.div
                    key="new-message-launcher"
                    layout
                    style={{
                        right: minimizedColumnRight,
                        // Sits one slot above the minimized stack (closest to
                        // the page content, farthest from the screen edge)
                        // so it stays reachable instead of crowding the
                        // bottom edge as more minimized chats pile up.
                        bottom: 16 + minimizedList.length * (MINIMIZED_SLOT_HEIGHT + MINIMIZED_GAP),
                    }}
                    className="fixed z-50"
                >
                    <Tooltip content="New message" side="left">
                        <button
                            type="button"
                            aria-label="New message"
                            onClick={() => setComposeOpen(true)}
                            className="flex size-11 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-700 shadow-[0_1px_2px_rgba(28,25,23,0.06),0_16px_36px_-18px_rgba(28,25,23,0.5)] outline-none transition-colors hover:bg-stone-50 focus-visible:ring-1 focus-visible:ring-[color:var(--focus-ring,#6B97FF)] dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:text-stone-200 dark:shadow-[0_2px_12px_rgba(0,0,0,0.6)] dark:hover:bg-white/5"
                        >
                            <SquarePen className="size-4" aria-hidden="true" />
                        </button>
                    </Tooltip>
                </motion.div>
            ) : null}
        </AnimatePresence>,
        document.body,
    );
}
