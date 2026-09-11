import { ChartNoAxesCombined } from 'lucide-react';
import { BarChart } from '@/components/ui/bar-chart';

const dateLabel = (value) => new Date(`${value}T00:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });

const number = new Intl.NumberFormat('en-PH');

const chartConfig = {
    // Matches the same status tones used in the orders table and the
    // "Where these orders stand" panel: every order starts out pending
    // (muted-foreground/gray) when placed, and a completed delivery uses
    // the same success green as the Completed status badge.
    current: {
        label: 'Orders placed',
        color: 'var(--muted-foreground)',
    },
    delivered: {
        label: 'Actual delivery',
        color: 'hsl(var(--success-hsl))',
    },
};

export default function OrderTrend({ trend, empty }) {
    const visibleTrend = trend.slice(-7);

    return <div className="px-3 pt-5 sm:px-5">
        {empty ? <div className="flex h-48 flex-col items-center justify-center gap-2 rounded-xl bg-stone-50 text-center dark:bg-white/[0.03]"><ChartNoAxesCombined className="mb-1 h-7 w-7 text-stone-400" aria-hidden="true" /><p className="text-sm font-medium text-stone-700 dark:text-stone-200">Your order activity will appear here</p><p className="max-w-xs text-xs leading-5 text-stone-500 dark:text-stone-400">No orders were placed or delivered in this period. Choose a longer range to check earlier activity.</p></div> :
            <div
                role="group"
                aria-label="Daily orders placed and actual deliveries."
                className="min-h-60 w-full text-stone-700 dark:text-stone-300 [&_.recharts-cartesian-axis-tick-value]:fill-stone-500 dark:[&_.recharts-cartesian-axis-tick-value]:fill-stone-400"
            >
                <BarChart
                    data={visibleTrend}
                    dataKey="date"
                    config={chartConfig}
                    containerHeight={250}
                    barCategoryGap={18}
                    barGap={4}
                    barRadius={6}
                    valueFormatter={(value) => number.format(value)}
                    xAxisProps={{
                        tickFormatter: dateLabel,
                        minTickGap: 34,
                    }}
                    yAxisProps={{
                        allowDecimals: false,
                        // Bar length encodes value -- an auto-scaled axis
                        // that doesn't touch 0 would make small day-to-day
                        // differences in order counts look disproportionate.
                        domain: [0, 'auto'],
                    }}
                    tooltipProps={{
                        labelFormatter: dateLabel,
                    }}
                    cartesianGridProps={{
                        stroke: 'currentColor',
                        strokeOpacity: 0.08,
                        strokeDasharray: '3 5',
                    }}
                />
            </div>}
    </div>;
}
