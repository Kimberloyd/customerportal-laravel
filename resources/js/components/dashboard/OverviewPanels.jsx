import { AnimatedBadge } from '@/components/motion/animated-badge';
import { statusBadge } from '@/utils/orderDisplay';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCheck, ClipboardCheck, Package, Truck } from 'lucide-react';
import { animate, motion, useMotionValue } from 'motion/react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { Area, AreaChart, ResponsiveContainer, YAxis } from 'recharts';

const number = new Intl.NumberFormat('en-PH');
const surface = 'rounded-xl border border-border bg-card';
const focus = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--focus-ring)] focus-visible:ring-offset-2';
const spring = { type: 'spring', stiffness: 700, damping: 46, mass: 0.5 };

/**
 * Counts up to `target` on mount and whenever it changes (e.g. switching the
 * 7/30/90-day period) -- a small, responsive flourish tied to a real user
 * action, not a page-load orchestration. Skips straight to the target under
 * reduced motion.
 */
function useCountUp(target, reducedMotion) {
    const [display, setDisplay] = useState(reducedMotion ? target : 0);
    const motionValue = useMotionValue(0);

    useEffect(() => {
        if (reducedMotion || Number.isNaN(target)) {
            setDisplay(target);
            return;
        }
        const controls = animate(motionValue, target, {
            duration: 0.8,
            ease: [0.16, 1, 0.3, 1],
            onUpdate: (value) => setDisplay(Math.round(value)),
        });
        return () => controls.stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target, reducedMotion]);

    return display;
}

export function PrimaryMetricCard({ label, value, rawValue, href, trend, reducedMotion }) {
    const Card = href ? Link : 'div';
    const sparkline = (trend ?? []).map((point) => ({ value: point.current }));
    const gradientId = useId();
    const hasCountUp = typeof rawValue === 'number' && !Number.isNaN(rawValue);
    const displayValue = useCountUp(hasCountUp ? rawValue : 0, reducedMotion);
    return (
        <Card href={href || undefined} className={`${surface} ${focus} group relative flex h-full flex-col overflow-hidden p-5 transition-colors ${href ? 'hover:bg-hover' : ''} sm:p-6`}>
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <div className="flex flex-1 flex-row-reverse items-center justify-between gap-4 sm:flex-row">
                <div className="min-w-0 text-right sm:text-left">
                    <p className="break-words text-5xl font-semibold leading-tight tracking-tight text-foreground tabular-nums sm:text-6xl">{hasCountUp ? number.format(displayValue) : value}</p>
                </div>
                {sparkline.length > 1 && (
                    <div className="pointer-events-none h-32 w-64 shrink-0 sm:h-40 sm:w-80" aria-hidden="true">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={sparkline} margin={{ top: 5, right: 0, bottom: 0, left: 0 }}>
                                <defs>
                                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="hsl(var(--success-hsl))" stopOpacity={0.28} />
                                        <stop offset="100%" stopColor="hsl(var(--success-hsl))" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                {/* Order counts are small integers -- an
                                    auto-scaled axis that never touches 0
                                    would turn a routine 1-2 order swing
                                    into what looks like a dramatic spike. */}
                                <YAxis hide domain={[0, 'auto']} />
                                <Area
                                    type="linear"
                                    dataKey="value"
                                    stroke="hsl(var(--success-hsl))"
                                    strokeWidth={2}
                                    strokeLinejoin="round"
                                    strokeLinecap="round"
                                    fill={`url(#${gradientId})`}
                                    isAnimationActive={false}
                                    connectNulls
                                />
                            </AreaChart>
                        </ResponsiveContainer>
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
                    className="pointer-events-none absolute inset-x-0 z-0 bg-hover"
                    initial={false}
                    animate={{ top: highlightRect.top, height: highlightRect.height, opacity: 1 }}
                    transition={reducedMotion ? { duration: 0 } : spring}
                />
            )}
            <div className="flex flex-1 flex-col divide-y divide-border">
            {metrics.map((metric, index) => {
                const Row = metric.href ? Link : 'div';
                return (
                    <div key={metric.label} ref={(el) => { rowRefs.current[index] = el; }} onMouseEnter={() => metric.href && setHoveredIndex(index)} onFocus={() => metric.href && setHoveredIndex(index)} onBlur={() => setHoveredIndex(null)}>
                        <Row href={metric.href || undefined} className={`${focus} relative z-10 flex flex-1 items-center justify-between gap-3 p-4`}>
                            <p className="min-w-0 truncate text-xs font-medium text-muted-foreground">{metric.label}</p>
                            <p className="shrink-0 text-xl font-semibold tracking-tight text-foreground tabular-nums sm:text-2xl">{metric.value}</p>
                        </Row>
                    </div>
                );
            })}
            </div>
        </div>
    );
}

export function OrderStages({ current, ordersUrl, reducedMotion }) {
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
        <div className="mt-3 border-t border-border px-5 py-4 sm:px-6">
            <p className="mb-3 text-xs font-medium text-muted-foreground">Where these orders stand</p>
            <div aria-hidden="true" className="mb-4 flex h-1.5 gap-1 overflow-hidden rounded-full bg-muted">
                {stages.map((stage) => <motion.span key={stage.key} className={`h-full rounded-full ${stage.color}`} initial={false} animate={{ width: `${current.orders ? current.stages[stage.key] / current.orders * 100 : 0}%` }} transition={{ duration: reducedMotion ? 0 : 0.45 }} />)}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {stages.map((stage) => (
                    <div key={stage.key}>
                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground"><span aria-hidden="true" className={`h-1.5 w-1.5 rounded-full ${stage.color}`} />{stage.label}</p>
                        {stage.status && current.stages[stage.key] > 0 ? <Link className={`mt-1 inline-block rounded text-lg font-semibold text-foreground tabular-nums hover:text-primary ${focus}`} href={`${ordersUrl}&status=${stage.status}`} aria-label={`${current.stages[stage.key]} orders ${stage.label.toLowerCase()}`}>{number.format(current.stages[stage.key])}</Link> : <p className="mt-1 text-lg font-semibold text-foreground tabular-nums">{number.format(current.stages[stage.key])}</p>}
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
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-5 sm:px-6">
                <div><h2 id="attention-heading" className="type-section-heading text-foreground">{customer ? 'Your next steps' : 'Work to pick up'}</h2><p className="mt-1 text-sm text-muted-foreground">{customer ? 'Updates to follow up on' : 'Fulfillment queue'} · All dates</p></div>
                <span className="grid h-6 min-w-6 place-items-center rounded-full bg-destructive px-1.5 text-xs font-semibold text-background tabular-nums">{number.format(count)}</span>
            </div>
            {orders.length ? (
                <div className="relative flex-1" onMouseLeave={() => setHoveredIndex(null)}>
                    {highlightRect && (
                        <motion.div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-x-0 z-0 bg-hover"
                            initial={false}
                            animate={{ top: highlightRect.top, height: highlightRect.height, opacity: 1 }}
                            transition={reducedMotion ? { duration: 0 } : spring}
                        />
                    )}
                    <ul className="divide-y divide-border">
                        {orders.map((order, index) => {
                            const action = actionFor(order, customer);
                            const badge = statusBadge(order.received ? 'received' : order.status);
                            return (
                                <li key={order.id} ref={(el) => { rowRefs.current[index] = el; }} onMouseEnter={() => setHoveredIndex(index)} onFocus={() => setHoveredIndex(index)} onBlur={() => setHoveredIndex(null)}>
                                    <Link href={route('purchase-orders.show', order.public_id)} className={`relative z-10 flex items-center gap-3 px-5 py-3 ${focus} sm:px-6`}>
                                        {customer ? (
                                            <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium text-foreground" title={order.transaction_number}>{order.transaction_number}</p><p className="mt-0.5 truncate text-xs leading-5 text-muted-foreground">{action.description}</p></div>
                                        ) : (
                                            <p className="min-w-0 flex-1 truncate text-sm font-medium text-foreground" title={`${order.customer_name || 'Customer order'} - ${order.po_number}`}>{order.customer_name || 'Customer order'} - {order.po_number}</p>
                                        )}
                                        <AnimatedBadge status={badge.status} size="sm" pulse={false} icon={badge.icon ? <badge.icon className="h-3.5 w-3.5" /> : undefined} className="shrink-0 border-0 bg-transparent px-0 shadow-none">{badge.label}</AnimatedBadge>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ) : (
                <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center"><span className="mb-4 grid h-12 w-12 place-items-center rounded-full bg-success/10 text-success"><CheckCheck className="h-6 w-6" aria-hidden="true" /></span><p className="text-sm font-medium text-foreground">{canOrder ? 'Nothing waiting on you' : 'Your workspace is almost ready'}</p><p className="mt-2 max-w-xs text-sm leading-6 text-muted-foreground">{canOrder ? 'Orders that need a next step will appear here.' : 'Your orders will appear once your customer profile is linked.'}</p></div>
            )}
            {canOrder && <div className="flex justify-end border-t border-border px-5 py-4"><Link href={route('purchase-orders.index')} className={`inline-flex items-center gap-2 rounded text-sm font-medium text-primary hover:underline ${focus}`}>Open all orders <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link></div>}
        </section>
    );
}

