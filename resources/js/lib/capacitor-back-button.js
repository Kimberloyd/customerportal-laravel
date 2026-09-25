import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { closeTopmostOverlay } from '@/lib/overlay-back-stack';

/**
 * Registering a 'backButton' listener replaces Capacitor's entire default
 * Android back-button handling (goBack() / minimize-app) -- once we add
 * one, we own all of it, so every branch below has to be handled
 * ourselves rather than just the modal case we actually care about.
 */
export function installCapacitorBackButton() {
    if (!Capacitor.isNativePlatform()) return;

    App.addListener('backButton', ({ canGoBack }) => {
        if (closeTopmostOverlay()) return;
        if (canGoBack) {
            window.history.back();
            return;
        }
        App.exitApp();
    });
}
