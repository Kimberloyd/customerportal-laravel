"use client";

import { useEffect, useState, type RefObject } from "react";

/**
 * True when the observed element's own inline size is at or below
 * `maxWidthPx` -- a container query, not a viewport one. `window.matchMedia`
 * answers for the whole page, which is wrong for anything that might ever
 * sit in a narrower context than full width (a side panel, a dashboard
 * widget slot); this instead reacts to the actual space the element has,
 * wherever it's placed.
 */
export function useContainerBreakpoint(
  ref: RefObject<HTMLElement | null>,
  maxWidthPx: number,
) {
  const [isCompact, setIsCompact] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el || typeof ResizeObserver === "undefined") return;
    const update = (width: number) => setIsCompact(width <= maxWidthPx);
    update(el.getBoundingClientRect().width);
    const observer = new ResizeObserver(([entry]) => {
      if (entry) update(entry.contentRect.width);
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, [ref, maxWidthPx]);

  return isCompact;
}
