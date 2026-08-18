import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server: {
        // Only takes effect when Vite's own dev server binds 0.0.0.0 (e.g.
        // `npm run dev -- --host 0.0.0.0`, used to run the dev server
        // inside Docker so other containers/the host can reach it) --
        // without an explicit origin, Vite writes that literal 0.0.0.0
        // into the asset URLs it hands the browser via public/hot, which
        // browsers can't connect to (0.0.0.0 means "any interface", not
        // an actual address). HMR_HOST lets this stay localhost for a
        // plain non-Docker `npm run dev` too.
        origin: `http://${process.env.VITE_HMR_HOST ?? 'localhost'}:5173`,
        cors: true,
        // Docker Desktop on Windows doesn't forward native filesystem
        // change events from a bind-mounted host directory into the
        // Linux container, so chokidar's default watcher never fires --
        // edits silently don't trigger HMR. Polling works around that by
        // having chokidar stat files on an interval instead of waiting
        // for events.
        watch: {
            usePolling: true,
            // Poll only at a human-scale HMR cadence, and skip the large
            // backend/generated trees that never contain frontend source.
            // Scanning them every 100ms can starve Tailwind's first compile
            // on Docker Desktop and leave the browser on a blank page.
            interval: 500,
            ignored: [
                '**/vendor/**',
                '**/storage/**',
                '**/public/build/**',
            ],
        },
    },
});
