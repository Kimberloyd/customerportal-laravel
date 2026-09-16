import { Capacitor } from '@capacitor/core';
import { Style, StatusBar } from '@capacitor/status-bar';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const ThemeContext = createContext(null);

const STORAGE_KEY = 'theme';

// Matches --background-hsl in each half of app.css's :root/.dark block --
// the native status bar is OS chrome, not web content, so it can't pick
// these up from CSS itself and has to be told explicitly.
const STATUS_BAR_COLOR = { light: '#FFFFFF', dark: '#161613' };

function systemPrefersDark() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function readStoredPreference() {
    if (typeof window === 'undefined') return 'system';
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
        return 'system';
    }
}

export function ThemeProvider({ children }) {
    // 'light' | 'dark' | 'system' -- what the user picked (or didn't).
    const [preference, setPreference] = useState(readStoredPreference);
    // The actually-applied theme, resolving 'system' against the OS setting.
    const [resolved, setResolved] = useState(() => (
        preference === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : preference
    ));

    useEffect(() => {
        if (preference !== 'system') {
            setResolved(preference);
            return;
        }

        setResolved(systemPrefersDark() ? 'dark' : 'light');

        // Only matters while following the OS -- an explicit light/dark
        // pick shouldn't silently change if the OS theme changes later.
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (event) => setResolved(event.matches ? 'dark' : 'light');
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [preference]);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', resolved === 'dark');
    }, [resolved]);

    useEffect(() => {
        if (!Capacitor.isNativePlatform()) return;
        const isDark = resolved === 'dark';
        // @capacitor/status-bar's setBackgroundColor() calls the deprecated
        // Window.setStatusBarColor(), which Android ignores under the
        // edge-to-edge window setup Capacitor 8 uses -- it has no visible
        // effect. setStyle() (icon color) still works through it. The actual
        // visible background is the decorView, repainted directly through a
        // small native plugin (see ThemeStatusBarPlugin.java) instead.
        Capacitor.Plugins.ThemeStatusBar?.setBackgroundColor({ color: STATUS_BAR_COLOR[resolved] }).catch(() => {});
        StatusBar.setStyle({ style: isDark ? Style.Dark : Style.Light }).catch(() => {});
    }, [resolved]);

    const setTheme = useCallback((next) => {
        setPreference(next);
        try {
            if (next === 'system') {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, next);
            }
        } catch {
            // Private browsing / storage disabled -- the choice just won't
            // survive a reload, still works for the current session.
        }
    }, []);

    const toggleTheme = useCallback(() => {
        setTheme(resolved === 'dark' ? 'light' : 'dark');
    }, [resolved, setTheme]);

    const value = useMemo(
        () => ({ preference, resolved, setTheme, toggleTheme }),
        [preference, resolved, setTheme, toggleTheme],
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
