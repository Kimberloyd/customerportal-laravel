const stack = [];

/**
 * Registers an open overlay in visual order. The returned cleanup removes the
 * exact entry even when overlays close out of order during route transitions.
 */
export function registerOverlayBackHandler(handler) {
    const entry = { handler };
    stack.push(entry);

    return () => {
        const index = stack.indexOf(entry);
        if (index !== -1) stack.splice(index, 1);
    };
}

export function isTopmostOverlayBackHandler(handler) {
    return stack.at(-1)?.handler === handler;
}

/** Returns true when an overlay consumed the back action. */
export function closeTopmostOverlay() {
    const entry = stack.at(-1);
    if (!entry) return false;

    entry.handler();
    return true;
}
