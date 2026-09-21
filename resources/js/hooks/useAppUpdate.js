import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

const DISMISSED_KEY = 'app-update-dismissed';

function readDismissedCode() {
    try {
        return Number(localStorage.getItem(DISMISSED_KEY)) || 0;
    } catch {
        return 0;
    }
}

/**
 * Compares the installed Android build (versionCode) with what the server
 * says is current. `update` keeps its last value after "Later" so the sheet
 * can animate out; `open` is what says whether to show it.
 */
export function useAppUpdate() {
    const [update, setUpdate] = useState(null);
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        if (!Capacitor.isNativePlatform()) return undefined;

        let cancelled = false;

        const check = async () => {
            try {
                const [{ build }, { data }] = await Promise.all([
                    App.getInfo(),
                    axios.get(route('mobile-app.version')),
                ]);
                if (cancelled) return;

                const installed = Number.parseInt(build, 10);
                if (!data.download_url || !(data.latest_version_code > installed)) {
                    setUpdate(null);
                    return;
                }

                const required = installed < data.min_version_code;
                setUpdate({
                    latestCode: data.latest_version_code,
                    versionName: data.latest_version_name,
                    downloadUrl: data.download_url,
                    required,
                });
                setHidden(!required && readDismissedCode() >= data.latest_version_code);
            } catch {
                // Offline or the server is unreachable; the next app resume checks again.
            }
        };

        check();
        const listener = App.addListener('appStateChange', ({ isActive }) => {
            if (isActive) check();
        });

        return () => {
            cancelled = true;
            listener.then((handle) => handle.remove());
        };
    }, []);

    const dismiss = useCallback(() => {
        if (!update || update.required) return;

        try {
            localStorage.setItem(DISMISSED_KEY, String(update.latestCode));
        } catch {
            // Storage blocked: the notice just returns on the next check.
        }
        setHidden(true);
    }, [update]);

    return { update, open: update !== null && !hidden, dismiss };
}
