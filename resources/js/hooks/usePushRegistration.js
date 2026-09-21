import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect } from 'react';

// Signing in as someone else registers straight away; the same person coming
// back to a page within this window doesn't register the phone again.
const REREGISTER_AFTER_MS = 60_000;

let listening = false;
let lastRegistration = { userId: null, at: 0 };

// Push data comes from our own server, but only a path inside the portal is
// ever followed.
function isPortalPath(url) {
    return typeof url === 'string' && url.startsWith('/') && !url.startsWith('//');
}

// Attached once for the life of the page. Pages render their own layout, so a
// hook that attached these per mount would drop and re-add them on every
// navigation. A tap that launches the app from fully closed is held by the
// plugin until the first listener exists, which is why this has to be ready
// as soon as a signed-in page renders, and why it must not be torn down.
async function listenOnce() {
    if (listening) return;
    listening = true;

    try {
        await PushNotifications.addListener('registration', ({ value }) => {
            axios.post(route('push-tokens.store'), { token: value }).catch(() => {});
        });
        await PushNotifications.addListener('registrationError', () => {});
        await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
            const url = notification?.data?.url;
            if (isPortalPath(url)) router.visit(url);
        });
    } catch (error) {
        listening = false;
        throw error;
    }
}

/**
 * Registers this phone for order notifications and opens the order when one
 * is tapped. Runs only inside the Android app, only for a signed-in user,
 * and only once the server says push is turned on.
 */
export function usePushRegistration(enabled, userId) {
    useEffect(() => {
        // Older installs of the Android app don't include the plugin (the web
        // deploy reaches them first), so they never register.
        if (!enabled || !userId || !Capacitor.isNativePlatform() || !Capacitor.isPluginAvailable('PushNotifications')) return;

        const setUp = async () => {
            try {
                await listenOnce();

                if (lastRegistration.userId === userId && Date.now() - lastRegistration.at < REREGISTER_AFTER_MS) return;
                lastRegistration = { userId, at: Date.now() };

                let { receive } = await PushNotifications.checkPermissions();
                if (receive === 'prompt' || receive === 'prompt-with-rationale') {
                    ({ receive } = await PushNotifications.requestPermissions());
                }
                if (receive === 'granted') await PushNotifications.register();
            } catch {
                // This build has no Firebase config yet: nothing to register.
            }
        };

        setUp();
    }, [enabled, userId]);
}
