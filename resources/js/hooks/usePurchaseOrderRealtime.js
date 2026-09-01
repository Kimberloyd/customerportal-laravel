import echo from '@/echo';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function usePurchaseOrderRealtime(orderId = null, { only, onStart, onFinish } = {}) {
    const userId = usePage().props.auth?.user?.id;
    const onlyKey = only ? only.join(',') : '';

    useEffect(() => {
        if (!echo || !userId) return undefined;

        const channelName = `users.${userId}`;
        const eventName = '.purchase-order.changed';
        const channel = echo.private(channelName);
        let refreshTimer;

        const refresh = (event) => {
            if (orderId !== null && Number(event.order_id) !== Number(orderId)) return;

            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(() => {
                router.reload({
                    only:
                        only
                            ?? (orderId === null
                                ? ['orders']
                                : ['order', 'canManageFulfillment', 'canComplete', 'canCancel']),
                    preserveScroll: true,
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
    }, [orderId, userId, onlyKey]);
}
