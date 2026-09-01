import {
  CheckCircle2Icon,
  CircleDotIcon,
  PackageCheckIcon,
  PackagePlusIcon,
  PencilLineIcon,
  XCircleIcon,
} from "lucide-react";
import type { ComponentType, ReactNode } from "react";

export type OrderActivity = {
  created_at: string | null;
  actor_name: string | null;
  actor_role: string | null;
  action: string;
  details: string | null;
  remarks: string | null;
};

export type TimelineEntry = {
  key: string;
  createdAt: string | null;
  icon: ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  tone: string;
  render: () => ReactNode;
};

type ActivityStyle = {
  Icon: ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  tone: string;
};

const DEFAULT_STYLE: ActivityStyle = {
  Icon: CircleDotIcon,
  tone: "bg-primary/10 text-primary",
};

export function styleFor(action: string): ActivityStyle {
  const normalized = action.toLocaleLowerCase();

  if (normalized.includes("cancel")) {
    return {
      Icon: XCircleIcon,
      tone: "bg-destructive/10 text-destructive",
    };
  }
  if (normalized.includes("complete") || normalized.includes("received")) {
    return {
      Icon: CheckCircle2Icon,
      tone: "bg-success/10 text-success",
    };
  }
  if (normalized.includes("fulfillment") || normalized.includes("delivery")) {
    return {
      Icon: PackageCheckIcon,
      tone: "bg-info/10 text-info",
    };
  }
  if (normalized.includes("created") || normalized.includes("submitted")) {
    return {
      Icon: PackagePlusIcon,
      tone: "bg-primary/10 text-primary",
    };
  }
  if (normalized.includes("updated") || normalized.includes("edited")) {
    return {
      Icon: PencilLineIcon,
      tone: "bg-primary/10 text-primary",
    };
  }

  return DEFAULT_STYLE;
}

function initialsFor(activity: OrderActivity): string {
  if (!activity.actor_name) return "PO";

  return activity.actor_name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toLocaleUpperCase())
    .join("");
}

function dateKey(value: string | null): string {
  if (!value) return "unknown";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "unknown";

  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function dayLabel(value: string | null): string {
  if (!value) return "Date unavailable";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Date unavailable";

  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);

  if (dateKey(value) === dateKey(today.toISOString())) return "Today";
  if (dateKey(value) === dateKey(yesterday.toISOString())) return "Yesterday";

  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function timeLabel(value: string | null): string {
  if (!value) return "Time unavailable";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Time unavailable";

  return date.toLocaleTimeString("en-US", {
    hour: "numeric",
    minute: "2-digit",
  });
}

function groupByDay(entries: TimelineEntry[]) {
  const groups = new Map<string, { label: string; entries: TimelineEntry[] }>();

  for (const entry of entries) {
    const key = dateKey(entry.createdAt);
    const group = groups.get(key) ?? {
      label: dayLabel(entry.createdAt),
      entries: [],
    };
    group.entries.push(entry);
    groups.set(key, group);
  }

  return Array.from(groups.values());
}

export function Timeline({
  entries,
  emptyTitle,
  emptyDescription,
}: {
  entries: TimelineEntry[];
  emptyTitle: string;
  emptyDescription: string;
}) {
  if (entries.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-border px-6 py-10 text-center">
        <p className="text-sm font-medium text-foreground">{emptyTitle}</p>
        <p className="mt-1 text-xs text-muted-foreground">{emptyDescription}</p>
      </div>
    );
  }

  return (
    <div className="w-full">
      {groupByDay(entries).map((group) => (
        <TimelineDaySection key={group.label} label={group.label} entries={group.entries} />
      ))}
    </div>
  );
}

function TimelineDaySection({
  label,
  entries,
}: {
  label: string;
  entries: TimelineEntry[];
}) {
  return (
    <section className="mt-6 first:mt-0">
      <div className="mb-3 flex items-center gap-3 py-2">
        <span className="text-[11px] font-medium uppercase tracking-[0.2em] text-muted-foreground">
          {label}
        </span>
        <span className="h-px flex-1 bg-border" />
        <span className="text-[11px] tabular-nums text-muted-foreground">
          {entries.length}
        </span>
      </div>

      <ol className="relative pl-8">
        <span
          aria-hidden="true"
          className="absolute bottom-3 left-[15px] top-3 w-px bg-border"
        />
        {entries.map((entry) => {
          const Icon = entry.icon;

          return (
            <li key={entry.key} className="relative py-2.5">
              <span
                className={`absolute -left-8 top-3 z-10 grid size-[30px] place-items-center rounded-full ring-4 ring-background ${entry.tone}`}
              >
                <Icon aria-hidden="true" className="size-3.5" />
              </span>

              <article className="rounded-xl border border-border bg-card px-4 py-3">
                {entry.render()}
              </article>
            </li>
          );
        })}
      </ol>
    </section>
  );
}

export function OrderActivityFeed({ activities }: { activities: OrderActivity[] }) {
  const entries: TimelineEntry[] = activities.map((activity, index) => {
    const { Icon, tone } = styleFor(activity.action);
    const actor = activity.actor_name
      ? `${activity.actor_name}${activity.actor_role ? ` (${activity.actor_role})` : ""}`
      : null;

    return {
      key: `${activity.created_at ?? "unknown"}-${activity.action}-${index}`,
      createdAt: activity.created_at,
      icon: Icon,
      tone,
      render: () => (
        <div className="flex items-start gap-3">
          <span className="grid size-7 shrink-0 place-items-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground">
            {initialsFor(activity)}
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
              <h4 className="text-sm font-medium text-foreground">
                {activity.action}
              </h4>
              <time
                dateTime={activity.created_at ?? undefined}
                className="shrink-0 text-[11px] tabular-nums text-muted-foreground"
              >
                {timeLabel(activity.created_at)}
              </time>
            </div>

            {actor ? (
              <p className="mt-0.5 text-xs text-muted-foreground">
                {actor}
              </p>
            ) : null}

            {activity.details ? (
              <p className="mt-2 text-sm text-foreground/85">
                {activity.details}
              </p>
            ) : null}

            {activity.remarks ? (
              <div className="mt-2 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                <span className="font-medium text-foreground">Remarks:</span>{" "}
                {activity.remarks}
              </div>
            ) : null}
          </div>
        </div>
      ),
    };
  });

  return (
    <Timeline
      entries={entries}
      emptyTitle="No updates yet"
      emptyDescription="Order changes will appear here when they are recorded."
    />
  );
}
