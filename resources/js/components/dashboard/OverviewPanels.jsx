import { Link } from '@inertiajs/react';
import { ArrowRight, Check, CheckCheck, ChevronRight, ClipboardCheck, Clock3, Package, Truck } from 'lucide-react';
import { motion } from 'motion/react';
import { useId, useState } from 'react';

const number = new Intl.NumberFormat('en-PH');
const surface = 'rounded-2xl border border-stone-200/80 bg-white shadow-[0_2px_8px_rgba(0,0,0,0.02)] dark:border-white/10 dark:bg-[#1d1e22]';
const focus = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';
const spring = { type: 'spring', stiffness: 700, damping: 46, mass: 0.5 };

export function MetricCard({ label, value, previous, icon: Icon, note, href, period, featured }) {
    const Card = href ? Link : 'div';
    return (
        <Card href={href || undefined} className={`${surface} ${focus} group relative flex h-full flex-col overflow-hidden p-4 transition-shadow ${href ? 'hover:shadow-md' : ''} sm:p-5`}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-medium text-stone-600 dark:text-stone-300">{label}</p>
                <Icon aria-hidden="true" className="h-4 w-4 text-stone-400" />
            </div>
            <p className="mt-4 break-words text-2xl font-semibold leading-tight tracking-tight text-stone-900 tabular-nums sm:text-3xl dark:text-stone-100">{value}</p>
            <p className="mt-2 text-xs leading-5 text-stone-500 dark:text-stone-400"><span className="font-medium text-stone-700 tabular-nums dark:text-stone-300">{previous}</span> in previous {period} days</p>
            <div className="flex-1" />
            <div className="mt-4 flex items-center justify-between gap-2 border-t border-stone-100 pt-3 dark:border-white/10"><p className="text-xs leading-4 text-stone-500 dark:text-stone-400">{note}</p><ChevronRight aria-hidden="true" className="h-3.5 w-3.5 shrink-0 text-stone-400 group-hover:text-primary" /></div>
        </Card>
    );
}

export function OrderStages({ current, previous, ordersUrl, reducedMotion }) {
    const stages = [
        { key: 'review', label: 'In review', color: 'bg-indigo-500', status: 'submitted' },
        { key: 'fulfillment', label: 'In fulfillment', color: 'bg-amber-500', status: 'partial' },
        { key: 'completed', label: 'Completed', color: 'bg-emerald-500', status: 'completed' },
        { key: 'cancelled', label: 'Cancelled', color: 'bg-stone-400' },
    ];
    return (
        <div className="mt-3 border-t border-stone-100 px-5 py-4 sm:px-6 dark:border-white/10">
            <p className="mb-3 text-xs font-medium text-stone-600 dark:text-stone-300">Where these orders stand</p>
            <div aria-hidden="true" className="mb-4 flex h-1.5 gap-1 overflow-hidden rounded-full bg-stone-100 dark:bg-white/5">
                {stages.map((stage) => <motion.span key={stage.key} className={`h-full rounded-full ${stage.color}`} initial={false} animate={{ width: `${current.orders ? current.stages[stage.key] / current.orders * 100 : 0}%` }} transition={{ duration: reducedMotion ? 0 : 0.45 }} />)}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
    if (order.status === 'partial' || order.status === 'processing') return { label: customer ? 'Track delivery' : 'Continue fulfillment', description: `${number.format(order.delivered_units)} of ${number.format(order.ordered_units)} units delivered`, icon: Truck };
    if (order.status === 'reviewing') return { label: customer ? 'View order' : 'Continue review', description: customer ? 'The team is reviewing your order.' : 'Review started. Check the next steps.', icon: Clock3 };
    return { label: customer ? 'View order' : 'Review order', description: customer ? 'Submitted and waiting for review.' : 'New order waiting for your review.', icon: Package };
}

export function AttentionPanel({ orders, count, customer, reducedMotion, canOrder }) {
    const [hovered, setHovered] = useState(null);
    const highlightId = useId();
    return (
        <section aria-labelledby="attention-heading" className={`${surface} flex flex-col overflow-hidden`}>
            <div className="flex items-start justify-between gap-3 px-5 pt-5">
                <div><h2 id="attention-heading" className="type-section-heading text-stone-900 dark:text-stone-100">{customer ? 'Your next steps' : 'Work to pick up'}</h2><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">{customer ? 'Updates to follow up on' : 'Review and fulfillment queue'} · All dates</p></div>
                <span className="grid h-6 min-w-6 place-items-center rounded-full bg-destructive px-1.5 text-xs font-semibold text-white tabular-nums">{number.format(count)}</span>
            </div>
            {orders.length ? (
                <ul className="my-3 flex-1 px-2" onMouseLeave={() => setHovered(null)}>
                    {orders.map((order) => {
                        const action = actionFor(order, customer);
                        return (
                            <li key={order.id} className="relative" onMouseEnter={() => setHovered(order.id)} onFocus={() => setHovered(order.id)} onBlur={() => setHovered(null)}>
                                {hovered === order.id && <motion.div aria-hidden="true" layoutId={reducedMotion ? undefined : highlightId} className="pointer-events-none absolute inset-0 rounded-xl bg-stone-100 dark:bg-white/[0.06]" transition={reducedMotion ? { duration: 0 } : spring} />}
                                <Link href={route('purchase-orders.show', order.id)} className={`relative flex gap-3 rounded-xl px-3 py-3 ${focus}`}>
                                    <div className="min-w-0 flex-1"><p className="truncate text-sm font-medium text-stone-900 dark:text-stone-100" title={order.po_number}>{order.po_number}</p><p className="mt-0.5 truncate text-xs leading-5 text-stone-500 dark:text-stone-400">{customer ? action.description : order.customer_name || 'Customer order'}</p><p className="mt-1 flex items-center gap-1 text-xs font-medium text-primary dark:text-indigo-300">{action.label}<ArrowRight className="h-3 w-3" aria-hidden="true" /></p></div>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center"><span className="mb-4 grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><CheckCheck className="h-6 w-6" aria-hidden="true" /></span><p className="text-sm font-medium text-stone-800 dark:text-stone-100">{canOrder ? 'Nothing waiting on you' : 'Your workspace is almost ready'}</p><p className="mt-2 max-w-xs text-sm leading-6 text-stone-500 dark:text-stone-400">{canOrder ? 'Orders that need a next step will appear here.' : 'Your orders will appear once your customer profile is linked.'}</p></div>
            )}
            {canOrder && <Link href={route('purchase-orders.index')} className={`flex items-center justify-between border-t border-stone-100 px-5 py-4 text-sm font-medium text-primary transition-colors hover:bg-stone-50 ${focus} dark:border-white/10 dark:text-indigo-300 dark:hover:bg-white/5`}>Open all orders <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link>}
        </section>
    );
}

function OrderStatus({ order }) {
    const statuses = {
        submitted: { label: 'Submitted', icon: Package, style: 'bg-indigo-50 text-indigo-800 dark:bg-indigo-400/10 dark:text-indigo-200' },
        reviewing: { label: 'In review', icon: Clock3, style: 'bg-indigo-50 text-indigo-800 dark:bg-indigo-400/10 dark:text-indigo-200' },
        partial: { label: 'Partial delivery', icon: Truck, style: 'bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-200' },
        processing: { label: 'Partial delivery', icon: Truck, style: 'bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-200' },
        completed: { label: order.received ? 'Received' : 'Completed', icon: Check, style: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-200' },
        cancelled: { label: 'Cancelled', icon: Package, style: 'bg-stone-100 text-stone-600 dark:bg-white/10 dark:text-stone-300' },
    };
    const status = statuses[order.status] || { label: order.status, icon: Package, style: 'bg-stone-100 text-stone-600' };
    return <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${status.style}`}><status.icon className="h-3 w-3" aria-hidden="true" />{status.label}</span>;
}

export function RecentOrders({ orders, customer, ordersUrl, canOrder, reducedMotion }) {
    return (
        <section aria-labelledby="recent-orders-heading" className={`${surface} overflow-hidden`}>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-5 sm:px-6"><div><h2 id="recent-orders-heading" className="type-section-heading text-stone-900 dark:text-stone-100">{customer ? 'Your latest orders' : 'Latest orders'}</h2><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">Most recent orders in the selected period.</p></div>{canOrder && <Link href={ordersUrl} className={`inline-flex items-center gap-2 rounded text-sm font-medium text-primary hover:underline ${focus} dark:text-indigo-300`}>View all <ArrowRight className="h-4 w-4" aria-hidden="true" /></Link>}</div>
            {orders.length ? (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-y border-stone-100 bg-stone-50/70 text-xs font-medium text-stone-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-stone-400"><tr><th scope="col" className="px-5 py-3 font-medium sm:px-6">Purchase order</th>{!customer && <th scope="col" className="px-4 py-3 font-medium">Customer</th>}<th scope="col" className="px-4 py-3 font-medium">Status</th><th scope="col" className="min-w-40 px-4 py-3 font-medium">Delivery progress</th><th scope="col" className="px-5 py-3 font-medium">Placed</th></tr></thead>
                        <tbody className="divide-y divide-stone-100 dark:divide-white/5">{orders.map((order) => {
                            const progress = order.ordered_units ? Math.min(100, order.delivered_units / order.ordered_units * 100) : 0;
                            return <tr key={order.id} className="transition-colors hover:bg-stone-50 dark:hover:bg-white/[0.03]">
                                <td className="max-w-56 px-5 py-4 sm:px-6"><Link className={`block truncate rounded font-medium text-primary hover:underline ${focus} dark:text-indigo-300`} href={route('purchase-orders.show', order.id)} title={order.po_number}>{order.po_number}</Link></td>
                                {!customer && <td className="max-w-56 truncate px-4 py-4 text-stone-600 dark:text-stone-300" title={order.customer_name}>{order.customer_name || '—'}</td>}
                                <td className="px-4 py-4"><OrderStatus order={order} /></td>
                                <td className="px-4 py-4"><div className="flex items-center gap-3"><div aria-hidden="true" className="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-stone-100 dark:bg-white/10"><motion.div initial={false} animate={{ width: `${progress}%` }} transition={{ duration: reducedMotion ? 0 : 0.45 }} className={`h-full rounded-full ${progress === 100 ? 'bg-emerald-500' : 'bg-indigo-500'}`} /></div><span className="whitespace-nowrap text-xs text-stone-500 tabular-nums dark:text-stone-400">{number.format(order.delivered_units)} / {number.format(order.ordered_units)} units</span></div></td>
                                <td className="whitespace-nowrap px-5 py-4 text-xs text-stone-500 dark:text-stone-400">{order.submitted_at ? new Date(order.submitted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }) : '—'}</td>
                            </tr>;
                        })}</tbody>
                    </table>
                </div>
            ) : <div className="border-t border-stone-100 px-6 py-9 text-center dark:border-white/10"><Package aria-hidden="true" className="mx-auto mb-3 h-7 w-7 text-stone-400" /><p className="text-sm font-medium text-stone-700 dark:text-stone-200">No orders in this period</p><p className="mt-1 text-sm text-stone-500 dark:text-stone-400">{canOrder ? 'Choose a longer date range or create a new order to get started.' : 'Your order history will appear here after your account is linked.'}</p></div>}
        </section>
    );
}
