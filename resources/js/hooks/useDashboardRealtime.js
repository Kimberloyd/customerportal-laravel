import echo from '@/echo';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function useDashboardRealtime({ onStart, onFinish } = {}) {
    const userId = usePage().props.auth?.user?.id;

    useEffect(() => {
        if (!echo || !userId) return undefined;

        const channelName = `users.${userId}`;
        const eventName = '.purchase-order.changed';
        const channel = echo.private(channelName);
        let refreshTimer;

        const refresh = () => {
            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(() => {
                router.reload({
                    only: ['dashboard'],
                    preserveScroll: true,
                    preserveState: true,
                    onStart,
                    onFinish,
                });
            }, 150);
        };

        channel.listen(eventName, refresh);

        return () => {
            window.clearTimeout(refreshTimer);
            channel.stopListening(eventName, refresh);
            echo.leave(channelName);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId]);
}
