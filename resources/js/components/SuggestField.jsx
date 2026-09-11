import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Owns the query text, open/active state, and the outside-click/reposition
// plumbing needed to float a suggestion list off a search input inside a
// scrollable modal. Selection semantics differ per field, so that stays
// with the caller.
export function useSuggestField() {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [position, setPosition] = useState(null);
    const fieldRef = useRef(null);
    const menuRef = useRef(null);
    const visible = open && query.trim() !== '';

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
        const dismiss = () => setOpen(false);
        // Capture-phase scroll listeners see scroll events from any
        // descendant, including the menu scrolling itself -- ignore those so
        // scrolling through the results doesn't close the list.
        const dismissUnlessMenuScroll = (event) => {
            if (menuRef.current?.contains(event.target)) return;
            dismiss();
        };
        window.addEventListener('resize', dismiss);
        window.addEventListener('scroll', dismissUnlessMenuScroll, true);
        window.visualViewport?.addEventListener('resize', reposition);
        window.visualViewport?.addEventListener('scroll', reposition);
        return () => {
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

export function SuggestionMenu({ menuRef, position, items, activeIndex, onHover, onSelect, emptyMessage }) {
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
            className="z-[60] overflow-y-auto rounded-xl border border-stone-200 bg-white p-[5px]"
        >
            {items.length === 0 ? (
                <div className="px-2.5 py-2 text-sm text-muted-foreground">{emptyMessage}</div>
            ) : (
                items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onMouseDown={(event) => event.preventDefault()}
                        onMouseEnter={() => onHover(index)}
                        onClick={() => onSelect(item)}
                        className={`flex w-full items-center gap-2 rounded-[7px] px-2.5 py-1.5 text-left text-sm ${
                            index === activeIndex
                                ? 'bg-stone-100 text-stone-900'
                                : 'text-stone-700'
                        }`}
                    >
                        <span className="min-w-0 flex-1 truncate">{item.label}</span>
                        {item.badge}
                        {item.hint ? (
                            <span className="shrink-0 font-mono text-[10.5px] text-stone-500">
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
