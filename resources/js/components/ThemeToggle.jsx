import { useTheme } from '@/lib/theme-context';
import { MorphIcon } from 'morphicons/react';
import { Moon, Sun } from 'lucide';

/**
 * A plain icon-button toggle (not the three-way light/dark/system Switch
 * some settings pages might eventually want) -- one click flips between
 * light and dark from wherever the user currently is, mirroring how the
 * search/notifications icons next to it behave.
 *
 * Sun/Moon is a single MorphIcon instance whose `icon` prop flips --
 * uncontrolled mode animates the shape morph itself, rather than the DOM
 * swapping between two separate icon components.
 */
export default function ThemeToggle({ className = '' }) {
    const { resolved, toggleTheme } = useTheme();
    const isDark = resolved === 'dark';

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-full bg-transparent text-muted-foreground outline-none transition-colors hover:bg-hover focus-visible:ring-2 focus-visible:ring-ring ${className}`}
        >
            <MorphIcon
                aria-hidden="true"
                icon={isDark ? Sun : Moon}
                spring="snappy"
                reducedMotion="user"
                size={20}
            />
        </button>
    );
}
