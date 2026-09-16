import { ChartNoAxesCombined } from 'lucide-react';
import { BarChart } from '@/components/ui/bar-chart';
import { useIsMobile } from '@/hooks/use-mobile';

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
    const isMobile = useIsMobile();
    // Recharts' own tick-gap logic still fits 3-4 date labels on a phone
    // width, which reads as clutter on a chart this narrow -- on mobile,
    // label only the most recent (rightmost) day instead.
    const mobileTick = visibleTrend.at(-1)?.date;

    return <div className="px-3 pt-5 sm:px-5">
        {empty ? <div className="flex h-48 flex-col items-center justify-center px-6 text-center"><span className="mb-4 grid h-12 w-12 place-items-center rounded-full bg-stone-100 text-stone-500 dark:bg-white/[0.06] dark:text-stone-400"><ChartNoAxesCombined className="h-6 w-6" aria-hidden="true" /></span><p className="text-sm font-medium text-stone-800 dark:text-stone-100">Your order activity will appear here</p><p className="mt-2 max-w-xs text-sm leading-6 text-stone-500 dark:text-stone-400">No orders were placed or delivered in this period. Choose a longer range to check earlier activity.</p></div> :
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
                        ...(isMobile && mobileTick ? { ticks: [mobileTick] } : {}),
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
