import '../css/app.css';
import './bootstrap';

import ChatWidget from '@/components/messaging/ChatWidget';
import { ChatWidgetProvider } from '@/lib/chat-widget-context';
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
            <ChatWidgetProvider>
                <App {...props} />
                <ChatWidget />
            </ChatWidgetProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
