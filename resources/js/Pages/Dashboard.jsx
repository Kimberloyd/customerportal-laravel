import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashBanner from '@/components/FlashBanner';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ChartColumn,
    CircleCheck,
    ClipboardList,
    Clock3,
    Eye,
    MessageSquare,
    PackageCheck,
    Plus,
} from 'lucide-react';
import { useMemo } from 'react';

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

function SummaryCard({ card, value }) {
    const Icon = card.icon;

    return (
        <article className="rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">{card.label}</p>
                    <p className="mt-2 text-3xl font-semibold tabular-nums text-foreground">{value}</p>
                </div>
                <span className={`grid size-10 shrink-0 place-items-center rounded-xl ${card.iconClassName}`}>
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">{card.description}</p>
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

function CompanyDashboard({ dashboard }) {
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
                    <SummaryCard key={card.key} card={card} value={dashboard.summary[card.key]} />
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
                    emptyState="No orders have been submitted yet."
                />
            </section>

            <section className="rounded-xl border border-border bg-card">
                <div className="border-b border-border p-5">
                    <SectionHeading
                        title="Recent Activity"
                        description="The latest recorded changes across purchase orders."
                    />
                </div>
                {dashboard.recent_activity.length === 0 ? (
                    <EmptyState>No order activity has been recorded yet.</EmptyState>
                ) : (
                    <ol className="divide-y divide-border">
                        {dashboard.recent_activity.map((activity) => (
                            <li key={activity.id} className="flex gap-4 px-5 py-4">
                                <span className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground">
                                    <Clock3 className="size-4" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <p className="font-medium text-foreground">
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
                                        <time className="shrink-0 text-xs text-muted-foreground">
                                            {formatDateTime(activity.created_at)}
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
                            </li>
                        ))}
                    </ol>
                )}
            </section>

        </div>
    );
}

export default function Dashboard({ companyDashboard = null }) {
    const submittedCount = companyDashboard?.summary.submitted ?? 0;

    return (
        <AuthenticatedLayout
            banner={submittedCount > 0 ? (
                <FlashBanner
                    message={`${submittedCount} submitted ${submittedCount === 1 ? 'order is' : 'orders are'} waiting for review.`}
                    variant="warning"
                />
            ) : null}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />
            {companyDashboard && <CompanyDashboard dashboard={companyDashboard} />}
        </AuthenticatedLayout>
    );
}
