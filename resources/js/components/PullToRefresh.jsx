import { Capacitor } from '@capacitor/core';
import { Haptics, ImpactStyle } from '@capacitor/haptics';
import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';

const PULL_THRESHOLD = 64;
const VISUAL_CAP = 96;
// The armed-and-refreshing hold pins the pull at this height rather than at
// resistedPull(PULL_THRESHOLD) (~38px) -- that's shorter than the ring icon
// itself (12px margin + 32px circle = 44px), which crowds it against the
// nav above for the whole refresh, not just a passing frame.
const REFRESH_HOLD_HEIGHT = 56;
const RELEASE_SPRING = { type: 'spring', stiffness: 300, damping: 20, mass: 0.5 };

// Diminishing-returns curve: a finger that has dragged well past VISUAL_CAP
// still only moves the content asymptotically toward it, so the drag feels
// elastic (a rubber band getting harder to stretch) instead of a 1:1 pass-
// through -- present for the whole drag, not just once armed.
function resistedPull(rawDelta) {
    return VISUAL_CAP * (1 - 1 / (rawDelta / VISUAL_CAP + 1));
}

/**
 * The Android WebView (unlike Chrome/Safari) has no built-in pull-to-refresh
 * chrome, so this exists purely for the Capacitor app -- on the regular
 * website the mobile browser already provides its own, and layering a
 * second one on top would just double up / fight it.
 */
export default function PullToRefresh({ children }) {
    const native = typeof window !== 'undefined' && Capacitor.isNativePlatform();
    const containerRef = useRef(null);
    const [pull, setPull] = useState(0);
    const [armed, setArmed] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [dragging, setDragging] = useState(false);
    const reducedMotion = useReducedMotion();
    // Raw (unresisted) drag distance drives the commit decision and the
    // haptic/armed moment; `pull` (the resisted curve above) is purely what
    // gets rendered -- they're deliberately not the same number.
    const state = useRef({
        dragging: false,
        startY: 0,
        raw: 0,
        refreshing: false,
        armed: false,
        moved: false,
        watchdog: null,
    });

    useEffect(() => {
        if (!native) return;
        const el = containerRef.current;
        if (!el) return;

        const clearWatchdog = () => {
            if (state.current.watchdog !== null) {
                clearTimeout(state.current.watchdog);
                state.current.watchdog = null;
            }
        };

        // A touchstart+touchmove with no touchend ever following leaves the
        // gesture hanging forever -- pull stuck at whatever the last move
        // set it to, with nothing else to snap it back. Seen in practice
        // right after a login form's autofill auto-submits (whatever
        // synthesizes that tap doesn't always deliver a clean touchend
        // before the page navigates). A drag has no legitimate reason to
        // still be "in progress" several seconds later, so force a reset.
        const armWatchdog = () => {
            clearWatchdog();
            state.current.watchdog = setTimeout(() => {
                state.current.watchdog = null;
                if (!state.current.dragging) return;
                state.current.dragging = false;
                state.current.moved = false;
                setDragging(false);
                setPull(0);
                setArmed(false);
            }, 2500);
        };

        // A tap that turns out to be a link click (or anything else that
        // starts an Inertia visit) can have its own touchend queued behind
        // the navigation -- once the visit swaps this component out and
        // back in, React has already torn down these listeners by the time
        // that queued touchend would have arrived, so it never fires at
        // all. The 2.5s watchdog above eventually catches this too, but
        // this closes the same gap the instant it's known, instead of
        // leaving the ring visibly stuck for however long is left on the
        // timer. Only clears a stray drag -- our own reload firing this
        // same 'start' event is handled by its own onFinish below.
        const unsubscribeNavigate = router.on('start', () => {
            if (state.current.refreshing || !state.current.dragging) return;
            clearWatchdog();
            state.current.dragging = false;
            state.current.moved = false;
            setDragging(false);
            setPull(0);
            setArmed(false);
        });

        const onTouchStart = (event) => {
            if (state.current.refreshing || window.scrollY > 0 || event.touches.length !== 1) return;

            // An inner scrollable area (e.g. a virtualized Table) that isn't
            // at its own top should keep this drag for itself -- otherwise
            // pulling it back toward its own top gets hijacked into a
            // page-level refresh instead.
            let node = event.target instanceof Element ? event.target : null;
            while (node && node !== el) {
                if (node.scrollTop > 0) return;
                node = node.parentElement;
            }

            state.current.dragging = true;
            state.current.startY = event.touches[0].clientY;
            state.current.armed = false;
            state.current.moved = false;
            setDragging(true);
            armWatchdog();
        };

        const onTouchMove = (event) => {
            if (!state.current.dragging) return;
            armWatchdog();
            const raw = Math.max(0, event.touches[0].clientY - state.current.startY);
            if (raw <= 0) {
                state.current.raw = 0;
                setPull(0);
                return;
            }
            event.preventDefault();
            state.current.raw = raw;
            state.current.moved = true;
            setPull(resistedPull(raw));

            const pastThreshold = raw >= PULL_THRESHOLD;
            if (pastThreshold && !state.current.armed) {
                state.current.armed = true;
                setArmed(true);
                Haptics.impact({ style: ImpactStyle.Light }).catch(() => {});
            } else if (!pastThreshold && state.current.armed) {
                // Pulled back below the line without releasing -- re-arm so
                // crossing it again in the same gesture ticks again too.
                state.current.armed = false;
                setArmed(false);
            }
        };

        const onTouchEnd = () => {
            clearWatchdog();
            if (!state.current.dragging) return;
            state.current.dragging = false;
            setDragging(false);

            // A touchend with no touchmove in between never happened as a
            // real drag -- committing off it anyway is exactly how a stray/
            // duplicate touchend (observed once, in a dev-server hot-reload
            // reconnect window) fired a refresh no one actually asked for.
            if (!state.current.moved) {
                setPull(0);
                return;
            }

            if (state.current.raw >= PULL_THRESHOLD) {
                state.current.refreshing = true;
                setRefreshing(true);
                setPull(REFRESH_HOLD_HEIGHT);
                router.reload({
                    onFinish: () => {
                        state.current.refreshing = false;
                        state.current.armed = false;
                        setRefreshing(false);
                        setArmed(false);
                        setPull(0);
                    },
                });
            } else {
                setPull(0);
            }
        };

        el.addEventListener('touchstart', onTouchStart, { passive: true });
        el.addEventListener('touchmove', onTouchMove, { passive: false });
        el.addEventListener('touchend', onTouchEnd);
        el.addEventListener('touchcancel', onTouchEnd);
        return () => {
            clearWatchdog();
            unsubscribeNavigate();
            el.removeEventListener('touchstart', onTouchStart);
            el.removeEventListener('touchmove', onTouchMove);
            el.removeEventListener('touchend', onTouchEnd);
            el.removeEventListener('touchcancel', onTouchEnd);
        };
    }, [native]);

    if (!native) return children;

    // Immediate while a finger is down (must track the drag 1:1 frame to
    // frame), a real spring once released -- a fixed-duration ease here is
    // exactly the "hard stop" this is trying to avoid.
    const releaseTransition = dragging || reducedMotion ? { duration: 0 } : RELEASE_SPRING;
    const progress = Math.min(pull / VISUAL_CAP, 1);

    return (
        <div ref={containerRef} className="relative">
            <motion.div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 flex justify-center overflow-hidden"
                animate={{ height: pull }}
                transition={releaseTransition}
            >
                {/* The clip is what hides this entirely at rest (height 0) --
                    dropping it earlier to stop the ring's bottom edge from
                    getting cut off during the refreshing hold was the wrong
                    fix; REFRESH_HOLD_HEIGHT (56px) already clears the icon's
                    own footprint (16px margin + 32px circle = 48px), so the
                    clip was never what caused that -- pinning the hold
                    height too short was. */}
                <motion.div
                    animate={{ rotate: refreshing || reducedMotion ? 0 : progress * 360 }}
                    transition={dragging ? { duration: 0 } : RELEASE_SPRING}
                    className={`mt-4 grid h-8 w-8 shrink-0 place-items-center rounded-full border bg-white shadow-md dark:bg-[#1D1D1A] ${
                        armed || refreshing ? 'border-primary text-primary' : 'border-border text-muted-foreground'
                    }`}
                >
                    <RefreshCw aria-hidden="true" className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                </motion.div>
            </motion.div>
            <motion.div animate={{ y: pull }} transition={releaseTransition}>
                {children}
            </motion.div>
        </div>
    );
}
