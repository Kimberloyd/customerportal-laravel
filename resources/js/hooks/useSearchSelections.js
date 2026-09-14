import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

/**
 * Backs the "recent searches" and "rank by clicks" rules from the
 * search-bar-ux skill: fetches this user's recent picks plus the
 * most-picked entities across all users for a given search context, and
 * exposes `record()` to log a new pick after the fact.
 *
 * `context` must be one of SearchSelection::CONTEXTS on the backend
 * ('customer', 'product', 'message_account', 'order_search').
 */
export function useSearchSelections(context) {
    const [recent, setRecent] = useState([]);
    const [popularity, setPopularity] = useState({});

    useEffect(() => {
        let cancelled = false;

        axios.get(route('search.recent'), { params: { context } })
            .then(({ data }) => {
                if (cancelled) return;
                setRecent(data.recent ?? []);
                const map = {};
                (data.popular ?? []).forEach((row) => { map[row.entity_key] = row.count; });
                setPopularity(map);
            })
            .catch(() => {
                // Recents/ranking are an enhancement, not a critical path --
                // fail silently and leave the search field working as a
                // plain (unranked, no-recents) field.
            });

        return () => { cancelled = true; };
    }, [context]);

    const record = useCallback((entityKey, label) => {
        axios.post(route('search.record'), { context, entity_key: entityKey, label }).catch(() => {});
    }, [context]);

    return { recent, popularity, record };
}
