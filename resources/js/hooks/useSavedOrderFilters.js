import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

/**
 * Named, reusable Orders-page filter combinations for the current user --
 * mirrors useSearchSelections.js's shape (fetch on mount, expose actions
 * that update local state after the request resolves).
 */
export function useSavedOrderFilters() {
    const [filters, setFilters] = useState([]);

    useEffect(() => {
        let cancelled = false;

        axios.get(route('purchase-orders.saved-filters.index'))
            .then(({ data }) => {
                if (!cancelled) setFilters(data ?? []);
            })
            .catch(() => {
                // A saved-filter list is an enhancement, not a critical path --
                // fail silently and leave the page working with zero presets.
            });

        return () => { cancelled = true; };
    }, []);

    const save = useCallback((name, values) => (
        axios.post(route('purchase-orders.saved-filters.store'), { name, filters: values })
            .then(({ data }) => {
                setFilters((current) => [data, ...current]);
                return data;
            })
    ), []);

    const remove = useCallback((id) => (
        axios.delete(route('purchase-orders.saved-filters.destroy', id))
            .then(() => setFilters((current) => current.filter((entry) => entry.id !== id)))
    ), []);

    return { filters, save, remove };
}
