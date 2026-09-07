import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AttentionPanel, MetricCard, OrderStages, RecentOrders } from '@/components/dashboard/OverviewPanels';
import OrderTrend from '@/components/dashboard/OrderTrend';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDownToLine, ArrowRight, CalendarDays, CheckCheck, Clock3, Package, RefreshCw } from 'lucide-react';
import { MotionConfig, motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';

const number = new Intl.NumberFormat('en-PH');
const date = (value) => new Date(`${value}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });

export default function Dashboard({ dashboard, workspace }) {
    const { auth } = usePage().props;
    const reducedMotion = useReducedMotion();
    const [loading, setLoading] = useState(false);
    const customer = workspace.is_customer;
    const { current, previous, period } = dashboard;
    const firstName = auth.user.full_name?.trim().split(/\s+/)[0] || 'there';
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
    const metrics = [
        { label: customer ? 'Orders placed' : 'Orders received', value: number.format(current.orders), previous: number.format(previous.orders), icon: Package, note: 'All orders in this period', href: ordersUrl },
        { label: 'Orders in progress', value: number.format(current.stages.review + current.stages.fulfillment), previous: number.format(previous.stages.review + previous.stages.fulfillment), icon: Clock3, note: 'Awaiting review or full delivery', href: `${ordersUrl}&status=active` },
        { label: 'Completed orders', value: number.format(current.completed), previous: number.format(previous.completed), icon: CheckCheck, note: 'From orders placed in this period', href: `${ordersUrl}&status=completed` },
        { label: customer ? 'Your delivery progress' : 'Quantity fulfilled', value: current.fulfillment === null ? '—' : `${number.format(current.fulfillment)}%`, previous: previous.fulfillment === null ? 'No quantities' : `${number.format(previous.fulfillment)}%`, icon: ArrowDownToLine, note: `${number.format(current.delivered_units)} of ${number.format(current.ordered_units)} units delivered`, href: ordersUrl },
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
                                <p className="mt-2 max-w-xl text-sm leading-6 text-stone-600 dark:text-stone-400">{customer ? `Welcome back, ${firstName}. Follow your orders from request to delivery.` : `Welcome back, ${firstName}. Here’s where your orders stand.`}</p>
                            </div>
                        </motion.div>
                        {!workspace.can_order && <div role="status" className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Your account needs an active customer profile before you can place or view orders. Contact your company representative to link your account.</div>}
                        <motion.div {...enter(0.04)} className="mb-5 mt-7 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400"><CalendarDays aria-hidden="true" className="h-4 w-4" /><span>{date(dashboard.start)} – {date(dashboard.end)}, {dashboard.end.slice(0, 4)}</span><span className="text-xs text-stone-500">UTC</span></div>
                            <div role="group" aria-label="Dashboard date range" className="flex rounded-xl border border-stone-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-[#1d1e22]">
                                {[7, 30, 90].map((days) => <button key={days} type="button" disabled={loading} aria-pressed={period === days} onClick={() => visit(days)} className={`relative min-h-9 rounded-lg px-4 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-wait ${period === days ? 'text-white' : 'text-stone-600 hover:text-stone-900 dark:text-stone-300'}`}>
                                    {period === days && <motion.span layoutId="dashboard-period" className="absolute inset-0 rounded-lg bg-primary" transition={{ duration: reducedMotion ? 0 : 0.2 }} />}<span className="relative">{days} days</span>
                                </button>)}
                            </div>
                        </motion.div>
                        <div aria-busy={loading} className={`space-y-5 transition-opacity ${loading ? 'opacity-60' : ''}`}>
                            <div className="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2 sm:gap-4 lg:grid-cols-4">{metrics.map((metric, index) => <motion.div key={metric.label} {...enter(0.06 + index * 0.04)}><MetricCard {...metric} href={workspace.can_order ? metric.href : null} period={period} featured={index === 0} /></motion.div>)}</div>
                            <motion.div {...enter(0.2)} className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                                <section aria-labelledby="order-trend-heading" className="min-w-0 rounded-2xl border border-stone-200/80 bg-white shadow-[0_2px_8px_rgba(0,0,0,0.02)] lg:col-span-2 dark:border-white/10 dark:bg-[#1d1e22]">
                                    <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6">
                                        <div><h2 id="order-trend-heading" className="type-section-heading text-stone-900 dark:text-stone-100">Order activity</h2><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">Daily orders vs. the previous {period} days.</p></div>
                                        {workspace.can_order && <Link href={ordersUrl} className="inline-flex items-center gap-1.5 rounded text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-300">View orders <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link>}
                                    </div>
                                    <OrderTrend trend={dashboard.trend} empty={current.orders === 0 && previous.orders === 0} reducedMotion={reducedMotion} />
                                    <OrderStages current={current} previous={previous} ordersUrl={ordersUrl} reducedMotion={reducedMotion} />
                                </section>
                                <AttentionPanel orders={dashboard.attention} count={dashboard.attention_count} customer={customer} reducedMotion={reducedMotion} canOrder={workspace.can_order} />
                            </motion.div>
                            <motion.div {...enter(0.26)}><RecentOrders orders={dashboard.recent} customer={customer} ordersUrl={ordersUrl} canOrder={workspace.can_order} reducedMotion={reducedMotion} /></motion.div>
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
