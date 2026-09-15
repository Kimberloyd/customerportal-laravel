import { Capacitor } from '@capacitor/core';
import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';

const PULL_THRESHOLD = 64;
const MAX_PULL = 96;

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
    const [refreshing, setRefreshing] = useState(false);
    const reducedMotion = useReducedMotion();
    const state = useRef({ dragging: false, startY: 0, pull: 0, refreshing: false });

    useEffect(() => {
        if (!native) return;
        const el = containerRef.current;
        if (!el) return;

        const setPullBoth = (value) => {
            state.current.pull = value;
            setPull(value);
        };

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
        };

        const onTouchMove = (event) => {
            if (!state.current.dragging) return;
            const delta = event.touches[0].clientY - state.current.startY;
            if (delta <= 0) {
                setPullBoth(0);
                return;
            }
            event.preventDefault();
            setPullBoth(delta < MAX_PULL ? delta : MAX_PULL + (delta - MAX_PULL) * 0.15);
        };

        const onTouchEnd = () => {
            if (!state.current.dragging) return;
            state.current.dragging = false;

            if (state.current.pull >= PULL_THRESHOLD) {
                state.current.refreshing = true;
                setRefreshing(true);
                setPullBoth(PULL_THRESHOLD);
                router.reload({
                    onFinish: () => {
                        state.current.refreshing = false;
                        setRefreshing(false);
                        setPullBoth(0);
                    },
                });
            } else {
                setPullBoth(0);
            }
        };

        el.addEventListener('touchstart', onTouchStart, { passive: true });
        el.addEventListener('touchmove', onTouchMove, { passive: false });
        el.addEventListener('touchend', onTouchEnd);
        el.addEventListener('touchcancel', onTouchEnd);
        return () => {
            el.removeEventListener('touchstart', onTouchStart);
            el.removeEventListener('touchmove', onTouchMove);
            el.removeEventListener('touchend', onTouchEnd);
            el.removeEventListener('touchcancel', onTouchEnd);
        };
    }, [native]);

    if (!native) return children;

    const settling = !state.current.dragging;

    return (
        <div ref={containerRef} className="relative">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 flex justify-center overflow-hidden"
                style={{
                    height: pull,
                    transition: settling ? 'height 0.2s ease' : 'none',
                }}
            >
                <motion.div
                    animate={{ rotate: refreshing || reducedMotion ? 0 : pull * 3 }}
                    transition={{ duration: 0 }}
                    className="mt-3 grid h-8 w-8 place-items-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-md"
                >
                    <RefreshCw aria-hidden="true" className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                </motion.div>
            </div>
            <div
                style={{
                    transform: `translateY(${pull}px)`,
                    transition: settling ? 'transform 0.2s ease' : 'none',
                }}
            >
                {children}
            </div>
        </div>
    );
}
