"use client";

import {
  useCallback,
  useId,
  useMemo,
  useRef,
  useState,
} from "react";
import { motion, useReducedMotion } from "motion/react";

const DISCLOSE = {
  type: "spring",
  stiffness: 480,
  damping: 40,
  mass: 0.6,
} as const;

const CHEVRON = {
  type: "spring",
  stiffness: 700,
  damping: 46,
  mass: 0.5,
} as const;

// Matches the Table component's row-hover slide (motion/table/index.tsx) --
// same spring so the two hover treatments feel identical.
const ROW_HOVER_SLIDE = {
  type: "spring",
  stiffness: 700,
  damping: 46,
  mass: 0.5,
} as const;
const ROW_HOVER_NONE = { duration: 0 } as const;

const NONE: readonly string[] = [];

export type AccordionEntry = {
  id: string;
};

export type AccordionHeaderProps = {
  id: string;
  ref: (node: HTMLButtonElement | null) => void;
  type: "button";
  onClick: () => void;
  onKeyDown: (event: React.KeyboardEvent) => void;
  "aria-expanded": boolean;
  "aria-controls": string;
};

export type AccordionPanelProps = {
  id: string;
  role: "region";
  "aria-labelledby": string;
  "aria-hidden": true | undefined;
};

export type UseAccordionOptions = {
  items: readonly AccordionEntry[];
  type?: "single" | "multiple";
  defaultOpen?: readonly string[];
  open?: readonly string[];
  onOpenChange?: (open: string[]) => void;
  collapsible?: boolean;
};

export type UseAccordionResult = {
  open: string[];
  isOpen: (id: string) => boolean;
  toggle: (id: string) => void;
  headerProps: (id: string) => AccordionHeaderProps;
  panelProps: (id: string) => AccordionPanelProps;
};

export function useAccordion({
  items,
  type = "single",
  defaultOpen = NONE,
  open: controlled,
  onOpenChange,
  collapsible = true,
}: UseAccordionOptions): UseAccordionResult {
  const base = useId();
  const normalizeOpen = useCallback(
    (value: readonly string[]) => (type === "single" ? value.slice(0, 1) : value.slice()),
    [type],
  );

  const [uncontrolled, setUncontrolled] = useState<string[]>(() => normalizeOpen(defaultOpen));

  const open = useMemo(
    () => normalizeOpen(controlled ?? uncontrolled),
    [controlled, normalizeOpen, uncontrolled],
  );

  const headers = useRef(new Map<string, HTMLButtonElement>());
  const binders = useRef(new Map<string, AccordionHeaderProps["ref"]>());

  const headerRef = useCallback((id: string): AccordionHeaderProps["ref"] => {
    const cached = binders.current.get(id);
    if (cached) return cached;
    const bind = (node: HTMLButtonElement | null) => {
      if (node) headers.current.set(id, node);
      else headers.current.delete(id);
    };
    binders.current.set(id, bind);
    return bind;
  }, []);

  const changed = useRef(onOpenChange);
  changed.current = onOpenChange;

  const commit = useCallback((next: string[]) => {
    const normalized = normalizeOpen(next);
    setUncontrolled(normalized);
    changed.current?.(normalized);
  }, [normalizeOpen]);

  const isOpen = useCallback((id: string) => open.includes(id), [open]);

  const toggle = useCallback(
    (id: string) => {
      const active = open.includes(id);
      if (active && !collapsible && type === "single") return;
      if (type === "single") {
        commit(active ? [] : [id]);
        return;
      }
      commit(active ? open.filter((x) => x !== id) : [...open, id]);
    },
    [open, type, collapsible, commit],
  );

  const order = useMemo(() => items.map((item) => item.id), [items]);

  const move = useCallback(
    (id: string, delta: number, edge: "first" | "last" | null) => {
      if (order.length === 0) return;
      const at = order.indexOf(id);
      if (at < 0) return;
      const next =
        edge === "first"
          ? 0
          : edge === "last"
            ? order.length - 1
            : (at + delta + order.length) % order.length;
      headers.current.get(order[next])?.focus();
    },
    [order],
  );

  const headerProps = useCallback(
    (id: string): AccordionHeaderProps => ({
      id: `${base}-header-${id}`,
      ref: headerRef(id),
      type: "button",
      onClick: () => toggle(id),
      onKeyDown: (event: React.KeyboardEvent) => {
        if (event.key === "ArrowDown") {
          event.preventDefault();
          move(id, 1, null);
        } else if (event.key === "ArrowUp") {
          event.preventDefault();
          move(id, -1, null);
        } else if (event.key === "Home") {
          event.preventDefault();
          move(id, 0, "first");
        } else if (event.key === "End") {
          event.preventDefault();
          move(id, 0, "last");
        }
      },
      "aria-expanded": open.includes(id),
      "aria-controls": `${base}-panel-${id}`,
    }),
    [base, open, toggle, move, headerRef],
  );

  const panelProps = useCallback(
    (id: string): AccordionPanelProps => ({
      id: `${base}-panel-${id}`,
      role: "region",
      "aria-labelledby": `${base}-header-${id}`,
      "aria-hidden": open.includes(id) ? undefined : true,
    }),
    [base, open],
  );

  return { open, isOpen, toggle, headerProps, panelProps };
}

export type AccordionItem = {
  id: string;
  title: React.ReactNode;
  content: React.ReactNode;
  meta?: React.ReactNode;
};

export type AccordionProps = {
  items: readonly AccordionItem[];
  type?: "single" | "multiple";
  defaultOpen?: readonly string[];
  open?: readonly string[];
  onOpenChange?: (open: string[]) => void;
  collapsible?: boolean;
  maxPanelHeight?: number;
  headingLevel?: number;
  className?: string;
};

export function Accordion({
  items,
  type = "single",
  defaultOpen = NONE,
  open: controlled,
  onOpenChange,
  collapsible = true,
  maxPanelHeight = 220,
  headingLevel = 3,
  className = "",
}: AccordionProps) {
  const reduced = useReducedMotion();
  const [hoveredId, setHoveredId] = useState<string | null>(null);

  const entries = useMemo(() => items.map(({ id }) => ({ id })), [items]);

  const { isOpen, headerProps, panelProps } = useAccordion({
    items: entries,
    type,
    defaultOpen,
    open: controlled,
    onOpenChange,
    collapsible,
  });

  return (
    <div
      onPointerLeave={() => setHoveredId(null)}
      className={`divide-y divide-stone-200 overflow-hidden rounded-[11px] border border-stone-200 bg-white shadow-[0_1px_2px_rgba(28,25,23,0.06),0_4px_10px_-8px_rgba(28,25,23,0.45)] dark:divide-white/10 dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:shadow-[0_1px_6px_rgba(0,0,0,0.45)] ${className}`}
    >
      {items.map((item) => (
        <AccordionRow
          key={item.id}
          item={item}
          open={isOpen(item.id)}
          reduced={Boolean(reduced)}
          maxPanelHeight={maxPanelHeight}
          headingLevel={headingLevel}
          header={headerProps(item.id)}
          panel={panelProps(item.id)}
          hovered={hoveredId === item.id}
          onHoverStart={(e) => {
            if (e.pointerType !== "touch") setHoveredId(item.id);
          }}
        />
      ))}
    </div>
  );
}

function AccordionRow({
  item,
  open,
  reduced,
  maxPanelHeight,
  headingLevel,
  header,
  panel,
  hovered,
  onHoverStart,
}: {
  item: AccordionItem;
  open: boolean;
  reduced: boolean;
  maxPanelHeight: number;
  headingLevel: number;
  header: AccordionHeaderProps;
  panel: AccordionPanelProps;
  hovered: boolean;
  onHoverStart: (event: React.PointerEvent) => void;
}) {
  return (
    <div>
      <div role="heading" aria-level={headingLevel} className="relative" onPointerEnter={onHoverStart}>
        {hovered ? (
          <motion.div
            aria-hidden
            layoutId="accordion-row-hover"
            className="pointer-events-none absolute inset-0 z-0 bg-muted/50"
            transition={reduced ? ROW_HOVER_NONE : ROW_HOVER_SLIDE}
          />
        ) : null}
        <button
          {...header}
          className="relative z-10 flex w-full items-center gap-3 px-4 py-4 text-left outline-none transition-colors duration-150 focus-visible:bg-[#4568FF]/[0.06] focus-visible:shadow-[inset_0_0_0_1px_#4568FF] dark:focus-visible:bg-[#93B0FF]/[0.1] dark:focus-visible:shadow-[inset_0_0_0_1px_#93B0FF]"
        >
          <span
            className={`min-w-0 flex-1 truncate text-base font-medium transition-colors duration-150 ${
              open
                ? "text-stone-900 dark:text-stone-50"
                : "text-stone-800 dark:text-stone-200"
            }`}
          >
            {item.title}
          </span>

          {item.meta ? (
            <span className="shrink-0 text-sm tabular-nums text-stone-700 dark:text-stone-300">
              {item.meta}
            </span>
          ) : null}

          <motion.svg
            width="18"
            height="18"
            viewBox="0 0 256 256"
            fill="none"
            aria-hidden="true"
            className="shrink-0 text-stone-600 dark:text-stone-300"
            initial={false}
            animate={{ rotate: open ? 180 : 0 }}
            transition={reduced ? { duration: 0 } : CHEVRON}
          >
            <path
              d="M208 96l-80 80-80-80"
              stroke="currentColor"
              strokeWidth="16"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </motion.svg>
        </button>
      </div>
      {open && (
        <motion.div
          initial={reduced ? { height: 0 } : { height: 0, opacity: 0 }}
          animate={{ height: "auto", opacity: 1 }}
          transition={reduced ? { duration: 0 } : DISCLOSE}
          style={{ overflow: "hidden" }}
        >
          <div
            {...panel}
            className="border-t border-stone-200 bg-stone-50 shadow-[inset_0_1px_2px_rgba(28,25,23,0.05)] dark:border-white/[0.16] dark:bg-white/[0.05] dark:shadow-[inset_0_1px_2px_rgba(0,0,0,0.3)]"
            style={{
              maxHeight: maxPanelHeight,
              overflowY: "auto",
              overscrollBehavior: "contain",
              scrollbarGutter: "stable",
            }}
          >
            <div className="px-4 pb-4 pt-3.5 text-base leading-relaxed text-stone-700 dark:text-stone-300">
              {item.content}
            </div>
          </div>
        </motion.div>
      )}
    </div>
  );
}
