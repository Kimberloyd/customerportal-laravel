import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect } from 'react';

// Push data comes from our own server, but only a path inside the portal is
// ever followed.
function isPortalPath(url) {
    return typeof url === 'string' && url.startsWith('/') && !url.startsWith('//');
}

/**
 * Registers this phone for order notifications and opens the order when one
 * is tapped. Runs only inside the Android app, only for a signed-in user,
 * and only once the server says push is turned on.
 */
export function usePushRegistration(enabled) {
    useEffect(() => {
        // Older installs of the Android app don't include the plugin (the web
        // deploy reaches them first), so they never register.
        if (!enabled || !Capacitor.isNativePlatform() || !Capacitor.isPluginAvailable('PushNotifications')) return;

        let cancelled = false;
        const handles = [];

        const setUp = async () => {
            try {
                handles.push(
                    await PushNotifications.addListener('registration', ({ value }) => {
                        axios.post(route('push-tokens.store'), { token: value }).catch(() => {});
                    }),
                    await PushNotifications.addListener('registrationError', () => {}),
                    await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
                        const url = notification?.data?.url;
                        if (isPortalPath(url)) router.visit(url);
                    }),
                );

                let { receive } = await PushNotifications.checkPermissions();
                if (receive === 'prompt' || receive === 'prompt-with-rationale') {
                    ({ receive } = await PushNotifications.requestPermissions());
                }
                if (receive === 'granted' && !cancelled) await PushNotifications.register();
            } catch {
                // This build has no Firebase config yet: nothing to register.
            }
        };

        setUp();

        return () => {
            cancelled = true;
            handles.forEach((handle) => handle.remove());
        };
    }, [enabled]);
}
