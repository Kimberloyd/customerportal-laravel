import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Owns the query text, open/active state, and the outside-click/reposition
// plumbing needed to float a suggestion list off a search input inside a
// scrollable modal. Selection semantics differ per field, so that stays
// with the caller.
//
// `hasEmptyStateContent` lets the menu stay open on an empty query when the
// caller has something worth showing anyway (recent picks, popular picks) --
// see the search-bar-ux skill's "Empty Isn't Empty" rule. Pass a plain
// boolean (e.g. `recent.length > 0`); it's read fresh each render, no need
// to memoize it.
export function useSuggestField(hasEmptyStateContent = false) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [position, setPosition] = useState(null);
    const fieldRef = useRef(null);
    const menuRef = useRef(null);
    const visible = open && (query.trim() !== '' || hasEmptyStateContent);

    // Portaled to <body> (like Dropdown's `portal` mode) so the list can
    // extend past the modal's own scrollable, overflow-hidden body instead
    // of being clipped by it.
    useEffect(() => {
        if (!visible) {
            setPosition(null);
            return undefined;
        }

        const reposition = () => {
            const rect = fieldRef.current?.getBoundingClientRect();
            if (!rect) return;
            const margin = 6;
            const preferredHeight = 256;
            const viewportTop = window.visualViewport?.offsetTop ?? 0;
            const viewportBottom = viewportTop + (window.visualViewport?.height ?? window.innerHeight);
            const spaceBelow = viewportBottom - rect.bottom - margin;
            const spaceAbove = rect.top - viewportTop - margin;
            // Flip above the field when there isn't enough room below but there
            // is more room above -- keeps the list from being squeezed to a
            // sliver (or clipped) near the bottom of a short viewport, e.g.
            // inside the mobile bottom sheet.
            const placeAbove = spaceBelow < Math.min(preferredHeight, 160) && spaceAbove > spaceBelow;
            const maxHeight = Math.max(0, Math.min(preferredHeight, placeAbove ? spaceAbove : spaceBelow));
            setPosition({
                left: rect.left,
                width: rect.width,
                maxHeight,
                ...(placeAbove
                    ? { bottom: window.innerHeight - rect.top + margin }
                    : { top: rect.bottom + margin }),
            });
        };

        reposition();

        // The field can sit inside an animated container (a BottomSheet
        // sliding up over ~500ms -- see motion/bottom-sheet.tsx's DRAWER
        // transition) that moves the field via a transform, which fires no
        // scroll/resize event at all. A one-shot measurement taken as the
        // sheet opens captures a mid-animation position and is never
        // corrected, leaving the menu stranded wherever the field happened
        // to be at that instant. Re-measuring every frame for the same
        // window as that transition keeps the menu glued to the field
        // through the open animation; the field is static well before the
        // loop ends in every other case, so this is a no-op cost-wise once
        // settled.
        const settleWindowMs = 600;
        const startedAt = performance.now();
        let frame = requestAnimationFrame(function trackDuringOpenAnimation(now) {
            reposition();
            if (now - startedAt < settleWindowMs) {
                frame = requestAnimationFrame(trackDuringOpenAnimation);
            }
        });

        const dismiss = () => setOpen(false);
        // Capture-phase scroll listeners see scroll events from any
        // descendant, including the menu scrolling itself -- ignore those so
        // scrolling through the results doesn't close the list. A scroll
        // inside the field's own container (a modal/sheet's scrollable
        // body) moves the field itself, so reposition instead of dismissing
        // -- only an unrelated/background scroll dismisses.
        const dismissUnlessMenuScroll = (event) => {
            if (menuRef.current?.contains(event.target)) return;
            if (fieldRef.current && event.target instanceof Node && event.target.contains(fieldRef.current)) {
                reposition();
                return;
            }
            dismiss();
        };
        window.addEventListener('resize', dismiss);
        window.addEventListener('scroll', dismissUnlessMenuScroll, true);
        window.visualViewport?.addEventListener('resize', reposition);
        window.visualViewport?.addEventListener('scroll', reposition);
        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('resize', dismiss);
            window.removeEventListener('scroll', dismissUnlessMenuScroll, true);
            window.visualViewport?.removeEventListener('resize', reposition);
            window.visualViewport?.removeEventListener('scroll', reposition);
        };
    }, [visible]);

    useEffect(() => {
        if (!open) return undefined;
        const onPointerDown = (event) => {
            if (
                !fieldRef.current?.contains(event.target)
                && !menuRef.current?.contains(event.target)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener('pointerdown', onPointerDown, true);
        return () => document.removeEventListener('pointerdown', onPointerDown, true);
    }, [open]);

    return { query, setQuery, open, setOpen, activeIndex, setActiveIndex, position, visible, fieldRef, menuRef };
}

export function suggestFieldKeyDown(field, matches, onSelect) {
    return (event) => {
        if (!field.open || matches.length === 0) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            field.setActiveIndex((index) => Math.min(matches.length - 1, index + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            field.setActiveIndex((index) => Math.max(0, index - 1));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = matches[field.activeIndex];
            if (item) onSelect(item);
        } else if (event.key === 'Escape') {
            field.setOpen(false);
        }
    };
}

// Keeps the menu's own radius (and its items' nested radius -- outer minus
// the p-[5px] padding, per the corner-radius-system nested formula) tied to
// whichever radius the caller's own input field actually uses, instead of a
// fixed rounded-xl that only matched some of them.
const RADIUS = {
    md: { outer: 'rounded-md', inner: 'rounded-[1px]' },
    lg: { outer: 'rounded-lg', inner: 'rounded-[3px]' },
    xl: { outer: 'rounded-xl', inner: 'rounded-[7px]' },
};

export function SuggestionMenu({ menuRef, position, items, activeIndex, onHover, onSelect, emptyMessage, onClear, heading, radius = 'xl' }) {
    const { outer, inner } = RADIUS[radius] ?? RADIUS.xl;

    return createPortal(
        <div
            ref={menuRef}
            data-modal-portal=""
            style={{
                position: 'fixed',
                top: position.top,
                bottom: position.bottom,
                left: position.left,
                width: position.width,
                maxHeight: position.maxHeight,
            }}
            className={`z-[60] overflow-y-auto ${outer} border border-border bg-card p-[5px]`}
        >
            {heading && items.length > 0 && (
                <div className="px-2.5 pb-1 pt-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {heading}
                </div>
            )}
            {items.length === 0 ? (
                <div className="flex items-center justify-between gap-3 px-2.5 py-2 text-sm text-muted-foreground">
                    <span>{emptyMessage}</span>
                    {onClear && (
                        <button
                            type="button"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={onClear}
                            className="shrink-0 font-medium text-primary outline-none hover:underline focus-visible:underline"
                        >
                            Clear search
                        </button>
                    )}
                </div>
            ) : (
                items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onMouseDown={(event) => event.preventDefault()}
                        onMouseEnter={() => onHover(index)}
                        onClick={() => onSelect(item)}
                        className={`flex w-full items-center gap-2 ${inner} px-2.5 py-1.5 text-left text-sm ${
                            index === activeIndex
                                ? 'bg-hover text-foreground'
                                : 'text-foreground'
                        }`}
                    >
                        <span className="min-w-0 flex-1 truncate">{item.label}</span>
                        {item.badge}
                        {item.hint ? (
                            <span className="shrink-0 text-[10.5px] tabular-nums text-muted-foreground">
                                {item.hint}
                            </span>
                        ) : null}
                    </button>
                ))
            )}
        </div>,
        document.body,
    );
}
