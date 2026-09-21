import { Capacitor } from '@capacitor/core';
import { Network } from '@capacitor/network';
import { useEffect, useState } from 'react';

export function useOnlineStatus() {
    const [online, setOnline] = useState(true);

    useEffect(() => {
        let cancelled = false;

        // Older installs of the Android app don't include the Network plugin
        // (the web deploy reaches them first), so they use the browser events.
        if (Capacitor.isNativePlatform() && !Capacitor.isPluginAvailable('Network')) {
            const update = () => setOnline(navigator.onLine);
            update();
            window.addEventListener('online', update);
            window.addEventListener('offline', update);

            return () => {
                window.removeEventListener('online', update);
                window.removeEventListener('offline', update);
            };
        }

        let listener = null;
        Network.getStatus()
            .then((status) => {
                if (!cancelled) setOnline(status.connected);
            })
            .catch(() => {});
        Network.addListener('networkStatusChange', (status) => setOnline(status.connected)).then((handle) => {
            if (cancelled) handle.remove();
            else listener = handle;
        });

        return () => {
            cancelled = true;
            listener?.remove();
        };
    }, []);

    return online;
}
