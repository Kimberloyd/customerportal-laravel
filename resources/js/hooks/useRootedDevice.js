import { Capacitor, registerPlugin } from '@capacitor/core';
import { useCallback, useEffect, useState } from 'react';

const DeviceSecurity = registerPlugin('DeviceSecurity');

const ACKNOWLEDGED_KEY = 'rooted-device-acknowledged-at';
const REMIND_AFTER_MS = 30 * 24 * 60 * 60 * 1000;

function recentlyAcknowledged() {
    try {
        const at = Number(localStorage.getItem(ACKNOWLEDGED_KEY)) || 0;
        return Date.now() - at < REMIND_AFTER_MS;
    } catch {
        return false;
    }
}

/**
 * Whether to warn that this phone looks rooted. Informational only: the check
 * is easy for a rooted phone to hide from, and the notice returns after 30
 * days so it doesn't nag on every launch.
 */
export function useRootedDevice() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        // Older installs of the Android app don't include the plugin (the web
        // deploy reaches them first), so they simply never see the notice.
        if (!Capacitor.isNativePlatform() || !Capacitor.isPluginAvailable('DeviceSecurity')) return;
        if (recentlyAcknowledged()) return;

        let cancelled = false;
        DeviceSecurity.isRooted()
            .then(({ rooted }) => {
                if (!cancelled && rooted) setOpen(true);
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, []);

    const acknowledge = useCallback(() => {
        try {
            localStorage.setItem(ACKNOWLEDGED_KEY, String(Date.now()));
        } catch {
            // Storage blocked: the notice just returns on the next launch.
        }
        setOpen(false);
    }, []);

    return { open, acknowledge };
}
