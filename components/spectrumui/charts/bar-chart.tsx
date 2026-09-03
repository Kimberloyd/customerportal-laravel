'use client';

import * as React from 'react';
import {
  Bar,
  BarChart as RechartsBarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { cn } from '@/lib/utils';
import {
  BarFillDefs,
  type BarFillVariant,
  ChartFrame,
  ChartGlowFilter,
  chartGrid,
  ChartLegend,
  ChartLoadingBars,
  ChartPlotSurface,
  ChartTooltipContent,
  chartXAxis,
  chartYAxis,
  CHART_COLORS,
  MONTHLY_TRAFFIC,
  SERIES,
  barFillUrl,
  createGrowBarShape,
  HoverIndexProvider,
  readActiveTooltipIndex,
  useChartId,
  useChartMotion,
  useIntroStartedAt,
} from './chart-kit';

export type { BarFillVariant };

export type BarStackType = 'none' | 'stacked' | 'percent';
export type BarLayout = 'vertical' | 'horizontal';

const VERTICAL_RADIUS = [4, 4, 0, 0] as [number, number, number, number];
const HORIZONTAL_RADIUS = [0, 4, 4, 0] as [number, number, number, number];

/** One plotted measure. `color` falls back to the palette slot for its position. */
export type BarSeries = {
  key: string;
  label: string;
  color?: string;
  variant?: BarFillVariant;
};

export interface SpectrumBarChartProps {
  className?: string;
  data?: ReadonlyArray<Record<string, string | number>>;
  /** Field holding each bar's category label. */
  categoryKey?: string;
  /** Measures to plot. Defaults to the demo desktop/mobile pair. */
  series?: BarSeries[];
  variant?: BarFillVariant;
  desktopVariant?: BarFillVariant;
  mobileVariant?: BarFillVariant;
  stackType?: BarStackType;
  layout?: BarLayout;
  glowing?: boolean;
  isLoading?: boolean;
  showLegend?: boolean;
  showGrid?: boolean;
  /** Width of the category axis in horizontal layout; long labels need more. */
  categoryWidth?: number;
}

export function BarChart({
  className,
  data = MONTHLY_TRAFFIC,
  categoryKey = 'month',
  series,
  variant = 'default',
  desktopVariant,
  mobileVariant,
  stackType = 'none',
  layout = 'vertical',
  glowing = false,
  isLoading = false,
  showLegend = true,
  showGrid = true,
  categoryWidth = 44,
}: SpectrumBarChartProps) {
  const id = useChartId('bar');
  const { reduce } = useChartMotion();
  const introStartedAt = useIntroStartedAt();
  const [activeIndex, setActiveIndex] = React.useState<number | null>(null);
  const desktopFill = desktopVariant ?? variant;
  const mobileFill = mobileVariant ?? variant;
  const stacked = stackType !== 'none';
  const horizontal = layout === 'horizontal';
  const glowId = `${id}-glow`;
  const radius = horizontal ? HORIZONTAL_RADIUS : VERTICAL_RADIUS;

  // Colour follows the series' own slot, never its rank in the data, so
  // filtering or reordering rows never repaints the survivors.
  const resolvedSeries = React.useMemo(
    () =>
      (series ?? [
        { key: 'desktop', label: SERIES.desktop.label, color: SERIES.desktop.color, variant: desktopFill },
        { key: 'mobile', label: SERIES.mobile.label, color: SERIES.mobile.color, variant: mobileFill },
      ]).map((entry, index) => ({
        key: entry.key,
        label: entry.label,
        color: entry.color ?? CHART_COLORS[index % CHART_COLORS.length],
        variant: entry.variant ?? variant,
      })),
    [series, desktopFill, mobileFill, variant],
  );

  const shapes = React.useMemo(
    () =>
      resolvedSeries.map((entry, index) =>
        createGrowBarShape({
          horizontal,
          introStartedAt,
          dataLength: data.length,
          reduce,
          radius,
          stripped: entry.variant === 'stripped',
          glowId: glowing && index === 0 ? glowId : undefined,
        }),
      ),
    [resolvedSeries, horizontal, introStartedAt, data.length, reduce, radius, glowing, glowId],
  );

  const legendItems = React.useMemo(
    () => resolvedSeries.map((entry) => ({ label: entry.label, color: entry.color })),
    [resolvedSeries],
  );

  return (
    <ChartFrame className={cn('flex flex-col', className)}>
      {showLegend ? <ChartLegend items={legendItems} /> : null}
      {isLoading ? (
        <ChartLoadingBars />
      ) : (
        <ChartPlotSurface>
          <HoverIndexProvider value={activeIndex}>
          <ResponsiveContainer width="100%" height="100%">
            <RechartsBarChart
              data={data}
              layout={horizontal ? 'vertical' : 'horizontal'}
              stackOffset={stackType === 'percent' ? 'expand' : undefined}
              margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
              barCategoryGap="18%"
              barGap={4}
              onMouseMove={(state) => {
                const next = readActiveTooltipIndex(state);
                setActiveIndex((current) => (current === next ? current : next));
              }}
              onMouseLeave={() => setActiveIndex(null)}
            >
              <defs>
                {resolvedSeries.map((entry) => (
                  <BarFillDefs
                    key={entry.key}
                    id={`${id}-${entry.key}`}
                    color={entry.color}
                    variant={entry.variant}
                  />
                ))}
                {glowing ? <ChartGlowFilter id={glowId} /> : null}
              </defs>
              {showGrid ? (
                <CartesianGrid {...chartGrid} horizontal={!horizontal} vertical={horizontal} />
              ) : null}
              <XAxis
                {...chartXAxis}
                {...(horizontal
                  ? { type: 'number' as const, hide: stackType === 'percent' }
                  : { dataKey: categoryKey })}
              />
              <YAxis
                {...chartYAxis}
                {...(horizontal
                  ? { dataKey: categoryKey, type: 'category' as const, width: categoryWidth }
                  : { hide: stackType === 'percent' })}
              />
              <Tooltip
                cursor={{ fill: 'currentColor', fillOpacity: 0.05 }}
                content={<ChartTooltipContent />}
              />
              {resolvedSeries.map((entry, index) => (
                <Bar
                  key={entry.key}
                  dataKey={entry.key}
                  name={entry.label}
                  fill={barFillUrl(`${id}-${entry.key}`, entry.variant, entry.color)}
                  radius={radius}
                  stackId={stacked ? 'traffic' : undefined}
                  isAnimationActive={false}
                  shape={shapes[index]}
                  maxBarSize={36}
                />
              ))}
            </RechartsBarChart>
          </ResponsiveContainer>
          </HoverIndexProvider>
        </ChartPlotSurface>
      )}
    </ChartFrame>
  );
}

export function DefaultBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="default" {...props} />;
}

export function HatchedBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="hatched" {...props} />;
}

export function DuotoneBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="duotone" {...props} />;
}

export function DuotoneReverseBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="duotone-reverse" {...props} />;
}

export function GradientBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="gradient" {...props} />;
}

export function StrippedBarChart(props: SpectrumBarChartProps) {
  return <BarChart variant="stripped" {...props} />;
}

export function StackedBarChart(props: SpectrumBarChartProps) {
  return <BarChart stackType="stacked" {...props} />;
}

export function PercentBarChart(props: SpectrumBarChartProps) {
  return <BarChart stackType="percent" {...props} />;
}

export function HorizontalBarChart(props: SpectrumBarChartProps) {
  return <BarChart layout="horizontal" {...props} />;
}

export function GlowingBarChart(props: SpectrumBarChartProps) {
  return <BarChart glowing {...props} />;
}
