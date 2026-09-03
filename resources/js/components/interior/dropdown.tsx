"use client";

import { type ReactNode, useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";

const EASE = [0.23, 1, 0.32, 1] as const;

const EXIT = [0.4, 0, 1, 1] as const;
const CELL = { type: "spring", stiffness: 520, damping: 34, mass: 0.45 } as const;

const NUDGE = { type: "spring", stiffness: 700, damping: 46, mass: 0.5 } as const;
const NONE = { duration: 0 } as const;

const SLIDE = { type: "spring", stiffness: 700, damping: 46, mass: 0.5 } as const;

const ROW_H = 32;

const OPEN = { type: "spring", stiffness: 620, damping: 38, mass: 0.6 } as const;
const MENU_WIDTH = 224;
const MENU_GAP = 6;
const SEARCH_HEIGHT = 44;
const MENU_TITLE_HEIGHT = 64;

function DropdownLayer({ portal, children }: { portal: boolean; children: ReactNode }) {
  if (portal && typeof document !== "undefined") {
    return createPortal(children, document.body);
  }

  return children;
}

export type DropdownItem = {
  value: string;
  label: string;
  hint?: string;
  icon?: ReactNode;
  destructive?: boolean;
  disabled?: boolean;
};

export type UseDropdownOptions = {
  items: DropdownItem[];
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  disabled?: boolean;
  typeaheadDelay?: number;
};

export function useDropdown({
  items,
  value,
  defaultValue,
  onChange,
  disabled = false,
  typeaheadDelay = 600,
}: UseDropdownOptions) {
  const uid = useId();
  const listId = `${uid}-list`;
  const itemId = useCallback((i: number) => `${uid}-opt-${i}`, [uid]);

  const [uncontrolled, setUncontrolled] = useState<string | null>(
    defaultValue ?? null,
  );
  const selectedValue = value !== undefined ? value : uncontrolled;
  const selectedIndex = items.findIndex((it) => it.value === selectedValue);

  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);

  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const itemRefs = useRef<(HTMLLIElement | null)[]>([]);
  const viaKey = useRef(false);
  const buffer = useRef("");
  const bufferTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const emit = useRef(onChange);
  emit.current = onChange;

  const step = useCallback(
    (from: number, dir: 1 | -1) => {
      const n = items.length;
      if (n === 0) return -1;
      let i = from;
      for (let k = 0; k < n; k++) {
        i = (i + dir + n) % n;
        if (!items[i].disabled) return i;
      }
      return from;
    },
    [items],
  );

  const edge = useCallback(
    (dir: 1 | -1) => step(dir === 1 ? -1 : items.length, dir),
    [step, items.length],
  );

  const openMenu = useCallback(
    (index?: number) => {
      if (disabled || items.length === 0) return;
      const usable = selectedIndex >= 0 && !items[selectedIndex].disabled;
      viaKey.current = true;
      setActiveIndex(index ?? (usable ? selectedIndex : edge(1)));
      setOpen(true);
    },
    [disabled, items, selectedIndex, edge],
  );

  const close = useCallback((restoreFocus = true) => {
    buffer.current = "";
    setOpen(false);
    setActiveIndex(-1);
    if (restoreFocus) triggerRef.current?.focus();
  }, []);

  const select = useCallback(
    (index: number) => {
      const item = items[index];
      if (!item || item.disabled) return;
      if (value === undefined) setUncontrolled(item.value);
      emit.current?.(item.value);
      close();
    },
    [items, value, close],
  );

  const typeahead = useCallback(
    (char: string) => {
      if (bufferTimer.current) clearTimeout(bufferTimer.current);
      buffer.current += char.toLowerCase();
      bufferTimer.current = setTimeout(() => {
        buffer.current = "";
      }, typeaheadDelay);

      const q = buffer.current;
      const n = items.length;
      const from = activeIndex < 0 ? 0 : activeIndex;
      const start = q.length > 1 ? from : from + 1;
      for (let k = 0; k < n; k++) {
        const i = (start + k) % n;
        const it = items[i];
        if (!it.disabled && it.label.toLowerCase().startsWith(q)) {
          viaKey.current = true;
          setActiveIndex(i);
          return;
        }
      }
    },
    [items, activeIndex, typeaheadDelay],
  );

  useEffect(() => {
    if (!open) return;

    const frame = requestAnimationFrame(() => listRef.current?.focus());
    return () => cancelAnimationFrame(frame);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => {
      const target = e.target as Node;
      if (
        !rootRef.current?.contains(target) &&
        !menuRef.current?.contains(target)
      ) {
        close(false);
      }
    };
    const onWindowBlur = () => close(false);
    document.addEventListener("pointerdown", onDown, true);
    window.addEventListener("blur", onWindowBlur);
    return () => {
      document.removeEventListener("pointerdown", onDown, true);
      window.removeEventListener("blur", onWindowBlur);
    };
  }, [open, close]);

  useEffect(() => {
    if (!open || activeIndex < 0 || !viaKey.current) return;
    viaKey.current = false;
    itemRefs.current[activeIndex]?.scrollIntoView({ block: "nearest" });
  }, [open, activeIndex]);

  useEffect(() => {
    if (!open) return;

    setActiveIndex((current) => {
      if (items.length === 0) return -1;
      if (
        current < 0 ||
        current >= items.length ||
        items[current]?.disabled
      ) {
        return edge(1);
      }

      return current;
    });
  }, [edge, items, open]);

  useEffect(
    () => () => {
      if (bufferTimer.current) clearTimeout(bufferTimer.current);
    },
    [],
  );

  const triggerProps = {
    ref: triggerRef,
    type: "button" as const,
    disabled,
    "aria-haspopup": "listbox" as const,
    "aria-expanded": open,
    "aria-controls": open ? listId : undefined,
    onClick: () => (open ? close() : openMenu()),
    onKeyDown: (e: React.KeyboardEvent<HTMLButtonElement>) => {
      if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openMenu();
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        openMenu(edge(-1));
      }
    },
  };

  const listProps = {
    ref: listRef,
    id: listId,
    role: "listbox" as const,
    tabIndex: -1,
    "aria-activedescendant": activeIndex >= 0 ? itemId(activeIndex) : undefined,
    onKeyDown: (e: React.KeyboardEvent<HTMLUListElement>) => {
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        const dir = e.key === "ArrowDown" ? 1 : -1;
        viaKey.current = true;
        setActiveIndex((i) => step(i, dir));
      } else if (e.key === "Home" || e.key === "End") {
        e.preventDefault();
        viaKey.current = true;
        setActiveIndex(edge(e.key === "Home" ? 1 : -1));
      } else if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        select(activeIndex);
      } else if (e.key === "Escape") {
        e.preventDefault();
        close();
      } else if (e.key === "Tab") {
        e.preventDefault();
        close();
      } else if (
        e.key.length === 1 &&
        !e.metaKey &&
        !e.ctrlKey &&
        !e.altKey
      ) {
        e.preventDefault();
        typeahead(e.key);
      }
    },
  };

  const getItemProps = useCallback(
    (index: number) => ({
      id: itemId(index),
      role: "option" as const,
      "aria-selected": index === selectedIndex,
      "aria-disabled": items[index]?.disabled ? (true as const) : undefined,
      ref: (el: HTMLLIElement | null) => {
        itemRefs.current[index] = el;
      },
      onPointerMove: () => {
        if (items[index]?.disabled) return;
        viaKey.current = false;
        setActiveIndex(index);
      },
      onClick: () => select(index),
    }),
    [itemId, items, selectedIndex, select],
  );

  return {
    open,
    openMenu,
    close,
    select,
    activeIndex,
    selectedIndex,
    selectedItem: selectedIndex >= 0 ? items[selectedIndex] : null,
    itemId,
    rootRef,
    triggerRef,
    menuRef,
    listRef,
    triggerProps,
    listProps,
    getItemProps,
  };
}

export type DropdownProps = {
  items: DropdownItem[];
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  ariaInvalid?: boolean;
  ariaDescribedBy?: string;
  emptyLabel?: string;
  className?: string;
  trigger?: ReactNode;
  triggerClassName?: string;
  menuWidth?: number;
  matchTriggerWidth?: boolean;
  menuTitle?: ReactNode | ((controls: { close: (restoreFocus?: boolean) => void }) => ReactNode);
  searchable?: boolean;
  searchPlaceholder?: string;
  align?: "left" | "right";
  portal?: boolean;
  /**
   * Whether scrolling the page while the menu is open dismisses it
   * (the default -- correct for a trigger embedded in a scrollable
   * list/table, where the trigger itself moves out from under the
   * menu). Set false for a trigger that stays fixed in place (e.g. a
   * sticky header icon) so the menu repositions with it instead,
   * matching the header's Notifications panel.
   */
  closeOnScroll?: boolean;
};

export function Dropdown({
  items,
  value,
  defaultValue,
  onChange,
  label = "Options",
  placeholder = "Select an option",
  disabled = false,
  ariaInvalid,
  ariaDescribedBy,
  emptyLabel = "Nothing to choose",
  className = "",
  trigger,
  triggerClassName,
  menuWidth,
  matchTriggerWidth = false,
  menuTitle,
  searchable = false,
  searchPlaceholder = "Search options",
  align = "left",
  portal = false,
  closeOnScroll = true,
}: DropdownProps) {
  const reduced = useReducedMotion();
  const [searchQuery, setSearchQuery] = useState("");
  const searchInputRef = useRef<HTMLInputElement>(null);
  const visibleItems = useMemo(() => {
    const query = searchQuery.trim().toLocaleLowerCase();
    if (!searchable || query === "") return items;

    return items.filter((item) =>
      [item.label, item.hint].some((value) =>
        String(value ?? "").toLocaleLowerCase().includes(query),
      ),
    );
  }, [items, searchQuery, searchable]);
  const {
    open,
    activeIndex,
    selectedIndex,
    selectedItem,
    rootRef,
    triggerRef,
    menuRef,
    listRef,
    select,
    close,
    triggerProps,
    listProps,
    getItemProps,
  } = useDropdown({ items: visibleItems, value, defaultValue, onChange, disabled });

  const cell = reduced ? NONE : CELL;
  const [portalPosition, setPortalPosition] = useState<{
    top: number;
    left: number;
    width?: number;
  } | null>(null);

  useEffect(() => {
    if (!open) {
      setSearchQuery("");
      return;
    }

    if (!searchable) return;
    const frame = requestAnimationFrame(() => searchInputRef.current?.focus());
    return () => cancelAnimationFrame(frame);
  }, [open, searchable]);

  useEffect(() => {
    if (!open || !portal) {
      setPortalPosition(null);
      return;
    }

    const computePosition = () => {
      const triggerRect = triggerRef.current?.getBoundingClientRect();
      if (!triggerRect) return;

      const menuChromeHeight =
        (searchable ? SEARCH_HEIGHT : 0) +
        (menuTitle != null ? MENU_TITLE_HEIGHT : 0);
      const menuHeight = Math.min(
        visibleItems.length * ROW_H + 10 + menuChromeHeight,
        226 + menuChromeHeight,
      );
      const resolvedMenuWidth = Math.min(
        matchTriggerWidth ? triggerRect.width : (menuWidth ?? MENU_WIDTH),
        window.innerWidth - 16,
      );
      const hasRoomBelow = triggerRect.bottom + MENU_GAP + menuHeight <= window.innerHeight - 8;
      const top = hasRoomBelow
        ? triggerRect.bottom + MENU_GAP
        : Math.max(8, triggerRect.top - MENU_GAP - menuHeight);
      const preferredLeft = align === "right"
        ? triggerRect.right - resolvedMenuWidth
        : triggerRect.left;

      setPortalPosition({
        top,
        left: Math.min(
          Math.max(8, preferredLeft),
          window.innerWidth - resolvedMenuWidth - 8,
        ),
        ...(!matchTriggerWidth && menuWidth == null ? {} : { width: resolvedMenuWidth }),
      });
    };

    computePosition();

    // A trigger embedded in a scrollable list/table moves out from under
    // the menu when that list scrolls, so closing is correct there; a
    // trigger fixed in place (closeOnScroll=false) just needs the menu to
    // follow it, exactly like the header's Notifications panel.
    const onScrollOrResize = closeOnScroll ? () => close(false) : computePosition;
    window.addEventListener("resize", onScrollOrResize);
    window.addEventListener("scroll", onScrollOrResize, true);

    return () => {
      window.removeEventListener("resize", onScrollOrResize);
      window.removeEventListener("scroll", onScrollOrResize, true);
    };
  }, [align, close, closeOnScroll, matchTriggerWidth, menuTitle, menuWidth, open, portal, searchable, triggerRef, visibleItems.length]);

  return (
    <div ref={rootRef} className={`relative inline-block text-left ${className}`}>
      <button
        {...triggerProps}
        aria-label={trigger ? label : undefined}
        aria-invalid={ariaInvalid || undefined}
        aria-describedby={ariaDescribedBy}
        className={
          triggerClassName ??
          "flex h-9 select-none items-center gap-2 whitespace-nowrap rounded-[9px] border border-stone-200 bg-white px-3 text-sm font-medium text-foreground shadow-none outline-none transition-colors duration-150 hover:border-stone-300 focus-visible:border-stone-400 disabled:opacity-50 dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:text-stone-200 dark:hover:border-white/20 dark:focus-visible:border-white/30"
        }
      >
        {trigger ?? (
          <>
            <span className="sr-only">
              {label}: {selectedItem ? selectedItem.label : placeholder}
            </span>
            <span aria-hidden>{label}</span>
            <motion.svg
              aria-hidden
              viewBox="0 0 12 12"
              className="size-3 shrink-0 text-stone-500 dark:text-stone-400"
              initial={false}
              animate={{ rotate: open ? 180 : 0 }}
              transition={reduced ? NONE : NUDGE}
            >
              <path
                d="M3 4.75 6 7.75 9 4.75"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.4"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </motion.svg>
          </>
        )}
      </button>
      <DropdownLayer portal={portal}>
        <AnimatePresence>
        {open && (!portal || portalPosition) && (
          <motion.div
            ref={menuRef}
            data-modal-portal={portal ? "" : undefined}
            initial={reduced ? { opacity: 0 } : { opacity: 0, scale: 0.94, y: -8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{
              opacity: 0,
              scale: 0.97,
              y: -6,
              transition: reduced ? NONE : { duration: 0.12, ease: EXIT },
            }}
            transition={
              reduced
                ? NONE
                : { ...OPEN, opacity: { duration: 0.12, ease: EASE } }
            }
            style={{
              transformOrigin: align === "right" ? "top right" : "top left",
              ...(portal && portalPosition ? portalPosition : {}),
              ...(!portal && matchTriggerWidth ? { width: "100%" } : {}),
              ...(!portal && !matchTriggerWidth && menuWidth != null ? { width: menuWidth } : {}),
            }}
            className={`${portal ? "fixed z-[60]" : `absolute top-[calc(100%+6px)] z-50 ${align === "right" ? "right-0" : "left-0"}`} min-w-[224px] whitespace-nowrap rounded-[11px] border border-stone-200 bg-white p-[5px] shadow-[0_1px_2px_rgba(28,25,23,0.06),0_16px_36px_-18px_rgba(28,25,23,0.5)] dark:border-white/[0.16] dark:bg-[#1D1D1A] dark:shadow-[0_2px_12px_rgba(0,0,0,0.6)]`}
          >
            {menuTitle != null ? (
              <div className="-mx-[5px] mb-1 border-b border-stone-200 px-[15px] pb-2 pt-1 text-left text-xl font-semibold text-stone-900 dark:border-white/[0.16] dark:text-stone-100">
                {typeof menuTitle === "function" ? menuTitle({ close }) : menuTitle}
              </div>
            ) : null}
            {searchable ? (
              <div className="mb-1 p-1 pb-2">
                <div className="flex h-8 items-center gap-2 rounded-full border border-stone-200 px-2.5 text-stone-500 focus-within:border-stone-400 dark:border-white/[0.16] dark:text-stone-400">
                  <svg
                    aria-hidden
                    viewBox="0 0 24 24"
                    className="size-4 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.3-4.3" />
                  </svg>
                  <input
                    ref={searchInputRef}
                    type="search"
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    onKeyDown={(event) => {
                      if (event.key === "Escape") {
                        event.preventDefault();
                        close();
                      } else if (event.key === "ArrowDown") {
                        event.preventDefault();
                        listRef.current?.focus();
                      } else if (event.key === "Enter" && activeIndex >= 0) {
                        event.preventDefault();
                        select(activeIndex);
                      }
                    }}
                    placeholder={searchPlaceholder}
                    aria-label={searchPlaceholder}
                    className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-0 dark:text-stone-100"
                  />
                </div>
              </div>
            ) : null}
            <ul
              {...listProps}
              aria-label={label}
              className="relative max-h-[216px] overflow-y-auto outline-none [scrollbar-gutter:stable] [&::-webkit-scrollbar]:h-[3px] [&::-webkit-scrollbar]:w-[3px]"
            >
              <motion.span
                aria-hidden
                className={`pointer-events-none absolute inset-x-0 top-0 h-8 rounded-[7px] transition-colors ${
                  visibleItems[activeIndex]?.destructive
                    ? "bg-destructive/10"
                    : "bg-stone-100 dark:bg-white/10"
                }`}
                initial={false}
                animate={{
                  y: activeIndex < 0 ? 0 : activeIndex * ROW_H,
                  opacity: activeIndex < 0 ? 0 : 1,
                }}
                transition={
                  reduced
                    ? NONE
                    : { ...SLIDE, opacity: { duration: 0.1, ease: EASE } }
                }
              />
              {visibleItems.map((item, i) => {
                const active = i === activeIndex && !item.disabled;
                const picked = i === selectedIndex;
                return (
                  <li
                    key={item.value}
                    {...getItemProps(i)}
                    className={`relative flex h-8 select-none items-center gap-2 rounded-[7px] px-2.5 text-sm ${
                      item.disabled
                        ? "cursor-not-allowed text-stone-500/70 dark:text-stone-400/70"
                        : item.destructive
                          ? "cursor-pointer text-destructive"
                        : active
                          ? "cursor-pointer text-stone-900 dark:text-stone-100"
                          : "cursor-pointer text-stone-700 hover:bg-stone-100 dark:text-stone-200 dark:hover:bg-white/10"
                    }`}
                  >
                    {item.icon ? (
                      <span
                        className={`relative z-10 flex size-4 shrink-0 items-center justify-center [&_svg]:size-4 ${
                          item.destructive ? "text-destructive" : ""
                        }`}
                      >
                        {item.icon}
                      </span>
                    ) : null}
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    {item.hint || picked ? (
                      <span className="ml-auto flex shrink-0 items-center gap-2">
                        {item.hint ? (
                          <span className="font-mono text-[10.5px] text-stone-500 dark:text-stone-400">
                            {item.hint}
                          </span>
                        ) : null}
                        {picked ? (
                          <motion.span
                            aria-hidden
                            initial={{ opacity: 0, scale: 0.7 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={cell}
                            className="relative flex size-[14px] shrink-0 items-center justify-center"
                          >
                            <svg viewBox="0 0 14 14" className="size-[14px]">
                              <path
                                d="M3 7.4 5.8 10.2 11 4.4"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              />
                            </svg>
                          </motion.span>
                        ) : null}
                      </span>
                    ) : null}
                  </li>
                );
              })}

              {visibleItems.length === 0 && (
                <li
                  role="presentation"
                  className="flex h-8 items-center px-2.5 text-sm text-muted-foreground dark:text-stone-400"
                >
                  {emptyLabel}
                </li>
              )}
            </ul>
          </motion.div>
        )}
        </AnimatePresence>
      </DropdownLayer>
    </div>
  );
}
