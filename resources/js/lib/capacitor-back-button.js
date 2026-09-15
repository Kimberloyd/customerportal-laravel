import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { closeTopmostModal } from '@/components/interior/modal';

/**
 * Registering a 'backButton' listener replaces Capacitor's entire default
 * Android back-button handling (goBack() / minimize-app) -- once we add
 * one, we own all of it, so every branch below has to be handled
 * ourselves rather than just the modal case we actually care about.
 */
export function installCapacitorBackButton() {
    if (!Capacitor.isNativePlatform()) return;

    App.addListener('backButton', ({ canGoBack }) => {
        if (closeTopmostModal()) return;
        if (canGoBack) {
            window.history.back();
            return;
        }
        App.exitApp();
    });
}
