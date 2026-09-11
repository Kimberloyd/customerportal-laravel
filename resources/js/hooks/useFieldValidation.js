import { useCallback, useRef, useState } from 'react';

/**
 * On-blur / escalate-to-live / confirm-on-success validation timing:
 * a field stays quiet while the user is typing, validates once when they
 * blur away, and only re-validates on every keystroke after it has shown
 * an error -- before that, no premature "not done typing yet" errors.
 *
 * `rules` maps field name -> (value, data) => string | null (an error
 * message, or null when valid). A field with no rule is left alone; its
 * error/success state is decided entirely by the caller (e.g. server-only
 * checks like uniqueness).
 */
export function useFieldValidation(rules) {
    const [clientErrors, setClientErrors] = useState({});
    const [validFields, setValidFields] = useState({});
    const escalatedRef = useRef(new Set());

    const check = useCallback((field, value, data) => {
        const rule = rules[field];
        if (!rule) return;
        const message = rule(value, data);

        setClientErrors((previous) => {
            if (!message && !(field in previous)) return previous;
            const next = { ...previous };
            if (message) next[field] = message; else delete next[field];
            return next;
        });
        setValidFields((previous) => {
            const isValid = !message;
            if (previous[field] === isValid) return previous;
            return { ...previous, [field]: isValid };
        });
        if (message) escalatedRef.current.add(field);
    }, [rules]);

    const onBlur = useCallback((field, value, data) => check(field, value, data), [check]);

    // Only re-checks once a field has already shown an error once -- keeps
    // the calmer on-blur timing for fields the user hasn't gotten wrong yet.
    const onChange = useCallback((field, value, data) => {
        if (escalatedRef.current.has(field)) check(field, value, data);
    }, [check]);

    const clear = useCallback((field) => {
        escalatedRef.current.delete(field);
        setClientErrors((previous) => {
            if (!(field in previous)) return previous;
            const next = { ...previous };
            delete next[field];
            return next;
        });
        setValidFields((previous) => {
            if (!(field in previous)) return previous;
            const next = { ...previous };
            delete next[field];
            return next;
        });
    }, []);

    // For a form/modal that resets its data but stays mounted (closes via
    // CSS/inert rather than unmounting) -- clears every field's validation
    // state so reopening it doesn't show stale errors or success ticks.
    const reset = useCallback(() => {
        escalatedRef.current.clear();
        setClientErrors({});
        setValidFields({});
    }, []);

    return { clientErrors, validFields, onBlur, onChange, clear, reset };
}
