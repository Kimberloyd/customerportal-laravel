import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AttentionPanel, OrderStages, percentDelta, pointsDelta, PrimaryMetricCard, SecondaryMetricsCard } from '@/components/dashboard/OverviewPanels';
import OrderTrend from '@/components/dashboard/OrderTrend';
import { useDashboardRealtime } from '@/hooks/useDashboardRealtime';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CalendarDays, RefreshCw } from 'lucide-react';
import { MotionConfig, motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';

const number = new Intl.NumberFormat('en-PH');
const date = (value) => new Date(`${value}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });

export default function Dashboard({ dashboard, workspace }) {
    const reducedMotion = useReducedMotion();
    const [loading, setLoading] = useState(false);
    useDashboardRealtime({ onStart: () => setLoading(true), onFinish: () => setLoading(false) });
    const customer = workspace.is_customer;
    const { current, previous, period } = dashboard;
    const ordersUrl = route('purchase-orders.index', { date_filter: 'custom', start_date: dashboard.start, end_date: dashboard.end });
    const enter = (delay) => ({
        initial: reducedMotion ? false : { opacity: 0, y: 12 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: reducedMotion ? 0 : 0.4, delay: reducedMotion ? 0 : delay, ease: [0.23, 1, 0.32, 1] },
    });
    const visit = (days) => router.get(route('dashboard'), { period: days }, {
        only: ['dashboard', 'workspace'], preserveState: true, preserveScroll: true,
        onStart: () => setLoading(true), onFinish: () => setLoading(false),
    });
    const inProgressCurrent = current.stages.pending + current.stages.fulfillment;
    const inProgressPrevious = previous.stages.pending + previous.stages.fulfillment;
    const metrics = [
        { label: customer ? 'Orders placed' : 'Orders received', value: number.format(current.orders), href: ordersUrl, delta: percentDelta(current.orders, previous.orders) },
        { label: 'Orders in progress', value: number.format(inProgressCurrent), href: `${ordersUrl}&status=active`, delta: percentDelta(inProgressCurrent, inProgressPrevious) },
        { label: 'Completed orders', value: number.format(current.completed), href: `${ordersUrl}&status=completed`, delta: percentDelta(current.completed, previous.completed) },
        { label: customer ? 'Your delivery progress' : 'Quantity fulfilled', value: current.fulfillment === null ? '—' : `${number.format(current.fulfillment)}%`, href: ordersUrl, delta: pointsDelta(current.fulfillment, previous.fulfillment) },
    ];

    return (
        <AuthenticatedLayout>
            <Head title={customer ? 'Your overview' : 'Company overview'} />
            <MotionConfig reducedMotion="user">
                <div className="min-h-[70vh] border-b border-stone-200/70 bg-white dark:border-white/10 dark:bg-[#151619]">
                    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                        <motion.div {...enter(0)} className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-primary dark:text-indigo-300">{customer ? workspace.name || 'Customer workspace' : 'Company workspace'}</p>
                                <h1 className="text-2xl font-semibold leading-tight tracking-tight text-stone-900 sm:text-3xl dark:text-stone-100">{customer ? 'Your orders, at a glance.' : 'Company overview'}</h1>
                                <p className="mt-2 max-w-xl text-sm leading-6 text-stone-600 dark:text-stone-400">
                                    {number.format(current.orders)} order{current.orders === 1 ? '' : 's'} {customer ? 'placed' : 'received'} in the last {period} days
                                    {dashboard.attention_count > 0 && `, ${number.format(dashboard.attention_count)} need${dashboard.attention_count === 1 ? 's' : ''} your attention`}.
                                </p>
                            </div>
                        </motion.div>
                        {!workspace.can_order && <div role="status" className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Your account needs an active customer profile before you can place or view orders. Contact your company representative to link your account.</div>}
                        <motion.div {...enter(0.04)} className="mb-5 mt-7 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400"><CalendarDays aria-hidden="true" className="h-4 w-4" /><span>{date(dashboard.start)} – {date(dashboard.end)}, {dashboard.end.slice(0, 4)}</span><span className="text-xs text-stone-500">UTC</span></div>
                            <div role="group" aria-label="Dashboard date range" className="flex rounded-xl border border-stone-200 bg-white p-1 dark:border-white/10 dark:bg-[#1d1e22]">
                                {[7, 30, 90].map((days) => <button key={days} type="button" disabled={loading} aria-pressed={period === days} onClick={() => visit(days)} className={`relative min-h-9 rounded-lg px-4 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-wait ${period === days ? 'text-white' : 'text-stone-600 hover:text-stone-900 dark:text-stone-300'}`}>
                                    {period === days && <motion.span layoutId="dashboard-period" className="absolute inset-0 rounded-lg bg-primary" transition={{ duration: reducedMotion ? 0 : 0.2 }} />}<span className="relative">{days} days</span>
                                </button>)}
                            </div>
                        </motion.div>
                        <div aria-busy={loading} className={`space-y-5 transition-opacity ${loading ? 'opacity-60' : ''}`}>
                            <div className="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-5">
                                <motion.div {...enter(0.06)} className="lg:col-span-3">
                                    <PrimaryMetricCard {...metrics[0]} href={workspace.can_order ? metrics[0].href : null} period={period} trend={dashboard.trend} />
                                </motion.div>
                                <motion.div {...enter(0.1)} className="lg:col-span-2">
                                    <SecondaryMetricsCard metrics={metrics.slice(1).map((metric) => ({ ...metric, href: workspace.can_order ? metric.href : null }))} reducedMotion={reducedMotion} />
                                </motion.div>
                            </div>
                            <motion.div {...enter(0.2)}>
                                <section aria-labelledby="order-trend-heading" className="min-w-0 rounded-2xl border border-stone-200/80 bg-white dark:border-white/10 dark:bg-[#1d1e22]">
                                    <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6">
                                        <div><h2 id="order-trend-heading" className="type-section-heading text-stone-900 dark:text-stone-100">Order activity</h2><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">Daily orders placed and completed deliveries.</p></div>
                                        {workspace.can_order && <Link href={ordersUrl} className="inline-flex items-center gap-1.5 rounded text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-300">View orders <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link>}
                                    </div>
                                    <OrderTrend trend={dashboard.trend} empty={dashboard.trend.every((point) => point.current === 0 && point.delivered === 0)} reducedMotion={reducedMotion} />
                                    <OrderStages current={current} previous={previous} ordersUrl={ordersUrl} reducedMotion={reducedMotion} />
                                </section>
                            </motion.div>
                            <motion.div {...enter(0.24)}>
                                <AttentionPanel orders={dashboard.attention} count={dashboard.attention_count} customer={customer} reducedMotion={reducedMotion} canOrder={workspace.can_order} />
                            </motion.div>
                        </div>
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-2 text-xs leading-5 text-stone-500 dark:text-stone-400">
                            <p>Progress reflects the latest status of orders placed in the selected period.</p>
                            <button type="button" disabled={loading} onClick={() => visit(period)} className="inline-flex min-h-9 items-center gap-2 rounded-lg px-2 hover:bg-stone-200/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-wait"><RefreshCw aria-hidden="true" className={`h-3.5 w-3.5 ${loading && !reducedMotion ? 'animate-spin' : ''}`} />{loading ? 'Updating overview…' : `Updated ${new Date(dashboard.updated_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })} · Refresh`}</button>
                        </div>
                        <span role="status" className="sr-only">{loading ? 'Updating dashboard.' : `Showing the last ${period} days.`}</span>
                    </div>
                </div>
            </MotionConfig>
        </AuthenticatedLayout>
    );
}
