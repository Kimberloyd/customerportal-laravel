import echo from '@/echo';
import FlashBanner from '@/components/FlashBanner';
import ResponsiveNavLink from '@/components/ResponsiveNavLink';
import { Dropdown as AccountDropdown } from '@/components/interior/dropdown';
import { Tooltip } from '@/components/motion/tooltip';
import ComposeModal from '@/components/messaging/ComposeModal';
import { CommandPalette } from '@/components/motion/command-palette';
import { FooterSimple } from '@/components/smoothui/footer-1';
import { CountBadge, NotificationBell } from '@/components/ui/notification-bell';
import { useChatWidget } from '@/lib/chat-widget-context';
import { formatDateTime } from '@/utils/orderDisplay';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Bell,
    CheckCheck,
    LayoutDashboard,
    LogOut,
    MessageCircle,
    MonitorX,
    Package,
    Plus,
    Search,
    ShieldCheck,
    SquarePen,
} from 'lucide-react';
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
        value: 'logout-all',
        label: 'Sign Out All Devices',
        icon: <MonitorX aria-hidden="true" className="h-4 w-4" />,
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
    const { openChat, readSignal, composeOpen, setComposeOpen, setIsAuthenticated } = useChatWidget();
    const reducedMotion = useReducedMotion() ?? false;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [notificationsPosition, setNotificationsPosition] = useState(null);
    const [paletteOpen, setPaletteOpen] = useState(false);
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
    const [messageAccounts, setMessageAccounts] = useState(null);
    const [messageAccountsError, setMessageAccountsError] = useState(false);
    const mountedRef = useRef(true);
    const notificationsTriggerRef = useRef(null);
    const notificationsPanelRef = useRef(null);

    const closeNotifications = useCallback(() => setNotificationsOpen(false), []);

    useEffect(() => {
        if (!notificationsOpen) {
            setNotificationsPosition(null);
            return;
        }

        fetchRecentNotifications();

        const updatePosition = () => {
            const rect = notificationsTriggerRef.current?.getBoundingClientRect();
            if (!rect) return;
            const left = Math.min(
                Math.max(8, rect.right - NOTIFICATIONS_PANEL_WIDTH),
                window.innerWidth - NOTIFICATIONS_PANEL_WIDTH - 8,
            );
            setNotificationsPosition({ top: rect.bottom + NOTIFICATIONS_PANEL_GAP, left });
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
                notificationsTriggerRef.current?.contains(target)
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
        return () => setIsAuthenticated(false);
    }, [setIsAuthenticated]);

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
        axios.post(route('notifications.mark-all-read')).catch(() => {
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
    useEffect(() => {
        if (readSignal === 0) return;
        fetchUnreadCount();
        fetchMessageAccounts();
    }, [readSignal, fetchUnreadCount, fetchMessageAccounts]);

    useEffect(() => {
        let intervalId = null;

        const refreshFromLiveEvent = () => {
            fetchUnreadCount();
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
                    threadId: recipient.thread_id,
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
                customerId: String(recipient.customer.id),
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
            {
                key: 'settings',
                href: route('settings.edit'),
                active: route().current('settings.*'),
                label: 'Settings',
            },
            {
                key: 'faq',
                href: route('faq'),
                active: route().current('faq'),
                label: 'FAQ',
            },
        ],
        [user.role],
    );

    // Everything the header palette can jump to.
    const commandItems = useMemo(() => {
        const navigate = (routeName) => () => router.visit(route(routeName));

        return [
            {
                id: 'nav-dashboard',
                label: 'Dashboard',
                group: 'Navigate',
                keywords: ['home', 'overview'],
                icon: LayoutDashboard,
                onSelect: navigate('dashboard'),
            },
            {
                id: 'nav-orders',
                label: 'Orders',
                group: 'Navigate',
                keywords: ['purchase', 'po'],
                icon: Package,
                onSelect: navigate('purchase-orders.index'),
            },
            {
                id: 'nav-faq',
                label: 'Frequently Asked Questions',
                group: 'Navigate',
                keywords: ['help', 'faq', 'support', 'questions'],
                icon: MessageCircle,
                onSelect: navigate('faq'),
            },
            ...(user.role === 'admin'
                ? [
                      {
                          id: 'nav-admin',
                          label: 'Admin',
                          group: 'Navigate',
                          keywords: ['products', 'customers', 'accounts', 'users'],
                          icon: ShieldCheck,
                          onSelect: navigate('admin.dashboard'),
                      },
                  ]
                : []),
            {
                id: 'action-new-order',
                label: 'New Order',
                group: 'Actions',
                keywords: ['create', 'add', 'purchase order'],
                icon: Plus,
                onSelect: () => router.visit(route('purchase-orders.index', { create: 1 })),
            },
            {
                id: 'action-new-message',
                label: 'New Message',
                group: 'Actions',
                keywords: ['compose', 'chat', 'send'],
                icon: SquarePen,
                onSelect: () => setComposeOpen(true),
            },
        ];
    }, [user.role, setComposeOpen]);

    return (
        <div className="min-h-screen bg-white">
            <nav className="sticky top-0 z-40 border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
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
                                                ? 'border-primary text-gray-900'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                        }`}
                                    >
                                        {tab.label}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <button
                                type="button"
                                onClick={() => setPaletteOpen(true)}
                                aria-label="Search"
                                className="me-2 flex h-9 w-48 items-center gap-2 rounded-full border border-gray-200 bg-transparent pl-3.5 pr-4 text-sm text-gray-500 transition-colors hover:border-gray-400 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring lg:w-64"
                            >
                                <Search aria-hidden="true" className="h-4 w-4 shrink-0" />
                                <span className="truncate text-left">Search</span>
                            </button>

                            <div className="flex items-center gap-1">
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
                                                        className="bg-transparent hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
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
                                                        className="bg-transparent hover:bg-gray-100"
                                                    />
                                                </Tooltip>
                                            </div>
                                        </div>
                                    )}
                                    menuWidth={420}
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
                                    triggerClassName="relative inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-gray-500 outline-none transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <div ref={notificationsTriggerRef} className="inline-flex">
                                    <NotificationBell
                                        count={notificationCount}
                                        size={36}
                                        icon={<Bell aria-hidden="true" className="h-5 w-5" />}
                                        className="bg-transparent text-gray-500 hover:bg-gray-100"
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
                                        if (action === 'logout-all') {
                                            router.post(route('logout.all'));
                                        } else if (action === 'logout') {
                                            router.post(route('logout'));
                                        }
                                    }}
                                    label={user.full_name}
                                    placeholder="User actions"
                                    align="right"
                                    portal
                                    triggerClassName="flex h-9 select-none items-center gap-2 whitespace-nowrap rounded-md border border-transparent bg-white px-3 text-sm font-medium text-gray-500 outline-none transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('purchase-orders.index')}
                            active={route().current('purchase-orders.*')}
                        >
                            Orders
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.full_name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('settings.edit')}>
                                Settings
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                href={route('faq')}
                                active={route().current('faq')}
                            >
                                FAQ
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout.all')}
                                as="button"
                            >
                                Sign Out All Devices
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

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
                                    width: NOTIFICATIONS_PANEL_WIDTH,
                                    transformOrigin: 'top right',
                                }}
                                className="z-[60] overflow-hidden rounded-[11px] border border-stone-200 bg-white shadow-[0_1px_2px_rgba(28,25,23,0.06),0_16px_36px_-18px_rgba(28,25,23,0.5)] dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:shadow-[0_2px_12px_rgba(0,0,0,0.6)]"
                            >
                                <div className="flex items-center justify-between gap-4 border-b border-stone-200 px-4 py-3 dark:border-white/[0.16]">
                                    <h2 className="text-[15px] font-semibold text-stone-900 dark:text-stone-100">Notifications</h2>
                                    <Tooltip content="Mark all as read" side="top">
                                        <NotificationBell
                                            count={0}
                                            size={36}
                                            label="Mark all as read"
                                            icon={<CheckCheck aria-hidden="true" className="h-5 w-5" />}
                                            onClick={markAllNotificationsRead}
                                            disabled={notificationCount === 0}
                                            className="bg-transparent hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                                        />
                                    </Tooltip>
                                </div>

                                <div className="max-h-[min(60vh,420px)] overflow-y-auto p-3">
                                    {/* Sourced from purchase_order_notifications via
                                        OrderNotificationFeed. Chat messages are deliberately not
                                        listed here; they belong to the Chats icon instead. */}
                                    {orderNotifications.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center px-4 py-8 text-center">
                                            <div className="grid h-11 w-11 place-items-center rounded-full bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-400">
                                                <Bell aria-hidden="true" className="h-5 w-5" />
                                            </div>
                                            <h3 className="mt-3 text-[13px] font-medium text-stone-900 dark:text-stone-100">You're all caught up</h3>
                                            <p className="mt-1 max-w-xs text-[12.5px] text-stone-500 dark:text-stone-400">
                                                New activity on your orders will appear here.
                                            </p>
                                        </div>
                                    ) : (
                                        <ul className="flex flex-col gap-1">
                                            {orderNotifications.map((notification) => (
                                                <li key={notification.id}>
                                                    <Link
                                                        href={
                                                            notification.order_id
                                                                ? route('purchase-orders.show', notification.order_id)
                                                                : '#'
                                                        }
                                                        onClick={closeNotifications}
                                                        className="block rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-stone-100 dark:hover:bg-white/[0.06]"
                                                    >
                                                        <p className="text-[13px] font-medium text-stone-900 dark:text-stone-100">
                                                            {notification.note ?? 'Order updated'}
                                                        </p>
                                                        <p className="mt-0.5 text-[12px] text-stone-500 dark:text-stone-400">
                                                            PO {notification.po_number} · {formatDateTime(notification.created_at)}
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

            {/* shortcut={null}: the order form mounts its own product-search
                palette on Ctrl+K, and this one is mounted on every page -- a
                shared binding would open both at once. */}
            <CommandPalette
                items={commandItems}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
                shortcut={null}
                placeholder="Search pages and actions"
                emptyMessage="No matches found."
            />

            <ComposeModal
                open={composeOpen}
                onClose={() => setComposeOpen(false)}
                accounts={composableAccounts}
            />

            <FlashBanner />
            {banner}

            {header && (
                <header className="border-b border-gray-100 bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="bg-white">{children}</main>

            <FooterSimple
                companyName="Theomeds Marketing Inc."
                logoSrc="/images/TM Horizontal Lockup_Transparent BG.png"
                description="Delay is not an OPTION"
                linkGroups={[
                    {
                        heading: 'Navigate',
                        items: [
                            { name: 'Dashboard', url: route('dashboard') },
                            { name: 'Orders', url: route('purchase-orders.index') },
                            { name: 'FAQ', url: route('faq') },
                        ],
                    },
                ]}
                social={{ facebook: 'https://www.facebook.com/profile.php?id=61560877803829' }}
                copyright={`© ${new Date().getFullYear()} Theomeds Marketing Inc. All rights reserved.`}
            />
        </div>
    );
}
