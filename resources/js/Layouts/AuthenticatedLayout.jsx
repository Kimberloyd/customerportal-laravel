import echo from '@/echo';
import FlashBanner from '@/components/FlashBanner';
import ResponsiveNavLink from '@/components/ResponsiveNavLink';
import { Dropdown as AccountDropdown } from '@/components/interior/dropdown';
import { Drawer } from '@/components/motion/drawer';
import { Tooltip } from '@/components/motion/tooltip';
import ComposeModal from '@/components/messaging/ComposeModal';
import { FooterSimple } from '@/components/smoothui/footer-1';
import { CountBadge, NotificationBell } from '@/components/ui/notification-bell';
import { useChatWidget } from '@/lib/chat-widget-context';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, LogOut, MessageCircle, MonitorX, SquarePen, X } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

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

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const { openChat, readSignal, composeOpen, setComposeOpen, setIsAuthenticated } = useChatWidget();
    const reducedMotion = useReducedMotion() ?? false;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [notificationDrawerOpen, setNotificationDrawerOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [messageAccounts, setMessageAccounts] = useState(null);
    const [messageAccountsError, setMessageAccountsError] = useState(false);
    const mountedRef = useRef(true);

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
            intervalId = setInterval(fetchUnreadCount, 30000);
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
        }

        return () => {
            stopPolling();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
            if (channel) {
                channel.stopListening('.customer-message.created', refreshFromLiveEvent);
                echo.leave(`users.${user.id}`);
            }
        };
    }, [user.id, fetchUnreadCount, fetchMessageAccounts]);

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
                    className="block size-2 rounded-full bg-indigo-500"
                />
            ) : undefined;

            if (recipient.channel === 'facebook') {
                return {
                    value: `fb-${recipient.thread_id}`,
                    label: String(recipient.name).toLocaleUpperCase(),
                    hint: recipient.linked_agent_name
                        ? `FACEBOOK · ${String(recipient.linked_agent_name).toLocaleUpperCase()}`
                        : 'FACEBOOK · UNLINKED',
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

    // Composing a new message only makes sense for accounts you can actually
    // start a fresh conversation with -- Facebook contacts are existing
    // threads only (Meta requires them to have messaged first), and the
    // loading/error/empty rows are placeholders, not real accounts.
    const composableAccounts = useMemo(
        () => messageAccountItems.filter((item) => !item.disabled && item.channel !== 'facebook'),
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
        ],
        [user.role],
    );

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
                                                ? 'border-indigo-500 text-gray-900'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                        }`}
                                    >
                                        {tab.label}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
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
                                    menuTitle={
                                        <div className="flex w-full items-center justify-between gap-4">
                                            <span>Chats</span>
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
                                                    onClick={() => setComposeOpen(true)}
                                                    className="bg-transparent hover:bg-gray-100"
                                                />
                                            </Tooltip>
                                        </div>
                                    }
                                    menuWidth={420}
                                    searchable
                                    searchPlaceholder="Search accounts"
                                    align="right"
                                    portal
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
                                    triggerClassName="relative inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-gray-500 outline-none transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-300"
                                />
                                <NotificationBell
                                    count={unreadCount}
                                    size={36}
                                    icon={<Bell aria-hidden="true" className="h-5 w-5" />}
                                    className="bg-transparent text-gray-500 hover:bg-gray-100"
                                    onClick={() => setNotificationDrawerOpen(true)}
                                />
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
                                    triggerClassName="flex h-9 select-none items-center gap-2 whitespace-nowrap rounded-md border border-transparent bg-white px-3 text-sm font-medium text-gray-500 outline-none transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-gray-300"
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

            <Drawer
                open={notificationDrawerOpen}
                onOpenChange={setNotificationDrawerOpen}
                side="right"
                ariaLabel="Notifications"
                className="w-96 bg-white"
            >
                <div className="flex items-start justify-between border-b border-gray-200 px-6 py-5">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Notifications</h2>
                        <p className="mt-1 text-sm text-gray-500">Updates that need your attention.</p>
                    </div>
                    <button
                        type="button"
                        aria-label="Close notifications"
                        className="grid h-9 w-9 place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        onClick={() => setNotificationDrawerOpen(false)}
                    >
                        <X aria-hidden="true" className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex flex-1 flex-col overflow-y-auto p-6">
                    {unreadCount > 0 ? (
                        <div className="border border-gray-200 p-4">
                            <div className="flex items-start gap-3">
                                <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-100 text-gray-600">
                                    <MessageCircle aria-hidden="true" className="h-5 w-5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium text-gray-900">
                                        {unreadCount} unread {unreadCount === 1 ? 'message' : 'messages'}
                                    </p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Open the Chats icon in the header to read and respond.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => setNotificationDrawerOpen(false)}
                                        className="mt-3 inline-flex text-sm font-medium text-gray-900 underline-offset-4 hover:underline"
                                    >
                                        Got it
                                    </button>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
                            <div className="grid h-12 w-12 place-items-center rounded-full bg-gray-100 text-gray-500">
                                <Bell aria-hidden="true" className="h-6 w-6" />
                            </div>
                            <h3 className="mt-4 text-sm font-medium text-gray-900">No new notifications</h3>
                            <p className="mt-1 max-w-xs text-sm text-gray-500">
                                You are all caught up. New message updates will appear here.
                            </p>
                        </div>
                    )}
                </div>
            </Drawer>

            <ComposeModal
                open={composeOpen}
                onClose={() => setComposeOpen(false)}
                accounts={composableAccounts}
            />

            <FlashBanner />

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
                description="Delay is not an Option"
                linkGroups={[
                    {
                        heading: 'Navigate',
                        items: [
                            { name: 'Dashboard', url: route('dashboard') },
                            { name: 'Orders', url: route('purchase-orders.index') },
                        ],
                    },
                ]}
                social={{ facebook: 'https://www.facebook.com/profile.php?id=61560877803829' }}
                copyright={`© ${new Date().getFullYear()} Theomeds Marketing Inc. All rights reserved.`}
            />
        </div>
    );
}
