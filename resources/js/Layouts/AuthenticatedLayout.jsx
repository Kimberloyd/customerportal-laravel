import echo from '@/echo';
import FlashBanner from '@/components/FlashBanner';
import PushRegistration from '@/components/PushRegistration';
import PullToRefresh from '@/components/PullToRefresh';
import ResponsiveNavLink from '@/components/ResponsiveNavLink';
import ThemeToggle from '@/components/ThemeToggle';
import { Dropdown as AccountDropdown } from '@/components/interior/dropdown';
import { useModal } from '@/components/interior/modal';
import { Tooltip } from '@/components/motion/tooltip';
import ComposeModal from '@/components/messaging/ComposeModal';
import { FooterSimple } from '@/components/smoothui/footer-1';
import { CountBadge, NotificationBell } from '@/components/ui/notification-bell';
import { useChatWidget } from '@/lib/chat-widget-context';
import { formatDateTime } from '@/utils/orderDisplay';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Bell,
    CheckCheck,
    LogOut,
    MessageCircle,
    SquarePen,
    User,
    X,
} from 'lucide-react';
import { Menu as MenuIconNode, X as XIconNode } from 'lucide';
import { MorphIcon } from 'morphicons/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { createPortal } from 'react-dom';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const EASE = [0.23, 1, 0.32, 1];
const EXIT_EASE = [0.4, 0, 1, 1];
const OPEN_SPRING = { type: 'spring', stiffness: 620, damping: 38, mass: 0.6 };
const NOTIFICATIONS_PANEL_WIDTH = 460;
const NOTIFICATIONS_PANEL_GAP = 6;

const USER_MENU_ITEMS = [
    {
        value: 'profile',
        label: 'Profile',
        icon: <User aria-hidden="true" className="h-4 w-4" />,
    },
    {
        value: 'logout',
        label: 'Log Out',
        icon: <LogOut aria-hidden="true" className="h-4 w-4" />,
        destructive: true,
    },
];

export default function AuthenticatedLayout({ header, banner, children }) {
    const user = usePage().props.auth.user;
    const { openChat, readSignal, composeOpen, setComposeOpen, setIsAuthenticated, clearChats } = useChatWidget();
    const reducedMotion = useReducedMotion() ?? false;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const closeMobileNav = useCallback(() => setShowingNavigationDropdown(false), []);
    const mobileNav = useModal({ open: showingNavigationDropdown, onClose: closeMobileNav });
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [notificationsPosition, setNotificationsPosition] = useState(null);
    // Unread chat messages. Sourced from MessageThread::unreadCount(), which
    // counts CustomerMessage rows with is_read = false -- message records, not
    // notification records. This badges the Chats icon only.
    const [unreadCount, setUnreadCount] = useState(0);

    // The bell is a separate channel from unread chat messages: it reads
    // purchase_order_notifications (via OrderNotificationFeed), scoped the
    // same way MessageThread::unreadCount() scopes chats -- a customer sees
    // only their own orders' notifications, staff see every order's.
    const [notificationCount, setNotificationCount] = useState(0);
    const [orderNotifications, setOrderNotifications] = useState([]);
    const [notificationHighlight, setNotificationHighlight] = useState({ y: 0, height: 0, opacity: 0 });
    const [messageAccounts, setMessageAccounts] = useState(null);
    const [messageAccountsError, setMessageAccountsError] = useState(false);
    const mountedRef = useRef(true);
    const notificationsTriggerRef = useRef(null);
    // Mobile has its own bell trigger (rendered on the left of the header
    // instead of the desktop-only right-side cluster) -- both are always
    // mounted, only one is ever visible at a given viewport width via CSS,
    // so the popover position/outside-click logic below checks both refs
    // rather than assuming a single one is active.
    const mobileNotificationsTriggerRef = useRef(null);
    const notificationsPanelRef = useRef(null);

    const closeNotifications = useCallback(() => {
        setNotificationsOpen(false);
        // Only steal focus back to the bell if it was actually inside the
        // panel (e.g. Escape while a notification link was focused) -- an
        // outside click that closes the panel already moved focus somewhere
        // the user chose, and shouldn't be yanked away from it.
        if (notificationsPanelRef.current?.contains(document.activeElement)) {
            const visibleTrigger = [notificationsTriggerRef, mobileNotificationsTriggerRef]
                .map((ref) => ref.current)
                .find((el) => el && el.offsetWidth > 0);
            visibleTrigger?.querySelector('button')?.focus();
        }
    }, []);
    const highlightNotification = (event) => {
        const item = event.currentTarget;
        setNotificationHighlight({ y: item.offsetTop, height: item.offsetHeight, opacity: 1 });
    };
    const clearNotificationHighlight = () => {
        setNotificationHighlight((previous) => ({ ...previous, opacity: 0 }));
    };

    useEffect(() => {
        if (!notificationsOpen) {
            setNotificationsPosition(null);
            setNotificationHighlight({ y: 0, height: 0, opacity: 0 });
            return;
        }

        fetchRecentNotifications();

        // Whichever trigger is actually visible at this viewport width --
        // the other is `hidden`/zero-size but still mounted.
        const activeTriggerEl = () => [notificationsTriggerRef, mobileNotificationsTriggerRef]
            .map((ref) => ref.current)
            .find((el) => el && el.offsetWidth > 0);

        const updatePosition = () => {
            const rect = activeTriggerEl()?.getBoundingClientRect();
            if (!rect) return;
            // Never wider than the viewport (minus an 8px margin each side) --
            // on a narrow phone the fixed panel width would otherwise push
            // its left edge past the screen edge entirely.
            const width = Math.min(NOTIFICATIONS_PANEL_WIDTH, window.innerWidth - 16);
            const left = Math.min(
                Math.max(8, rect.right - width),
                window.innerWidth - width - 8,
            );
            setNotificationsPosition({ top: rect.bottom + NOTIFICATIONS_PANEL_GAP, left, width });
        };

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [notificationsOpen]);

    useEffect(() => {
        if (!notificationsOpen) return;

        const onPointerDown = (event) => {
            const target = event.target;
            if (
                notificationsPanelRef.current?.contains(target) ||
                notificationsTriggerRef.current?.contains(target) ||
                mobileNotificationsTriggerRef.current?.contains(target)
            ) {
                return;
            }
            closeNotifications();
        };
        const onKeyDown = (event) => {
            if (event.key === 'Escape') closeNotifications();
        };

        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown, true);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [notificationsOpen, closeNotifications]);

    useEffect(() => {
        setIsAuthenticated(true);
        return () => {
            setIsAuthenticated(false);
            // Otherwise a chat left open (or minimized) when this layout
            // unmounts -- logout, or navigating to a guest-only page --
            // keeps rendering exactly as it last looked, visible to
            // whoever's at the login screen next.
            clearChats();
        };
    }, [setIsAuthenticated, clearChats]);

    const fetchUnreadCount = useCallback(() => {
        fetch(route('messages.unread-count'))
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => data && mountedRef.current && setUnreadCount(data.count))
            .catch(() => {});
    }, []);

    const markAllMessagesRead = useCallback(() => {
        setUnreadCount(0);
        axios.post(route('messages.mark-all-read')).catch(() => {
            if (mountedRef.current) fetchUnreadCount();
        });
    }, [fetchUnreadCount]);

    const fetchRecentNotifications = useCallback(() => {
        fetch(route('notifications.recent'))
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!data || !mountedRef.current) return;
                setNotificationCount(data.count);
                setOrderNotifications(Array.isArray(data.notifications) ? data.notifications : []);
            })
            .catch(() => {});
    }, []);

    const markAllNotificationsRead = useCallback(() => {
        setNotificationCount(0);
        setOrderNotifications((notifications) => notifications.map((notification) => ({
            ...notification,
            is_unread: false,
        })));
        axios.post(route('notifications.mark-all-read')).catch(() => {
            if (mountedRef.current) fetchRecentNotifications();
        });
    }, [fetchRecentNotifications]);

    const markNotificationRead = useCallback((notification) => {
        if (!notification.is_unread) return;
        setOrderNotifications((notifications) => notifications.map((item) => (
            item.id === notification.id ? { ...item, is_unread: false } : item
        )));
        setNotificationCount((count) => Math.max(0, count - 1));
        axios.post(route('notifications.mark-read', notification.id)).catch(() => {
            if (mountedRef.current) fetchRecentNotifications();
        });
    }, [fetchRecentNotifications]);

    const fetchMessageAccounts = useCallback(() => {
        fetch(route('messages.recipients'), {
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load message accounts.');
                }

                return response.json();
            })
            .then((data) => {
                if (mountedRef.current) {
                    setMessageAccounts(
                        Array.isArray(data.recipients) ? data.recipients : [],
                    );
                    // Piggybacks on this same response instead of a separate
                    // fetchUnreadCount() round trip -- the two call sites
                    // below always needed both together anyway.
                    if (typeof data.unread_count === 'number') {
                        setUnreadCount(data.unread_count);
                    }
                }
            })
            .catch(() => {
                if (mountedRef.current) {
                    setMessageAccountsError(true);
                }
            });
    }, []);

    useEffect(
        () => () => {
            mountedRef.current = false;
        },
        [],
    );

    // A conversation being opened/read in the chat widget marks messages read
    // server-side but doesn't itself touch the header -- this is how that
    // gets reflected here without waiting for the next poll or new message.
    // fetchMessageAccounts's response already carries unread_count, so one
    // call covers both instead of a separate fetchUnreadCount round trip.
    useEffect(() => {
        if (readSignal === 0) return;
        fetchMessageAccounts();
    }, [readSignal, fetchMessageAccounts]);

    useEffect(() => {
        let intervalId = null;

        // fetchMessageAccounts's response already carries unread_count.
        const refreshFromLiveEvent = () => {
            fetchMessageAccounts();
        };

        const refreshNotifications = () => fetchRecentNotifications();

        const stopPolling = () => {
            if (intervalId !== null) {
                clearInterval(intervalId);
                intervalId = null;
            }
        };

        const startPolling = () => {
            if (intervalId !== null) {
                return;
            }

            fetchUnreadCount();
            fetchRecentNotifications();
            intervalId = setInterval(() => {
                fetchUnreadCount();
                fetchRecentNotifications();
            }, 30000);
        };

        const handleVisibilityChange = () => {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
            }
        };

        fetchMessageAccounts();
        handleVisibilityChange();
        document.addEventListener('visibilitychange', handleVisibilityChange);

        let channel = null;
        if (echo && user.id) {
            channel = echo.private(`users.${user.id}`);
            channel.listen('.customer-message.created', refreshFromLiveEvent);
            channel.listen('.purchase-order.changed', refreshNotifications);
        }

        return () => {
            stopPolling();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
            if (channel) {
                channel.stopListening('.customer-message.created', refreshFromLiveEvent);
                channel.stopListening('.purchase-order.changed', refreshNotifications);
                echo.leave(`users.${user.id}`);
            }
        };
    }, [user.id, fetchUnreadCount, fetchMessageAccounts, fetchRecentNotifications]);

    const messageAccountItems = useMemo(() => {
        if (messageAccountsError) {
            return [
                {
                    value: '__error',
                    label: 'Accounts unavailable',
                    disabled: true,
                },
            ];
        }

        if (messageAccounts === null) {
            return [
                {
                    value: '__loading',
                    label: 'Loading accounts...',
                    disabled: true,
                },
            ];
        }

        if (messageAccounts.length === 0) {
            return [
                {
                    value: '__empty',
                    label: 'No message accounts found',
                    disabled: true,
                },
            ];
        }

        return messageAccounts.map((recipient) => {
            const unreadIcon = recipient.has_unread ? (
                <span
                    aria-label="Unread messages"
                    className="block size-2 rounded-full bg-primary"
                />
            ) : undefined;

            if (recipient.channel === 'facebook') {
                return {
                    value: `fb-${recipient.thread_id}`,
                    label: String(recipient.name).toLocaleUpperCase(),
                    hint: 'FACEBOOK',
                    channel: 'facebook',
                    threadId: recipient.thread_public_id,
                    hasUnread: Boolean(recipient.has_unread),
                    icon: unreadIcon,
                };
            }

            return {
                value:
                    recipient.contact_id != null
                        ? `staff-${recipient.contact_id}`
                        : String(recipient.customer.id),
                label: String(recipient.user_full_name).toLocaleUpperCase(),
                hint:
                    recipient.contact_id != null
                        ? String(recipient.contact_role).toLocaleUpperCase()
                        : String(recipient.customer.company_name).toLocaleUpperCase(),
                channel: 'portal',
                customerId: String(recipient.customer.public_id),
                staffUserId: recipient.contact_id != null ? recipient.contact_id : undefined,
                hasUnread: Boolean(recipient.has_unread),
                icon: unreadIcon,
            };
        });
    }, [messageAccounts, messageAccountsError]);

    // Portal accounts can start a new thread, while Facebook contacts can
    // receive a reply through their existing Messenger thread. Loading,
    // error, and empty rows are placeholders rather than message targets.
    const composableAccounts = useMemo(
        () => messageAccountItems.filter((item) => !item.disabled),
        [messageAccountItems],
    );

    const navTabs = useMemo(
        () => [
            { key: 'dashboard', href: route('dashboard'), active: route().current('dashboard'), label: 'Dashboard' },
            {
                key: 'purchase-orders',
                href: route('purchase-orders.index'),
                active: route().current('purchase-orders.*'),
                label: 'Orders',
            },
            ...(user.role === 'admin'
                ? [
                      {
                          key: 'admin.dashboard',
                          href: route('admin.dashboard'),
                          active: route().current('admin.dashboard'),
                          label: 'Admin',
                      },
                  ]
                : []),
            ...(user.role === 'agent'
                ? [{ key: 'customer-accounts', href: route('customer-accounts.create'), active: route().current('customer-accounts.*'), label: 'Customers' }]
                : []),
            {
                key: 'settings',
                href: route('settings.edit'),
                active: route().current('settings.*'),
                label: 'Settings',
            },
        ],
        [user.role],
    );

    return (
        <div className="min-h-screen bg-background">
            <a
                href="#main-content"
                className="sr-only focus-visible:not-sr-only focus-visible:absolute focus-visible:left-2 focus-visible:top-2 focus-visible:z-50 focus-visible:rounded-md focus-visible:bg-primary focus-visible:px-4 focus-visible:py-2 focus-visible:text-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
            >
                Skip to main content
            </a>
            <nav className="sticky top-0 z-40 border-b border-border bg-background">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="relative flex h-16 justify-between">
                        <div className="pointer-events-none absolute inset-x-0 flex h-16 items-center justify-center sm:hidden">
                            <Link href="/" className="pointer-events-auto">
                                <img
                                    src="/images/TM Horizontal Lockup_Transparent BG.png"
                                    alt="Logo"
                                    className="block h-14 w-auto"
                                />
                            </Link>
                        </div>

                        <div className="flex">
                            <div className="flex items-center gap-2 sm:hidden">
                                <button
                                    onClick={() =>
                                        setShowingNavigationDropdown(
                                            (previousState) => !previousState,
                                        )
                                    }
                                    aria-label={showingNavigationDropdown ? 'Close menu' : 'Open menu'}
                                    className="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-muted-foreground transition duration-150 ease-in-out hover:text-foreground focus:outline-none focus-visible:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <MorphIcon
                                        aria-hidden="true"
                                        icon={showingNavigationDropdown ? XIconNode : MenuIconNode}
                                        spring="snappy"
                                        reducedMotion="user"
                                        size={24}
                                        strokeWidth={2}
                                    />
                                </button>

                                <ThemeToggle />
                            </div>

                            <div className="hidden shrink-0 items-center sm:flex">
                                <Link href="/">
                                    <img
                                        src="/images/TM Horizontal Lockup_Transparent BG.png"
                                        alt="Logo"
                                        className="block h-14 w-auto"
                                    />
                                </Link>
                            </div>

                            <div className="hidden sm:-my-px sm:ms-10 sm:flex sm:gap-6">
                                {navTabs.map((tab) => (
                                    <Link
                                        key={tab.key}
                                        href={tab.href}
                                        className={`inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors ${
                                            tab.active
                                                ? 'border-primary text-foreground'
                                                : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                                        }`}
                                    >
                                        {tab.label}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-center gap-2 sm:hidden">
                            <AccountDropdown
                                items={messageAccountItems}
                                value=""
                                onChange={(value) => {
                                    const item = messageAccountItems.find(
                                        (candidate) => candidate.value === value,
                                    );
                                    if (!item || item.disabled) return;

                                    if (item.channel === 'facebook') {
                                        openChat({
                                            channel: 'facebook',
                                            threadId: item.threadId,
                                            name: item.label,
                                            hint: item.hint,
                                            viewerUserId: user.id,
                                        });
                                        return;
                                    }

                                    openChat({
                                        channel: 'portal',
                                        customerId: item.customerId,
                                        staffUserId: item.staffUserId,
                                        name: item.label,
                                        hint: item.hint,
                                        viewerIsCompany: user.role !== 'customer',
                                        viewerUserId: user.id,
                                    });
                                }}
                                label="Choose an account to message"
                                emptyLabel="No message accounts found"
                                menuTitle={({ close }) => (
                                    <div className="flex w-full items-center justify-between gap-4">
                                        <span>Chats</span>
                                        <div className="flex items-center gap-1">
                                            <NotificationBell
                                                count={0}
                                                size={36}
                                                label="Mark all as read"
                                                icon={
                                                    <CheckCheck
                                                        aria-hidden="true"
                                                        className="h-5 w-5"
                                                    />
                                                }
                                                onClick={markAllMessagesRead}
                                                disabled={unreadCount === 0}
                                                className="bg-transparent hover:bg-hover disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                                            />
                                            <NotificationBell
                                                count={0}
                                                size={36}
                                                label="New message"
                                                icon={
                                                    <SquarePen
                                                        aria-hidden="true"
                                                        className="h-5 w-5"
                                                    />
                                                }
                                                onClick={() => {
                                                    close(false);
                                                    setComposeOpen(true);
                                                }}
                                                className="bg-transparent hover:bg-hover"
                                            />
                                        </div>
                                    </div>
                                )}
                                menuWidth={340}
                                fullWidthOnMobile
                                searchable
                                searchPlaceholder="Search accounts"
                                align="right"
                                portal
                                closeOnScroll={false}
                                trigger={
                                    <span className="relative inline-flex h-10 w-10 items-center justify-center">
                                        <MessageCircle
                                            aria-hidden="true"
                                            className="h-[18px] w-[18px]"
                                        />
                                        <CountBadge
                                            total={unreadCount}
                                            max={99}
                                            size={40}
                                            color="red"
                                            dot={false}
                                            reduced={reducedMotion}
                                        />
                                    </span>
                                }
                                triggerClassName="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-transparent text-muted-foreground outline-none transition-colors hover:bg-hover focus-visible:ring-2 focus-visible:ring-ring"
                            />

                            <div ref={mobileNotificationsTriggerRef} className="inline-flex">
                                <NotificationBell
                                    count={notificationCount}
                                    size={40}
                                    icon={<Bell aria-hidden="true" className="h-5 w-5" />}
                                    className="bg-transparent text-muted-foreground hover:bg-hover"
                                    aria-expanded={notificationsOpen}
                                    onClick={() => setNotificationsOpen((previous) => !previous)}
                                />
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="flex items-center gap-1">
                                <ThemeToggle />

                                <AccountDropdown
                                    items={messageAccountItems}
                                    value=""
                                    onChange={(value) => {
                                        const item = messageAccountItems.find(
                                            (candidate) => candidate.value === value,
                                        );
                                        if (!item || item.disabled) return;

                                        if (item.channel === 'facebook') {
                                            openChat({
                                                channel: 'facebook',
                                                threadId: item.threadId,
                                                name: item.label,
                                                hint: item.hint,
                                                viewerUserId: user.id,
                                            });
                                            return;
                                        }

                                        openChat({
                                            channel: 'portal',
                                            customerId: item.customerId,
                                            staffUserId: item.staffUserId,
                                            name: item.label,
                                            hint: item.hint,
                                            viewerIsCompany: user.role !== 'customer',
                                            viewerUserId: user.id,
                                        });
                                    }}
                                    label="Choose an account to message"
                                    emptyLabel="No message accounts found"
                                    menuTitle={({ close }) => (
                                        <div className="flex w-full items-center justify-between gap-4">
                                            <span>Chats</span>
                                            <div className="flex items-center gap-1">
                                                <Tooltip
                                                    content="Mark all as read"
                                                    side="top"
                                                >
                                                    <NotificationBell
                                                        count={0}
                                                        size={36}
                                                        label="Mark all as read"
                                                        icon={
                                                            <CheckCheck
                                                                aria-hidden="true"
                                                                className="h-5 w-5"
                                                            />
                                                        }
                                                        onClick={markAllMessagesRead}
                                                        disabled={unreadCount === 0}
                                                        className="bg-transparent hover:bg-hover disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                                                    />
                                                </Tooltip>
                                                <Tooltip
                                                    content="New message"
                                                    side="top"
                                                >
                                                    <NotificationBell
                                                        count={0}
                                                        size={36}
                                                        label="New message"
                                                        icon={
                                                            <SquarePen
                                                                aria-hidden="true"
                                                                className="h-5 w-5"
                                                            />
                                                        }
                                                        onClick={() => {
                                                            close(false);
                                                            setComposeOpen(true);
                                                        }}
                                                        className="bg-transparent hover:bg-hover"
                                                    />
                                                </Tooltip>
                                            </div>
                                        </div>
                                    )}
                                    menuWidth={420}
                                    fullWidthOnMobile
                                    searchable
                                    searchPlaceholder="Search accounts"
                                    align="right"
                                    portal
                                    closeOnScroll={false}
                                    trigger={
                                        <span className="relative inline-flex h-9 w-9 items-center justify-center">
                                            <MessageCircle
                                                aria-hidden="true"
                                                className="h-[18px] w-[18px]"
                                            />
                                            <CountBadge
                                                total={unreadCount}
                                                max={99}
                                                size={36}
                                                color="red"
                                                dot={false}
                                                reduced={reducedMotion}
                                            />
                                        </span>
                                    }
                                    triggerClassName="relative inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-muted-foreground outline-none transition-colors hover:bg-hover focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <div ref={notificationsTriggerRef} className="inline-flex">
                                    <NotificationBell
                                        count={notificationCount}
                                        size={36}
                                        icon={<Bell aria-hidden="true" className="h-5 w-5" />}
                                        className="bg-transparent text-muted-foreground hover:bg-hover"
                                        aria-expanded={notificationsOpen}
                                        onClick={() => setNotificationsOpen((previous) => !previous)}
                                    />
                                </div>
                            </div>

                            <div className="relative ms-2">
                                <AccountDropdown
                                    items={USER_MENU_ITEMS}
                                    value=""
                                    onChange={(action) => {
                                        if (action === 'profile') {
                                            router.visit(route('profile.show'));
                                        } else if (action === 'logout') {
                                            router.post(route('logout'));
                                        }
                                    }}
                                    label={user.full_name}
                                    placeholder="User actions"
                                    align="right"
                                    portal
                                    triggerClassName="flex h-9 select-none items-center gap-2 whitespace-nowrap rounded-md border border-transparent bg-background px-3 text-sm font-medium text-muted-foreground outline-none transition-colors hover:bg-hover hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>
                        </div>
                    </div>
                </div>

            </nav>

            {mobileNav.target &&
                createPortal(
                    <AnimatePresence>
                        {showingNavigationDropdown && (
                            <motion.div
                                key="mobile-nav"
                                {...mobileNav.overlayProps}
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: reducedMotion ? 0 : 0.2, ease: EASE }}
                                className="fixed inset-0 z-[70] bg-stone-900/40 sm:hidden"
                            >
                                <motion.div
                                    {...mobileNav.panelProps}
                                    aria-label="Navigation"
                                    initial={{ x: '-100%' }}
                                    animate={{ x: 0 }}
                                    exit={{ x: '-100%' }}
                                    transition={reducedMotion ? { duration: 0 } : OPEN_SPRING}
                                    className="flex h-full w-full max-w-xs flex-col overflow-y-auto bg-card shadow-xl outline-none"
                                >
                                    <div className="flex shrink-0 items-center justify-between border-b border-border px-4 py-3">
                                        <div className="min-w-0">
                                            <div className="truncate text-base font-medium text-foreground">
                                                {user.full_name}
                                            </div>
                                            <div className="truncate text-sm font-medium text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={closeMobileNav}
                                            aria-label="Close navigation"
                                            className="grid h-9 w-9 place-items-center rounded-md text-muted-foreground outline-none transition-colors hover:bg-hover hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <X aria-hidden="true" className="h-5 w-5" />
                                        </button>
                                    </div>

                                    <div className="space-y-1">
                                        {navTabs.map((tab) => (
                                            <ResponsiveNavLink
                                                key={tab.key}
                                                href={tab.href}
                                                active={tab.active}
                                                onClick={closeMobileNav}
                                            >
                                                {tab.label}
                                            </ResponsiveNavLink>
                                        ))}
                                    </div>

                                    <div className="border-t border-border pb-1 pt-4">
                                        <div className="space-y-1">
                                            <ResponsiveNavLink
                                                href={route('profile.show')}
                                                active={route().current('profile.show')}
                                                onClick={closeMobileNav}
                                            >
                                                Profile
                                            </ResponsiveNavLink>
                                            <ResponsiveNavLink
                                                method="post"
                                                href={route('logout')}
                                                as="button"
                                                onClick={closeMobileNav}
                                            >
                                                Log Out
                                            </ResponsiveNavLink>
                                        </div>
                                    </div>
                                </motion.div>
                            </motion.div>
                        )}
                    </AnimatePresence>,
                    mobileNav.target,
                )}

            {typeof document !== 'undefined' &&
                createPortal(
                    <AnimatePresence>
                        {notificationsOpen && notificationsPosition && (
                            <motion.div
                                ref={notificationsPanelRef}
                                role="menu"
                                aria-label="Notifications"
                                initial={
                                    reducedMotion
                                        ? { opacity: 0 }
                                        : { opacity: 0, scale: 0.94, y: -8 }
                                }
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{
                                    opacity: 0,
                                    scale: 0.97,
                                    y: -6,
                                    transition: reducedMotion
                                        ? { duration: 0 }
                                        : { duration: 0.12, ease: EXIT_EASE },
                                }}
                                transition={
                                    reducedMotion
                                        ? { duration: 0 }
                                        : { ...OPEN_SPRING, opacity: { duration: 0.12, ease: EASE } }
                                }
                                style={{
                                    position: 'fixed',
                                    top: notificationsPosition.top,
                                    left: notificationsPosition.left,
                                    width: notificationsPosition.width,
                                    transformOrigin: 'top right',
                                }}
                                className="z-[60] overflow-hidden rounded-xl border border-border bg-card shadow-[0_1px_2px_rgba(28,25,23,0.06),0_16px_36px_-18px_rgba(28,25,23,0.5)] dark:shadow-[0_2px_12px_rgba(0,0,0,0.6)]"
                            >
                                <div className="flex items-center justify-between gap-4 border-b border-border px-4 py-3">
                                    <h2 className="text-[15px] font-semibold text-foreground">Notifications</h2>
                                    <Tooltip content="Mark all as read" side="top">
                                        <NotificationBell
                                            count={0}
                                            size={36}
                                            label="Mark all as read"
                                            icon={<CheckCheck aria-hidden="true" className="h-5 w-5" />}
                                            onClick={markAllNotificationsRead}
                                            disabled={notificationCount === 0}
                                            className="bg-transparent hover:bg-hover disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                                        />
                                    </Tooltip>
                                </div>

                                <div className="max-h-[min(60vh,420px)] overflow-y-auto p-3">
                                    {/* Sourced from purchase_order_notifications via
                                        OrderNotificationFeed. Chat messages are deliberately not
                                        listed here; they belong to the Chats icon instead. */}
                                    {orderNotifications.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center px-4 py-8 text-center">
                                            <div className="grid h-11 w-11 place-items-center rounded-full bg-muted text-muted-foreground">
                                                <Bell aria-hidden="true" className="h-5 w-5" />
                                            </div>
                                            <h3 className="mt-3 text-[13px] font-medium text-foreground">You're all caught up</h3>
                                            <p className="mt-1 max-w-xs text-sm text-muted-foreground">
                                                New activity on your orders will appear here.
                                            </p>
                                        </div>
                                    ) : (
                                        <ul
                                            className="relative flex flex-col gap-1"
                                            onPointerLeave={clearNotificationHighlight}
                                            onBlur={(event) => {
                                                if (!event.currentTarget.contains(event.relatedTarget)) clearNotificationHighlight();
                                            }}
                                        >
                                            <motion.li
                                                aria-hidden="true"
                                                className="pointer-events-none absolute inset-x-0 top-0 rounded-[7px] bg-hover"
                                                initial={false}
                                                animate={notificationHighlight}
                                                transition={reducedMotion
                                                    ? { duration: 0 }
                                                    : { type: 'spring', stiffness: 700, damping: 46, mass: 0.5, opacity: { duration: 0.1, ease: EASE } }}
                                            />
                                            {orderNotifications.map((notification) => (
                                                <li
                                                    key={notification.id}
                                                    className="relative"
                                                    onPointerMove={highlightNotification}
                                                    onFocus={highlightNotification}
                                                >
                                                    <Link
                                                        href={
                                                            notification.order_id
                                                                ? route('purchase-orders.show', notification.order_public_id)
                                                                : '#'
                                                        }
                                                        onClick={() => {
                                                            markNotificationRead(notification);
                                                            closeNotifications();
                                                        }}
                                                        className="relative block rounded-[7px] py-2.5 pl-3 pr-8 text-left"
                                                    >
                                                        {notification.is_unread && (
                                                            <span className="absolute right-3 top-4 h-2 w-2 rounded-full bg-destructive">
                                                                <span className="sr-only">Unread</span>
                                                            </span>
                                                        )}
                                                        <p className="text-sm font-medium text-foreground">
                                                            {notification.note ?? 'Order updated'}
                                                        </p>
                                                        <p className="mt-0.5 text-[12px] text-muted-foreground">
                                                            {user.role === 'customer'
                                                                ? notification.transaction_number
                                                                : `PO ${notification.po_number}`} · {formatDateTime(notification.created_at)}
                                                        </p>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>,
                    document.body,
                )}

            <ComposeModal
                open={composeOpen}
                onClose={() => setComposeOpen(false)}
                accounts={composableAccounts}
            />

            <PullToRefresh>
                <FlashBanner />
                <PushRegistration />
                {user.password_change_recommended && (
                    <div className="border-b border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="status">
                        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6 lg:px-8">
                            <p>
                                <span className="font-semibold">Protect your new account.</span>{' '}
                                Change the password provided when your account was created.
                            </p>
                            <Link
                                href={route('settings.edit')}
                                className="shrink-0 font-semibold underline underline-offset-4"
                            >
                                Change password
                            </Link>
                        </div>
                    </div>
                )}
                {banner}

                {header && (
                    <header className="border-b border-border bg-background">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main id="main-content" tabIndex={-1} className="bg-background focus:outline-none">{children}</main>

                <FooterSimple
                    companyName="Theomeds Marketing Inc."
                    logoSrc="/images/TM Horizontal Lockup_Transparent BG.png"
                    description="Delay is not an Option"
                    linkGroups={[
                        {
                            heading: 'Navigate',
                            items: [
                                { name: 'Dashboard', url: route('dashboard') },
                                { name: 'Orders', url: route('purchase-orders.index') },
                                { name: 'FAQ', url: route('faq') },
                                { name: "What's New", url: route('whats-new') },
                                { name: 'Terms & Privacy', url: route('terms-and-privacy') },
                            ],
                        },
                        {
                            heading: 'Contacts',
                            items: [
                                {
                                    name: 'Theomedsmktg@gmail.com',
                                    url: 'mailto:Theomedsmktg@gmail.com',
                                },
                            ],
                        },
                    ]}
                    feedbackEmail="Theomedsmktg@gmail.com"
                    social={{ facebook: 'https://www.facebook.com/profile.php?id=61560877803829' }}
                    copyright={`© ${new Date().getFullYear()} Theomeds Marketing Inc. All rights reserved.`}
                />
            </PullToRefresh>
        </div>
    );
}
