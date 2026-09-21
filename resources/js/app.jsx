import '../css/app.css';
import './bootstrap';

import AppUpdateNotice from '@/components/AppUpdateNotice';
import ChatWidget from '@/components/messaging/ChatWidget';
import OfflineBanner from '@/components/OfflineBanner';
import RootedDeviceNotice from '@/components/RootedDeviceNotice';
import { ChatWidgetProvider } from '@/lib/chat-widget-context';
import { ThemeProvider } from '@/lib/theme-context';
import { installCapacitorBackButton } from '@/lib/capacitor-back-button';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

installCapacitorBackButton();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider>
                <ChatWidgetProvider>
                    <App {...props} />
                    <ChatWidget />
                    <AppUpdateNotice />
                    <OfflineBanner />
                    <RootedDeviceNotice />
                </ChatWidgetProvider>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
