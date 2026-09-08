import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartNoAxesCombined } from 'lucide-react';

const dateLabel = (value) => new Date(`${value}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });

function TrendTooltip({ active, payload }) {
    if (!active || !payload?.length) return null;
    const point = payload[0].payload;
    return <div className="rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm shadow-lg dark:border-white/10 dark:bg-stone-900"><p className="font-medium text-stone-900 dark:text-stone-100">{dateLabel(point.date)}: {point.current} orders placed</p><p className="mt-1 text-emerald-700 dark:text-emerald-300">{point.delivered} actual deliveries</p></div>;
}

export default function OrderTrend({ trend, empty, reducedMotion }) {
    return <div className="px-3 pt-5 sm:px-5">
        <div className="mb-3 flex items-center gap-5 px-1 text-xs text-stone-600 dark:text-stone-400" aria-hidden="true"><span className="inline-flex items-center gap-2"><span className="h-0.5 w-5 rounded bg-primary dark:bg-indigo-300" /> Orders placed</span><span className="inline-flex items-center gap-2"><span className="h-0.5 w-5 rounded bg-emerald-500" /> Actual delivery</span></div>
        {empty ? <div className="flex h-48 flex-col items-center justify-center gap-2 rounded-xl bg-stone-50 text-center dark:bg-white/[0.03]"><ChartNoAxesCombined className="mb-1 h-7 w-7 text-stone-400" aria-hidden="true" /><p className="text-sm font-medium text-stone-700 dark:text-stone-200">Your order activity will appear here</p><p className="max-w-xs text-xs leading-5 text-stone-500 dark:text-stone-400">No orders were placed or delivered in this period. Choose a longer range to check earlier activity.</p></div> :
            <div className="h-48 w-full text-primary dark:text-indigo-300 [&_.recharts-cartesian-axis-tick-value]:fill-stone-500 dark:[&_.recharts-cartesian-axis-tick-value]:fill-stone-400" role="group" aria-label="Daily orders placed and actual deliveries. Use the arrow keys to explore the chart.">
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <AreaChart data={trend} margin={{ top: 8, right: 8, bottom: 0, left: -22 }} accessibilityLayer>
                        <defs><linearGradient id="dashboard-order-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="currentColor" stopOpacity={0.17} /><stop offset="100%" stopColor="currentColor" stopOpacity={0.01} /></linearGradient></defs>
                        <CartesianGrid vertical={false} stroke="currentColor" strokeOpacity={0.08} strokeDasharray="3 5" />
                        <XAxis dataKey="date" tickFormatter={dateLabel} axisLine={false} tickLine={false} minTickGap={38} tick={{ fill: '#78716c', fontSize: 12 }} dy={6} />
                        <YAxis allowDecimals={false} axisLine={false} tickLine={false} tick={{ fill: '#78716c', fontSize: 12 }} />
                        <Tooltip content={<TrendTooltip />} cursor={{ stroke: '#a8a29e', strokeDasharray: '3 3' }} />
                        <Area type="linear" dataKey="delivered" name="Actual delivery" stroke="#10b981" strokeWidth={2.5} fill="transparent" isAnimationActive={!reducedMotion} animationDuration={650} activeDot={{ r: 4, strokeWidth: 3, stroke: '#fff' }} />
                        <Area type="linear" dataKey="current" name="Selected period" stroke="currentColor" strokeWidth={2.5} fill="url(#dashboard-order-fill)" isAnimationActive={!reducedMotion} animationDuration={650} activeDot={{ r: 4, strokeWidth: 3, stroke: '#fff' }} />
                    </AreaChart>
                </ResponsiveContainer>
            </div>}
    </div>;
}
