"use client";
// beui.dev/components/motion/bottom-sheet

import {
  AnimatePresence,
  motion,
  type PanInfo,
  useDragControls,
  useReducedMotion,
} from "motion/react";
import { type ReactNode, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { EASE_DRAWER } from "@/lib/ease";
import { PresenceGate } from "@/lib/presence-gate";
import { TOUCH_GESTURE_CONTENT_CLASS } from "@/lib/touch";
import { cn } from "@/lib/utils";

// Vaul-style glide: a long, fully-damped tween reads smoother than a spring on
// open — no settle/overshoot, just one clean decel. Same curve drives the
// backdrop fade so the surface and scrim move as one.
const DRAWER = { duration: 0.5, ease: EASE_DRAWER } as const;

export interface BottomSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Heights (0-1 = fraction of viewport, or "auto"). First entry is default. */
  snapPoints?: (number | "auto")[];
  defaultSnap?: number;
  title?: string;
  description?: string;
  children?: ReactNode;
  /** Actions stay visible while the body scrolls. */
  footer?: ReactNode;
  className?: string;
  /** Min drag distance (px) past current snap to dismiss. */
  dismissThreshold?: number;
}

export function BottomSheet({
  open,
  onOpenChange,
  snapPoints = [0.5, 0.92],
  defaultSnap = 0,
  title,
  description,
  children,
  footer,
  className,
  dismissThreshold = 120,
}: BottomSheetProps) {
  const [snap, setSnap] = useState(defaultSnap);
  const [mounted, setMounted] = useState(false);
  const [viewport, setViewport] = useState<{ height: number; bottom: number; left: number; right: number } | null>(null);
  const onOpenChangeRef = useRef(onOpenChange);
  const dragControls = useDragControls();
  const sheetRef = useRef<HTMLDivElement>(null);
  const reduce = useReducedMotion();
  const heightRef = useRef(0);
  const uid = useId();
  const titleId = `${uid}-title`;
  const descriptionId = `${uid}-description`;

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    onOpenChangeRef.current = onOpenChange;
  }, [onOpenChange]);

  useEffect(() => {
    if (!open) return;
    const visible = window.visualViewport;
    const update = () => {
      const height = visible?.height ?? window.innerHeight;
      const left = visible?.offsetLeft ?? 0;
      setViewport({
        height,
        bottom: Math.max(0, window.innerHeight - height - (visible?.offsetTop ?? 0)),
        left,
        right: Math.max(0, window.innerWidth - (visible?.width ?? window.innerWidth) - left),
      });
    };
    update();
    visible?.addEventListener("resize", update);
    visible?.addEventListener("scroll", update);
    window.addEventListener("resize", update);
    return () => {
      visible?.removeEventListener("resize", update);
      visible?.removeEventListener("scroll", update);
      window.removeEventListener("resize", update);
    };
  }, [open]);

  useEffect(() => {
    if (open) setSnap(defaultSnap);
  }, [open, defaultSnap]);

  // Lock background scroll while open. overflow:hidden alone is ignored by
  // iOS Safari — boundary scrolls inside the sheet chain to the page, which
  // scrolls underneath and ends up somewhere else on close. position:fixed
  // is the lock that actually holds; restore the scroll position after.
  useEffect(() => {
    if (!open) return;
    const body = document.body;
    const root = document.documentElement;
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    const rootOverflow = root.style.overflow;
    const rootOverscrollBehavior = root.style.overscrollBehavior;
    const prev = {
      position: body.style.position,
      top: body.style.top,
      left: body.style.left,
      right: body.style.right,
      overflow: body.style.overflow,
      touchAction: body.style.touchAction,
      overscrollBehavior: body.style.overscrollBehavior,
    };
    body.style.position = "fixed";
    body.style.top = `-${scrollY}px`;
    body.style.left = "0";
    body.style.right = "0";
    body.style.overflow = "hidden";
    root.style.overflow = "hidden";
    root.style.overscrollBehavior = "none";
    body.style.overscrollBehavior = "none";

    // Portal content (product suggestions or a nested dialog) may scroll too.
    // Everything else, including the backdrop and background tables, is locked.
    const isInsideOverlay = (target: EventTarget | null) => target instanceof Element && (
      sheetRef.current?.contains(target)
      || target.closest('[data-modal-portal], [role="dialog"][aria-modal="true"]') !== null
    );
    const preventOutsideScroll = (event: WheelEvent | TouchEvent) => {
      if (!isInsideOverlay(event.target) && event.cancelable) event.preventDefault();
    };
    document.addEventListener("wheel", preventOutsideScroll, { passive: false, capture: true });
    document.addEventListener("touchmove", preventOutsideScroll, { passive: false, capture: true });

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        onOpenChangeRef.current(false);
      } else if ([" ", "PageUp", "PageDown", "Home", "End", "ArrowUp", "ArrowDown"].includes(event.key) && !isInsideOverlay(event.target)) {
        event.preventDefault();
      }
    };
    window.addEventListener("keydown", onKey);

    return () => {
      window.removeEventListener("keydown", onKey);
      document.removeEventListener("wheel", preventOutsideScroll, true);
      document.removeEventListener("touchmove", preventOutsideScroll, true);
      body.style.position = prev.position;
      body.style.top = prev.top;
      body.style.left = prev.left;
      body.style.right = prev.right;
      body.style.overflow = prev.overflow;
      body.style.touchAction = prev.touchAction;
      body.style.overscrollBehavior = prev.overscrollBehavior;
      root.style.overflow = rootOverflow;
      root.style.overscrollBehavior = rootOverscrollBehavior;
      window.scrollTo(scrollX, scrollY);
    };
  }, [open]);

  const onDragEnd = (_: unknown, info: PanInfo) => {
    const velocity = info.velocity.y;
    const offset = info.offset.y;

    // Strong downward fling or large drag → dismiss.
    if (velocity > 600 || offset > dismissThreshold) {
      const smaller = snapPoints.map((_, i) => i).filter((i) => i < snap);
      if (smaller.length && velocity < 800 && offset < dismissThreshold * 1.6) {
        setSnap(smaller[smaller.length - 1]);
      } else {
        onOpenChange(false);
      }
      return;
    }

    // Strong upward fling → next snap.
    if (velocity < -500) {
      setSnap((current) => Math.min(snapPoints.length - 1, current + 1));
      return;
    }

    // Otherwise snap to nearest by current offset.
    setSnap((current) => {
      if (offset > 80 && current > 0) return current - 1;
      if (offset < -80 && current < snapPoints.length - 1) return current + 1;
      return current;
    });
  };

  const snapValue = snapPoints[snap];
  // dvh handles browser chrome, but not keyboards that only resize the visual
  // viewport. Use its actual bounds for both sizing and bottom anchoring.
  const heightStyle =
    snapValue === "auto"
      ? { maxHeight: viewport ? viewport.height * 0.92 : "92dvh" }
      : { height: viewport ? viewport.height * snapValue : `${snapValue * 100}dvh` };

  // Portal to <body>: an ancestor with backdrop-filter or transform becomes
  // the containing block for fixed descendants, which would position the
  // sheet against that ancestor instead of the viewport.
  if (!mounted) return null;

  // Two fixed siblings, no wrapper: the scrim spans the viewport edges but
  // carries a colour, and the sheet is pinned to the bottom, stops short of the
  // top edge at every snap point the component ships, and paints an opaque
  // surface either way. Both hang off `PresenceGate`, so interaction releases in
  // the same commit that starts the exit rather than when it ends. See
  // tests/fixed-overlay-edge-sampling.test.tsx.
  return createPortal(
    <AnimatePresence>
      {open ? (
        <PresenceGate key="backdrop">
          {({ gate }) => (
            <motion.button
              type="button"
              aria-label="Close bottom sheet"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={DRAWER}
              {...gate}
              onClick={() => onOpenChange(false)}
              // A dim scrim with a light blur. backdrop-blur is GPU-expensive and
              // re-rasterizes every frame the sheet drags over it; a small radius
              // plus more opacity keeps the glass look without the jank.
              className="pointer-events-auto fixed inset-0 z-50 bg-background/40 backdrop-blur-sm"
            />
          )}
        </PresenceGate>
      ) : null}
      {open ? (
        <PresenceGate key="sheet">
          {({ gate }) => (
            <motion.div
              ref={sheetRef}
              drag="y"
              dragControls={dragControls}
              dragListener={false}
              dragConstraints={{ top: 0, bottom: 0 }}
              dragElastic={{ top: 0.02, bottom: 0.4 }}
              dragMomentum={false}
              onDragEnd={onDragEnd}
              initial={reduce ? { y: 0, opacity: 0 } : { y: "100%" }}
              animate={reduce ? { y: 0, opacity: 1 } : { y: 0 }}
              exit={reduce ? { y: 0, opacity: 0 } : { y: "100%" }}
              transition={reduce ? { duration: 0.18, ease: EASE_DRAWER } : DRAWER}
              onAnimationComplete={() => {
                if (sheetRef.current)
                  heightRef.current = sheetRef.current.offsetHeight;
              }}
              {...gate}
              style={{ ...heightStyle, bottom: viewport?.bottom, left: viewport?.left, right: viewport?.right, ...gate.style }}
              className={cn(
                "pointer-events-auto fixed bottom-0 left-0 right-0 z-50 mx-auto flex max-w-2xl flex-col overflow-hidden rounded-t-3xl will-change-transform",
                "border border-border bg-background shadow-xl",
                className,
              )}
              role="dialog"
              aria-modal="true"
              aria-labelledby={title ? titleId : undefined}
              aria-describedby={description ? descriptionId : undefined}
              aria-label={title ? undefined : "Bottom sheet"}
            >
              <div className="flex shrink-0 flex-col items-center px-4 pb-2 pt-3">
                {/* Drag only the pill so the title and description stay selectable. */}
                <div
                  onPointerDown={(event) => dragControls.start(event)}
                  // A slow pull must not hand the gesture to iOS's callout,
                  // which would leave the sheet frozen mid-drag.
                  className={cn(
                    "flex cursor-grab touch-none items-center justify-center py-1 active:cursor-grabbing",
                    TOUCH_GESTURE_CONTENT_CLASS,
                  )}
                >
                  <div className="h-1.5 w-10 rounded-full bg-muted-foreground/40" />
                </div>
                {title || description ? (
                  <div className="mt-2 w-full">
                    {title ? (
                      <h2
                        id={titleId}
                        className="text-base font-semibold text-foreground"
                      >
                        {title}
                      </h2>
                    ) : null}
                    {description ? (
                      <p
                        id={descriptionId}
                        className="mt-0.5 text-sm text-muted-foreground"
                      >
                        {description}
                      </p>
                    ) : null}
                  </div>
                ) : null}
              </div>
              {/* overscroll-contain stops boundary scrolls from chaining to the page. */}
              <div
                className={cn(
                  "min-h-0 flex-auto touch-pan-y overflow-y-auto overscroll-contain px-4 pb-4",
                  // A thin, styled scrollbar instead of the platform default.
                  // Content sized right at the sheet's max-height boundary can
                  // tip into a 1-2px overflow on some machines (font-hinting
                  // and OS text-scaling differences), and Windows' classic
                  // scrollbar reserves ~15-17px of width when that happens --
                  // enough to reflow wrapped text and grow the content further.
                  // A slim scrollbar keeps that from being visually jarring
                  // and keeps its width from meaningfully affecting layout.
                  "[scrollbar-width:thin] [scrollbar-color:theme(colors.border)_transparent]",
                  "[&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent",
                  "[&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border",
                )}
              >
                {children}
              </div>
              {footer ? (
                <div className="flex shrink-0 items-center justify-end gap-2 border-t border-border bg-background px-4 pb-[max(env(safe-area-inset-bottom),0.75rem)] pt-3">
                  {footer}
                </div>
              ) : null}
            </motion.div>
          )}
        </PresenceGate>
      ) : null}
    </AnimatePresence>,
    document.body,
  );
}
