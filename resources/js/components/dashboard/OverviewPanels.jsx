import { AnimatedBadge } from '@/components/motion/animated-badge';
import { LineChart } from '@/components/ui/line-chart';
import { statusBadge } from '@/utils/orderDisplay';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCheck, ClipboardCheck, Package, Truck } from 'lucide-react';
import { motion } from 'motion/react';
import { useLayoutEffect, useRef, useState } from 'react';

const number = new Intl.NumberFormat('en-PH');
const surface = 'rounded-2xl border border-stone-200/80 bg-white dark:border-white/10 dark:bg-[#1d1e22]';
const focus = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';
const spring = { type: 'spring', stiffness: 700, damping: 46, mass: 0.5 };

/**
 * Percent change vs. the previous period, for count-style metrics (orders,
 * completed orders). Null when there's no meaningful baseline to compare
 * against (previous period had zero and still has zero).
 */
export function percentDelta(current, previous) {
    if (current == null || previous == null) return null;
    if (previous === 0) return current === 0 ? null : { text: '+New' };
    const pct = (current - previous) / previous * 100;
    return { text: `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%` };
}

/**
 * Percentage-point change vs. the previous period, for metrics that are
 * already a percentage (e.g. fulfillment rate) -- a relative "%" delta on
 * a percentage is misleading, so this reports the raw point difference.
 */
export function pointsDelta(current, previous) {
    if (current == null || previous == null) return null;
    const diff = current - previous;
    return { text: `${diff >= 0 ? '+' : ''}${diff.toFixed(1)} pt` };
}

export function PrimaryMetricCard({ label, value, delta, href, period, trend }) {
    const Card = href ? Link : 'div';
    const sparkline = (trend ?? []).map((point) => ({ value: point.current }));
    return (
        <Card href={href || undefined} className={`${surface} ${focus} group relative flex h-full flex-col overflow-hidden p-5 transition-colors ${href ? 'hover:bg-stone-50 dark:hover:bg-white/[0.03]' : ''} sm:p-6`}>
            <p className="text-sm font-medium text-stone-600 dark:text-stone-300">{label}</p>
            <div className="flex flex-1 items-center justify-between gap-4">
                <div className="min-w-0">
                    <p className="break-words text-5xl font-semibold leading-tight tracking-tight text-stone-900 tabular-nums sm:text-6xl dark:text-stone-100">{value}</p>
                    {delta && <p className="mt-2 text-xs font-medium text-stone-500 dark:text-stone-400">{delta.text} vs previous {period} days</p>}
                </div>
                {sparkline.length > 1 && (
                    <div className="pointer-events-none h-24 w-52 shrink-0 sm:h-28 sm:w-64" aria-hidden="true">
                        <LineChart
                            data={sparkline}
                            dataKey="value"
                            config={{ value: { color: '#10b981' } }}
                            containerHeight={112}
                            hideXAxis
                            hideYAxis
                            hideGridLines
                            tooltip={false}
                            legend={false}
                            connectNulls
                            // Order counts are small integers -- an
                            // auto-scaled axis that never touches 0 would
                            // turn a routine 1-2 order swing into what looks
                            // like a dramatic spike.
                            yAxisProps={{ domain: [0, 'auto'] }}
                            lineProps={{ isAnimationActive: false }}
                        />
                    </div>
                )}
            </div>
        </Card>
    );
}

export function SecondaryMetricsCard({ metrics, reducedMotion }) {
    const [hoveredIndex, setHoveredIndex] = useState(null);
    const rowRefs = useRef([]);
    const [highlightRect, setHighlightRect] = useState(null);

    useLayoutEffect(() => {
        if (hoveredIndex == null) { setHighlightRect(null); return; }
        const node = rowRefs.current[hoveredIndex];
        if (!node) return;
        setHighlightRect({ top: node.offsetTop, height: node.offsetHeight });
    }, [hoveredIndex]);

    return (
        <div className={`${surface} relative flex h-full flex-col overflow-hidden`} onMouseLeave={() => setHoveredIndex(null)}>
            {highlightRect && (
                <motion.div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 z-0 bg-stone-50 dark:bg-white/[0.03]"
                    initial={false}
                    animate={{ top: highlightRect.top, height: highlightRect.height, opacity: 1 }}
                    transition={reducedMotion ? { duration: 0 } : spring}
                />
            )}
            <div className="flex flex-1 flex-col divide-y divide-stone-100 dark:divide-white/10">
            {metrics.map((metric, index) => {
                const Row = metric.href ? Link : 'div';
                return (
                    <div key={metric.label} ref={(el) => { rowRefs.current[index] = el; }} onMouseEnter={() => metric.href && setHoveredIndex(index)} onFocus={() => metric.href && setHoveredIndex(index)} onBlur={() => setHoveredIndex(null)}>
                        <Row href={metric.href || undefined} className={`${focus} relative z-10 flex flex-1 items-center justify-between gap-3 p-4`}>
                            <div className="min-w-0">
                                <p className="truncate text-xs font-medium text-stone-500 dark:text-stone-400">{metric.label}</p>
                                <p className="mt-1 text-xl font-semibold tracking-tight text-stone-900 tabular-nums dark:text-stone-100 sm:text-2xl">{metric.value}</p>
                            </div>
                            {metric.delta && <span className="shrink-0 text-xs font-medium text-stone-500 dark:text-stone-400">{metric.delta.text}</span>}
                        </Row>
                    </div>
                );
            })}
            </div>
        </div>
    );
}

export function OrderStages({ current, previous, ordersUrl, reducedMotion }) {
    // Same tones as the order status badges (see statusBadge() in
    // utils/orderDisplay.js and STATUS_CLASS in motion/animated-badge.tsx):
    // neutral=muted-foreground, warning=amber-500, info/success are the
    // custom brand tokens. Partial and Needs redelivery share amber and
    // Pending/Cancelled share gray there too -- kept identical here rather
    // than inventing distinct colors, so the same status always reads as
    // the same color everywhere in the app.
    const stages = [
        { key: 'pending', label: 'Pending', color: 'bg-muted-foreground', status: 'pending' },
        { key: 'partial', label: 'Partial', color: 'bg-amber-500', status: 'partial' },
        { key: 'processed', label: 'Processed', color: 'bg-info', status: 'processed' },
        { key: 'completed', label: 'Completed', color: 'bg-success', status: 'completed' },
        { key: 'returned', label: 'Needs redelivery', color: 'bg-amber-500', status: 'returned' },
        { key: 'cancelled', label: 'Cancelled', color: 'bg-muted-foreground' },
    ];
    return (
        <div className="mt-3 border-t border-stone-100 px-5 py-4 sm:px-6 dark:border-white/10">
            <p className="mb-3 text-xs font-medium text-stone-600 dark:text-stone-300">Where these orders stand</p>
            <div aria-hidden="true" className="mb-4 flex h-1.5 gap-1 overflow-hidden rounded-full bg-stone-100 dark:bg-white/5">
                {stages.map((stage) => <motion.span key={stage.key} className={`h-full rounded-full ${stage.color}`} initial={false} animate={{ width: `${current.orders ? current.stages[stage.key] / current.orders * 100 : 0}%` }} transition={{ duration: reducedMotion ? 0 : 0.45 }} />)}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {stages.map((stage) => (
                    <div key={stage.key}>
                        <p className="flex items-center gap-1.5 text-xs text-stone-600 dark:text-stone-400"><span aria-hidden="true" className={`h-1.5 w-1.5 rounded-full ${stage.color}`} />{stage.label}</p>
                        {stage.status && current.stages[stage.key] > 0 ? <Link className={`mt-1 inline-block rounded text-lg font-semibold text-stone-800 tabular-nums hover:text-primary ${focus} dark:text-stone-200`} href={`${ordersUrl}&status=${stage.status}`} aria-label={`${current.stages[stage.key]} orders ${stage.label.toLowerCase()}`}>{number.format(current.stages[stage.key])}</Link> : <p className="mt-1 text-lg font-semibold text-stone-800 tabular-nums dark:text-stone-200">{number.format(current.stages[stage.key])}</p>}
                        <p className="text-xs text-stone-500 dark:text-stone-400">{number.format(previous.stages[stage.key])} previously</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function actionFor(order, customer) {
    if (order.status === 'completed') return { label: 'Confirm receipt', description: 'All items delivered. Confirm they arrived.', icon: ClipboardCheck };
    if (order.status === 'processed') return { label: customer ? 'Close order' : 'Complete order', description: customer ? 'All items delivered. Processed.' : 'All items have been delivered.', icon: ClipboardCheck };
    if (order.status === 'partial') return { label: customer ? 'Track delivery' : 'Continue fulfillment', description: `${number.format(order.delivered_units)} of ${number.format(order.ordered_units)} units delivered`, icon: Truck };
    return { label: customer ? 'View order' : 'Fulfill order', description: 'Pending and waiting for fulfillment.', icon: Package };
}

export function AttentionPanel({ orders, count, customer, reducedMotion, canOrder }) {
    const [hoveredIndex, setHoveredIndex] = useState(null);
    const rowRefs = useRef([]);
    const [highlightRect, setHighlightRect] = useState(null);

    useLayoutEffect(() => {
        if (hoveredIndex == null) { setHighlightRect(null); return; }
        const node = rowRefs.current[hoveredIndex];
        if (!node) return;
        setHighlightRect({ top: node.offsetTop, height: node.offsetHeight });
    }, [hoveredIndex]);

    return (
        <section aria-labelledby="attention-heading" className={`${surface} flex flex-col overflow-hidden`}>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-5 dark:border-white/10 sm:px-6">
                <div><h2 id="attention-heading" className="type-section-heading text-stone-900 dark:text-stone-100">{customer ? 'Your next steps' : 'Work to pick up'}</h2><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">{customer ? 'Updates to follow up on' : 'Fulfillment queue'} · All dates</p></div>
                <span className="grid h-6 min-w-6 place-items-center rounded-full bg-destructive px-1.5 text-xs font-semibold text-white tabular-nums">{number.format(count)}</span>
            </div>
            {orders.length ? (
                <div className="relative flex-1" onMouseLeave={() => setHoveredIndex(null)}>
                    {highlightRect && (
                        <motion.div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-x-0 z-0 bg-stone-50 dark:bg-white/[0.03]"
                            initial={false}
                            animate={{ top: highlightRect.top, height: highlightRect.height, opacity: 1 }}
                            transition={reducedMotion ? { duration: 0 } : spring}
                        />
                    )}
                    <ul className="divide-y divide-stone-100 dark:divide-white/5">
                        {orders.map((order, index) => {
                            const action = actionFor(order, customer);
                            const badge = statusBadge(order.received ? 'received' : order.status);
                            return (
                                <li key={order.id} ref={(el) => { rowRefs.current[index] = el; }} onMouseEnter={() => setHoveredIndex(index)} onFocus={() => setHoveredIndex(index)} onBlur={() => setHoveredIndex(null)}>
                                    <Link href={route('purchase-orders.show', order.public_id)} className={`relative z-10 flex items-center gap-3 px-5 py-3 ${focus} sm:px-6`}>
                                        {customer ? (
                                            <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium text-stone-900 dark:text-stone-100" title={order.po_number}>{order.po_number}</p><p className="mt-0.5 truncate text-xs leading-5 text-stone-500 dark:text-stone-400">{action.description}</p></div>
                                        ) : (
                                            <p className="min-w-0 flex-1 truncate text-sm font-medium text-stone-900 dark:text-stone-100" title={`${order.customer_name || 'Customer order'} - ${order.po_number}`}>{order.customer_name || 'Customer order'} - {order.po_number}</p>
                                        )}
                                        <AnimatedBadge status={badge.status} size="sm" pulse={false} icon={badge.icon ? <badge.icon className="h-3.5 w-3.5" /> : undefined} className="shrink-0 border-0 bg-transparent px-0 shadow-none">{badge.label}</AnimatedBadge>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ) : (
                <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center"><span className="mb-4 grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><CheckCheck className="h-6 w-6" aria-hidden="true" /></span><p className="text-sm font-medium text-stone-800 dark:text-stone-100">{canOrder ? 'Nothing waiting on you' : 'Your workspace is almost ready'}</p><p className="mt-2 max-w-xs text-sm leading-6 text-stone-500 dark:text-stone-400">{canOrder ? 'Orders that need a next step will appear here.' : 'Your orders will appear once your customer profile is linked.'}</p></div>
            )}
            {canOrder && <div className="flex justify-end border-t border-stone-100/70 px-5 py-4 dark:border-white/[0.07]"><Link href={route('purchase-orders.index')} className={`inline-flex items-center gap-2 rounded text-sm font-medium text-primary hover:underline ${focus} dark:text-indigo-300`}>Open all orders <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link></div>}
        </section>
    );
}

