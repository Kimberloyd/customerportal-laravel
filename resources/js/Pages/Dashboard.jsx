import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashBanner from '@/components/FlashBanner';
import { AnimatedBadge } from '@/components/motion/animated-badge';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { usePurchaseOrderRealtime } from '@/hooks/usePurchaseOrderRealtime';
import { formatDateTime, statusBadge } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardList,
    MessageSquare,
    Plus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BarChart } from '../../../components/spectrumui/charts/bar-chart';
import { CalendarHeatmap } from '../../../components/spectrumui/charts/calendar-heatmap';
import { CohortChart } from '../../../components/spectrumui/charts/cohort-chart';
import { HistogramChart } from '../../../components/spectrumui/charts/histogram-chart';
import { StatCards } from '../../../components/spectrumui/charts/stat-cards';

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

            <section aria-label="Your order summary">
                <StatCards cards={dashboard.metrics} />
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

// One measure, so the bars carry no identity of their own -- the heading names
// them and the legend would only repeat it.
const AGING_SERIES = [{ key: 'orders', label: 'Open orders' }];

function ChartLabel({ children }) {
    return (
        <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-primary">
            {children}
        </p>
    );
}

// Stands in for the whole card, not just the plot -- a chart's own heading and
// legend would otherwise render real-looking zeroes over a skeleton grid.
function ChartSkeleton({ height }) {
    return (
        <div className="animate-pulse" aria-hidden="true">
            <div className="h-5 w-48 rounded bg-muted" />
            <div className="mt-2 h-4 w-32 rounded bg-muted" />
            <div className="mt-5 rounded-lg bg-muted" style={{ height }} />
        </div>
    );
}

function ChartCard({ category, loading = false, skeletonHeight = 260, children }) {
    return (
        <section aria-busy={loading || undefined}>
            <ChartLabel>{category}</ChartLabel>
            <div className="rounded-xl border border-border bg-card p-5">
                {loading ? <ChartSkeleton height={skeletonHeight} /> : children}
            </div>
        </section>
    );
}

function CompanyDashboard({ dashboard, charts }) {
    usePurchaseOrderRealtime(null, {
        // Ask for the deferred charts by name too, otherwise a realtime refresh
        // would replace them with nothing and strand every chart in its skeleton.
        only: ['companyDashboard', 'companyCharts'],
    });

    // Absent until the deferred request lands -- that gap is the skeleton.
    const loading = charts === undefined;

    const orderActivity = charts?.order_activity ?? [];
    const leadTimes = charts?.lead_times ?? [];
    const openOrderAging = charts?.open_order_aging ?? [];
    const reorderCohorts = charts?.reorder_cohorts ?? [];

    // The activity series is always a dense 365 days, so its length says nothing
    // about whether anything actually happened.
    const hasActivity = useMemo(
        () => orderActivity.some((day) => day.value > 0),
        [orderActivity],
    );

    const openOrderTotal = useMemo(
        () => openOrderAging.reduce((total, bucket) => total + bucket.orders, 0),
        [openOrderAging],
    );

    // Lead times run from hours to weeks depending on the customer, so the axis
    // picks one unit for the whole plot rather than mixing them per tick.
    const formatLeadTime = useMemo(() => {
        const longest = leadTimes.reduce((max, hours) => Math.max(max, hours), 0);

        return longest >= 72
            ? (value) => `${(value / 24).toFixed(value < 24 ? 1 : 0)}d`
            : (value) => `${Math.round(value)}h`;
    }, [leadTimes]);

    return (
        <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <section>
                <ChartLabel>Stats</ChartLabel>
                <StatCards cards={dashboard.metrics} />
            </section>

            <ChartCard category="Calendar Heatmap" loading={loading} skeletonHeight={175}>
                <CalendarHeatmap
                    data={orderActivity}
                    // The default 13px cap leaves a year's 53 columns well short
                    // of a full-width card. This is an upper bound, not a fixed
                    // size -- the component still shrinks cells on narrow screens.
                    cell={20}
                    label="order updates"
                    periodLabel="this year"
                    currentThrough={charts?.activity_through}
                    status={hasActivity ? 'ready' : 'empty'}
                />
            </ChartCard>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard category="Bar Chart" loading={loading} skeletonHeight={260}>
                    <SectionHeading
                        title="Open Order Aging"
                        description="How long open orders have been waiting since submission."
                    />
                    <div className="mt-6">
                        {openOrderTotal === 0 ? (
                            <EmptyState>
                                Nothing is waiting right now. Orders appear here while they are being
                                reviewed or fulfilled.
                            </EmptyState>
                        ) : (
                            <BarChart
                                data={openOrderAging}
                                categoryKey="bucket"
                                series={AGING_SERIES}
                                layout="horizontal"
                                categoryWidth={96}
                                showLegend={false}
                            />
                        )}
                    </div>
                </ChartCard>

                <ChartCard category="Histogram" loading={loading} skeletonHeight={320}>
                    <HistogramChart
                        data={leadTimes}
                        label="Fulfillment lead time"
                        sampleLabel="completed orders"
                        format={formatLeadTime}
                        status={leadTimes.length > 0 ? 'ready' : 'empty'}
                    />
                </ChartCard>
            </div>

            <ChartCard category="Cohort" loading={loading} skeletonHeight={260}>
                <CohortChart
                    data={reorderCohorts}
                    period="Month"
                    memberLabel="customers"
                    label="Customer reorder retention"
                    status={reorderCohorts.length > 0 ? 'ready' : 'empty'}
                />
            </ChartCard>
        </div>
    );
}

export default function Dashboard({
    companyDashboard = null,
    companyCharts = undefined,
    customerDashboard = null,
}) {
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
            {companyDashboard && (
                <CompanyDashboard dashboard={companyDashboard} charts={companyCharts} />
            )}
            {customerDashboard && <CustomerDashboard dashboard={customerDashboard} />}
        </AuthenticatedLayout>
    );
}
