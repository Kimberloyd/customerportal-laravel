import { useTheme } from '@/lib/theme-context';
import { Moon, Sun } from 'lucide-react';

/**
 * A plain icon-button toggle (not the three-way light/dark/system Switch
 * some settings pages might eventually want) -- one click flips between
 * light and dark from wherever the user currently is, mirroring how the
 * search/notifications icons next to it behave.
 */
export default function ThemeToggle({ className = '' }) {
    const { resolved, toggleTheme } = useTheme();
    const isDark = resolved === 'dark';

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-gray-500 outline-none transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-ring dark:text-gray-400 dark:hover:bg-white/10 ${className}`}
        >
            {isDark ? (
                <Sun aria-hidden="true" className="h-[18px] w-[18px]" />
            ) : (
                <Moon aria-hidden="true" className="h-[18px] w-[18px]" />
            )}
        </button>
    );
}
