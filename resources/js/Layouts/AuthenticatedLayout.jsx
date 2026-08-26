import Dropdown from '@/components/Dropdown';
import FlashBanner from '@/components/FlashBanner';
import ResponsiveNavLink from '@/components/ResponsiveNavLink';
import { Drawer } from '@/components/motion/drawer';
import { FooterSimple } from '@/components/smoothui/footer-1';
import { NotificationBell } from '@/components/ui/notification-bell';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, MessageCircle, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [notificationDrawerOpen, setNotificationDrawerOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);

    useEffect(() => {
        let intervalId = null;

        const fetchUnreadCount = () => {
            fetch(route('messages.unread-count'))
                .then((res) => (res.ok ? res.json() : null))
                .then((data) => data && setUnreadCount(data.count))
                .catch(() => {});
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

        handleVisibilityChange();
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            stopPolling();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, []);

    const navTabs = useMemo(
        () => [
            { key: 'dashboard', href: route('dashboard'), active: route().current('dashboard'), label: 'Dashboard' },
            {
                key: 'purchase-orders',
                href: route('purchase-orders.index'),
                active: route().current('purchase-orders.*'),
                label: 'Orders',
            },
            {
                key: 'messages',
                href: route('messages.index'),
                active: route().current('messages.*'),
                label: (
                    <>
                        Messages
                        {unreadCount > 0 && (
                            <span className="ml-1.5 rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white">
                                {unreadCount}
                            </span>
                        )}
                    </>
                ),
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
        [user.role, unreadCount],
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
                                <NotificationBell
                                    count={unreadCount}
                                    size={36}
                                    label="Messages"
                                    icon={<MessageCircle aria-hidden="true" className="h-[18px] w-[18px]" />}
                                    className="bg-transparent hover:bg-gray-100"
                                    onClick={() => router.visit(route('messages.index'))}
                                />
                                <NotificationBell
                                    count={unreadCount}
                                    size={36}
                                    icon={<Bell aria-hidden="true" className="h-5 w-5" />}
                                    className="bg-transparent hover:bg-gray-100"
                                    onClick={() => setNotificationDrawerOpen(true)}
                                />
                            </div>

                            <div className="relative ms-2">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.full_name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('logout.all')}
                                            method="post"
                                            as="button"
                                        >
                                            Sign Out All Devices
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
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
                                        Open your inbox to read and respond.
                                    </p>
                                    <Link
                                        href={route('messages.index')}
                                        className="mt-3 inline-flex text-sm font-medium text-gray-900 underline-offset-4 hover:underline"
                                        onClick={() => setNotificationDrawerOpen(false)}
                                    >
                                        View messages
                                    </Link>
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
                            { name: 'Messages', url: route('messages.index') },
                        ],
                    },
                ]}
                social={{ facebook: 'https://www.facebook.com/profile.php?id=61560877803829' }}
                copyright={`© ${new Date().getFullYear()} Theomeds Marketing Inc. All rights reserved.`}
            />
        </div>
    );
}
