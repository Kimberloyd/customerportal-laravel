import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashBanner from '@/components/FlashBanner';
import { Skeleton } from '@/components/loading-ui/skeleton';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Timeline, styleFor, timeLabel } from '@/components/timelines-activity-feed';
import { Button } from '@/components/ui/button';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ChartColumn,
    CircleCheck,
    ClipboardList,
    Eye,
    MessageSquare,
    PackageCheck,
    Plus,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const SUMMARY_CARDS = [
    {
        key: 'submitted',
        label: 'Submitted',
        description: 'Waiting for review',
        icon: ClipboardList,
        iconClassName: 'bg-info/10 text-info',
    },
    {
        key: 'reviewing',
        label: 'Reviewing',
        description: 'Currently being checked',
        icon: Eye,
        iconClassName: 'bg-primary/10 text-primary',
    },
    {
        key: 'partial',
        label: 'Partial',
        description: 'Still has a balance',
        icon: PackageCheck,
        iconClassName: 'bg-amber-500/10 text-amber-600',
    },
    {
        key: 'completed_today',
        label: 'Completed Today',
        description: 'Finished since midnight',
        icon: CircleCheck,
        iconClassName: 'bg-success/10 text-success',
    },
];

const CUSTOMER_SUMMARY_CARDS = [
    {
        key: 'active',
        label: 'Active Orders',
        description: 'Being reviewed or fulfilled',
        icon: ClipboardList,
        iconClassName: 'bg-info/10 text-info',
    },
    {
        key: 'in_progress',
        label: 'Partial Deliveries',
        description: 'Orders with items still due',
        icon: PackageCheck,
        iconClassName: 'bg-primary/10 text-primary',
    },
    {
        key: 'ready_to_confirm',
        label: 'Ready to Confirm',
        description: 'Completed deliveries to review',
        icon: Eye,
        iconClassName: 'bg-amber-500/10 text-amber-600',
    },
    {
        key: 'received',
        label: 'Received',
        description: 'Deliveries you confirmed',
        icon: CircleCheck,
        iconClassName: 'bg-success/10 text-success',
    },
];

const CUSTOMER_TABLE_ROW_HEIGHT = 48;
const CUSTOMER_TABLE_HEIGHT = 6 * CUSTOMER_TABLE_ROW_HEIGHT + 20;

function Status({ order }) {
    const badge = statusBadge(order.display_status ?? order.status);

    return (
        <AnimatedBadge
            status={badge.status}
            size="sm"
            pulse={false}
            className="border-0 bg-transparent px-0 shadow-none"
        >
            {badge.label}
        </AnimatedBadge>
    );
}

function SummaryCard({ card, value, loading }) {
    const Icon = card.icon;

    return (
        <article className="rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">{card.label}</p>
                    {loading ? (
                        <Skeleton className="mt-2 h-9 w-10" />
                    ) : (
                        <p className="mt-2 text-3xl font-semibold tabular-nums text-foreground">{value}</p>
                    )}
                </div>
                <span className={`grid size-10 shrink-0 place-items-center rounded-xl ${card.iconClassName}`}>
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </div>
            {loading ? (
                <Skeleton className="mt-3 h-4 w-28" />
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">{card.description}</p>
            )}
        </article>
    );
}

function SectionHeading({ title, description, action }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 className="text-lg font-semibold text-foreground">{title}</h3>
                {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}

function EmptyState({ children }) {
    return (
        <div className="flex min-h-44 items-center justify-center px-6 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}

function toActivityTimelineEntry(activity) {
    const { Icon, tone } = styleFor(activity.action);

    return {
        key: String(activity.id),
        createdAt: activity.created_at,
        icon: Icon,
        tone,
        render: () => (
            <div>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="text-sm font-medium text-foreground">
                        {activity.action}
                        {activity.po_number && (
                            <>
                                {' · '}
                                <Link
                                    href={route('purchase-orders.show', activity.order_id)}
                                    className="text-primary hover:underline"
                                >
                                    {activity.po_number}
                                </Link>
                            </>
                        )}
                    </p>
                    <time
                        dateTime={activity.created_at ?? undefined}
                        className="shrink-0 text-[11px] tabular-nums text-muted-foreground"
                    >
                        {timeLabel(activity.created_at)}
                    </time>
                </div>
                {activity.details && (
                    <p className="mt-1 text-sm text-foreground/80">{activity.details}</p>
                )}
                <p className="mt-1 text-xs text-muted-foreground">
                    {activity.actor_name ?? 'System'}
                    {activity.actor_role ? ` (${activity.actor_role})` : ''}
                </p>
            </div>
        ),
    };
}

function DeliveryProgress({ delivered, ordered }) {
    const percentage = ordered > 0
        ? Math.min(100, Math.round((delivered / ordered) * 100))
        : 0;

    return (
        <div className="mt-3">
            <div className="mb-1.5 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>{delivered} of {ordered} units delivered</span>
                <span className="tabular-nums">{percentage}%</span>
            </div>
            <div
                className="h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-label={`${delivered} of ${ordered} units delivered`}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percentage}
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-300"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

function CustomerDashboard({ dashboard }) {
    const [refreshingSummary, setRefreshingSummary] = useState(false);

    usePurchaseOrderRealtime(null, {
        only: ['customerDashboard'],
        onStart: () => setRefreshingSummary(true),
        onFinish: () => setRefreshingSummary(false),
    });

    const recentOrderColumns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                cell: (order) => (
                    <Link
                        href={route('purchase-orders.show', order.id)}
                        className="font-medium text-foreground transition-colors hover:text-primary"
                    >
                        {order.po_number}
                    </Link>
                ),
            },
            { key: 'status', header: 'Status', cell: (order) => <Status order={order} /> },
            {
                key: 'delivered_units',
                header: 'Delivered',
                cell: (order) => (
                    <span className="tabular-nums">
                        {order.delivered_units} of {order.ordered_units}
                    </span>
                ),
            },
            {
                key: 'balance_units',
                header: 'Balance',
                cell: (order) => <span className="tabular-nums">{order.balance_units}</span>,
            },
            {
                key: 'submitted_at',
                header: 'Submitted',
                cell: (order) => formatDateTime(order.submitted_at),
            },
        ],
        [],
    );

    if (!dashboard.linked) {
        return (
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <section className="flex min-h-80 flex-col items-center justify-center rounded-xl border border-border bg-card px-6 text-center">
                    <span className="grid size-12 place-items-center rounded-xl bg-muted text-muted-foreground">
                        <ClipboardList className="size-6" aria-hidden="true" />
                    </span>
                    <h3 className="mt-4 text-lg font-semibold text-foreground">
                        Your orders aren&apos;t available yet
                    </h3>
                    <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                        This account still needs to be connected to your customer record. Contact Theomeds support or your account administrator to finish setup.
                    </p>
                </section>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <section>
                <p className="text-sm font-medium text-primary">Order overview</p>
                <h3 className="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                    {dashboard.customer_name}
                </h3>
                <p className="mt-2 text-sm text-muted-foreground">
                    Track current deliveries and review the latest activity on your orders.
                </p>
            </section>

            <section aria-label="Your order summary" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {CUSTOMER_SUMMARY_CARDS.map((card) => (
                    <SummaryCard
                        key={card.key}
                        card={card}
                        value={dashboard.summary[card.key]}
                        loading={refreshingSummary}
                    />
                ))}
            </section>

            <section className="rounded-xl border border-border bg-card">
                <div className="border-b border-border p-5">
                    <SectionHeading
                        title="Confirm Completed Deliveries"
                        description="Review completed orders and confirm when the delivery has arrived."
                        action={(
                            <Link
                                href={route('purchase-orders.index', { status: 'completed' })}
                                className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                            >
                                View completed orders <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                        )}
                    />
                </div>
                {dashboard.action_required.length === 0 ? (
                    <EmptyState>No completed deliveries are waiting for confirmation.</EmptyState>
                ) : (
                    <ul className="divide-y divide-border">
                        {dashboard.action_required.map((order) => (
                            <li key={order.id}>
                                <Link
                                    href={route('purchase-orders.show', order.id)}
                                    className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium text-foreground">{order.po_number}</span>
                                            <Status order={order} />
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Completed delivery · {order.ordered_units} units
                                        </p>
                                    </div>
                                    <span className="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary">
                                        Review delivery <ArrowRight className="size-4" aria-hidden="true" />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-xl border border-border bg-card lg:col-span-2">
                    <div className="border-b border-border p-5">
                        <SectionHeading
                            title="Active Orders"
                            description="Orders currently being reviewed or fulfilled."
                            action={(
                                <Link
                                    href={route('purchase-orders.index', { status: 'active' })}
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    View all <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            )}
                        />
                    </div>
                    {dashboard.active_orders.length === 0 ? (
                        <EmptyState>No orders are currently being reviewed or fulfilled.</EmptyState>
                    ) : (
                        <ul className="divide-y divide-border">
                            {dashboard.active_orders.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('purchase-orders.show', order.id)}
                                        className="block px-5 py-4 transition-colors hover:bg-muted/50"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium text-foreground">{order.po_number}</span>
                                            <Status order={order} />
                                        </div>
                                        <DeliveryProgress
                                            delivered={order.delivered_units}
                                            ordered={order.ordered_units}
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-border bg-card p-5">
                    <SectionHeading
                        title="Quick Actions"
                        description="Start or find an order."
                    />
                    <div className="mt-5 grid gap-3">
                        <Button asChild variant="primary" className="w-full justify-start" leadingIcon={Plus}>
                            <Link href={route('purchase-orders.index', { create: 1 })}>Create Order</Link>
                        </Button>
                        <Button asChild variant="tertiary" className="w-full justify-start" leadingIcon={ClipboardList}>
                            <Link href={route('purchase-orders.index')}>View All Orders</Link>
                        </Button>
                        <div className="mt-2 flex items-start gap-3 rounded-xl bg-muted/60 p-4 text-sm text-muted-foreground">
                            <MessageSquare className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                            <p>Use the message icon in the header if you need help with an order.</p>
                        </div>
                    </div>
                </section>
            </div>

            <section className="space-y-4">
                <SectionHeading
                    title="Recent Orders"
                    description="Your five most recently submitted purchase orders."
                />
                <Table
                    data={dashboard.recent_orders}
                    columns={recentOrderColumns}
                    getRowId={(order) => String(order.id)}
                    className="[&>div]:!overflow-x-auto [&>div]:!overflow-y-hidden"
                    rowHeight={CUSTOMER_TABLE_ROW_HEIGHT}
                    height={CUSTOMER_TABLE_HEIGHT}
                    loading={refreshingSummary}
                    emptyState="No orders yet. Create an order to start tracking it here."
                />
            </section>
        </div>
    );
}

function CompanyDashboard({ dashboard }) {
    const [refreshingSummary, setRefreshingSummary] = useState(false);

    usePurchaseOrderRealtime(null, {
        only: ['companyDashboard'],
        onStart: () => setRefreshingSummary(true),
        onFinish: () => setRefreshingSummary(false),
    });

    const recentOrderColumns = useMemo(
        () => [
            {
                key: 'po_number',
                header: 'PO Number',
                cell: (order) => (
                    <Link
                        href={route('purchase-orders.show', order.id)}
                        className="font-medium text-foreground transition-colors hover:text-primary"
                    >
                        {order.po_number}
                    </Link>
                ),
            },
            { key: 'customer_name', header: 'Customer' },
            { key: 'status', header: 'Status', cell: (order) => <Status order={order} /> },
            {
                key: 'balance_units',
                header: 'Balance',
                cell: (order) => <span className="tabular-nums">{order.balance_units}</span>,
            },
            {
                key: 'submitted_at',
                header: 'Submitted',
                cell: (order) => formatDateTime(order.submitted_at),
            },
        ],
        [],
    );

    return (
        <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <section aria-label="Order summary" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {SUMMARY_CARDS.map((card) => (
                    <SummaryCard
                        key={card.key}
                        card={card}
                        value={dashboard.summary[card.key]}
                        loading={refreshingSummary}
                    />
                ))}
            </section>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-xl border border-border bg-card lg:col-span-2">
                    <div className="border-b border-border p-5">
                        <SectionHeading
                            title="Needs Attention"
                            description="Open orders that still require review or fulfillment."
                            action={(
                                <Link
                                    href={route('purchase-orders.index')}
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    View all <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            )}
                        />
                    </div>
                    {dashboard.needs_attention.length === 0 ? (
                        <EmptyState>No open orders need attention.</EmptyState>
                    ) : (
                        <ul className="divide-y divide-border">
                            {dashboard.needs_attention.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('purchase-orders.show', order.id)}
                                        className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium text-foreground">{order.po_number}</span>
                                                <Status order={order} />
                                            </div>
                                            <p className="mt-1 truncate text-sm text-muted-foreground">
                                                {order.customer_name}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-left sm:text-right">
                                            <p className="text-sm font-medium tabular-nums text-foreground">
                                                {order.delivered_units} of {order.ordered_units} units delivered
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Submitted {formatDateTime(order.submitted_at)}
                                            </p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-border bg-card p-5">
                    <SectionHeading
                        title="Quick Actions"
                        description="Common company tasks."
                    />
                    <div className="mt-5 grid gap-3">
                        <Button asChild variant="primary" className="w-full justify-start" leadingIcon={Plus}>
                            <Link href={route('purchase-orders.index', { create: 1 })}>Create Order</Link>
                        </Button>
                        <Button asChild variant="tertiary" className="w-full justify-start" leadingIcon={ClipboardList}>
                            <Link href={route('purchase-orders.index')}>View Orders</Link>
                        </Button>
                        <Button asChild variant="tertiary" className="w-full justify-start" leadingIcon={ChartColumn}>
                            <Link href={route('reports.overview')}>View Reports</Link>
                        </Button>
                        <div className="mt-2 flex items-start gap-3 rounded-xl bg-muted/60 p-4 text-sm text-muted-foreground">
                            <MessageSquare className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                            <p>Use the message icon in the header to contact a customer.</p>
                        </div>
                    </div>
                </section>
            </div>

            <section className="space-y-4">
                <SectionHeading
                    title="Recent Orders"
                    description="The five most recently submitted purchase orders."
                />
                <Table
                    data={dashboard.recent_orders}
                    columns={recentOrderColumns}
                    getRowId={(order) => String(order.id)}
                    loading={refreshingSummary}
                    emptyState="No orders have been submitted yet."
                />
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <SectionHeading
                    title="Recent Activity"
                    description="The latest recorded changes across purchase orders."
                />
                <div className="mt-5">
                    <Timeline
                        entries={dashboard.recent_activity.map(toActivityTimelineEntry)}
                        emptyTitle="No order activity has been recorded yet."
                        emptyDescription="Company order changes will appear here as they happen."
                    />
                </div>
            </section>

        </div>
    );
}

export default function Dashboard({ companyDashboard = null, customerDashboard = null }) {
    const submittedCount = companyDashboard?.summary.submitted ?? 0;
    const readyToConfirmCount = customerDashboard?.summary.ready_to_confirm ?? 0;
    const banner = submittedCount > 0
        ? (
            <FlashBanner
                message={`${submittedCount} submitted ${submittedCount === 1 ? 'order is' : 'orders are'} waiting for review.`}
                variant="warning"
            />
        )
        : readyToConfirmCount > 0
            ? (
                <FlashBanner
                    message={`${readyToConfirmCount} completed ${readyToConfirmCount === 1 ? 'order is' : 'orders are'} ready for your confirmation.`}
                    variant="warning"
                />
            )
            : null;

    return (
        <AuthenticatedLayout
            banner={banner}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />
            {companyDashboard && <CompanyDashboard dashboard={companyDashboard} />}
            {customerDashboard && <CustomerDashboard dashboard={customerDashboard} />}
        </AuthenticatedLayout>
    );
}
