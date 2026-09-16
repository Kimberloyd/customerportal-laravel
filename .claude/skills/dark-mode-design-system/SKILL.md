---
name: dark-mode-design-system
description: Implements and audits a full light/dark theming system for a React + Tailwind app -- HSL custom-property tokens with a real .dark override block, a React theme-context provider (light/dark/system, persisted, flash-free), a manual toggle, a systematic gap audit across every component, and native-shell status-bar theming for Capacitor apps.
---

# Dark Mode Design System

Builds a complete, production-grade light/dark theme system the way it was actually built and shipped in a real Laravel + Inertia + React app this session: token architecture, a React provider, a manual toggle, a repo-wide audit workflow that actually finds every gap, and (for a Capacitor-wrapped app) making the native status bar follow the theme too.

## When to use

- The user asks to "add dark mode," "complete dark mode," "audit dark mode coverage," or reports a dark-mode UI that's only partially themed (e.g. one white nav bar or white form field sitting in an otherwise-dark app).
- A Tailwind project has no `darkMode` config at all (defaults to `'media'`, OS-only, no manual toggle).
- A Capacitor-wrapped web app's native Android/iOS status bar stays a fixed color while the web content itself has working dark mode.

## Architecture

### 1. Token layer (CSS custom properties, not literal Tailwind dark: everywhere)

In your global CSS (e.g. `resources/css/app.css`), define semantic tokens as HSL channel triples so Tailwind's opacity modifiers work (`bg-primary/10`), plus a full value for code that needs to read the color directly:

```css
@layer base {
    :root {
        --background-hsl: 238 10% 100%;
        --background: hsl(var(--background-hsl));
        --foreground-hsl: 238 10% 3.9%;
        --foreground: hsl(var(--foreground-hsl));
        /* ...card, border, muted, muted-foreground, primary, ring,
           destructive, success, info, and any brand-specific tokens... */
    }

    /* Activated by the `dark` class the theme toggle puts on <html>
       (requires darkMode: 'class' in tailwind.config.js -- see below).
       Pick a genuinely different hue for the dark neutral surface
       (e.g. warm stone ~60) rather than just inverting the light
       values -- a pure invert of a cool-blue light theme often reads
       as muddy or too blue at night. Brand/accent colors (primary,
       ring) usually stay the same hue, just lightened for contrast
       against a dark background. */
    .dark {
        --background-hsl: 60 6% 8%;
        --foreground-hsl: 60 9% 96%;
        /* ...same token set, dark-appropriate values... */
    }
}
```

Wire these into `tailwind.config.js` so every token is a real Tailwind color (supports `bg-*`, `text-*`, `border-*`, and opacity modifiers):

```js
darkMode: 'class', // REQUIRED -- without this, Tailwind defaults to
                    // 'media' (OS-only) and a manual toggle is impossible.
theme: {
    extend: {
        colors: {
            background: withOpacity('--background-hsl'),
            foreground: withOpacity('--foreground-hsl'),
            destructive: withOpacity('--destructive-hsl'),
            success: withOpacity('--success-hsl'),
            // ...
        },
    },
},
```

```js
function withOpacity(variable) {
    return ({ opacityValue }) =>
        opacityValue === undefined
            ? `hsl(var(${variable}))`
            : `hsl(var(${variable}) / ${opacityValue})`;
}
```

**Why tokens instead of just `dark:` everywhere:** a component that writes `className="text-destructive"` is correct in both themes automatically, forever, because the token itself flips under `.dark` -- no `dark:` variant needed on that class at all. Reserve literal `dark:bg-*`/`dark:text-*` Tailwind classes for the genuine one-offs that don't have (and don't deserve) a semantic token -- e.g. a specific amber warning-banner color when the project has no `--warning` token yet.

### 2. React theme provider (light/dark/system, persisted, no flash)

```jsx
// lib/theme-context.jsx
const STORAGE_KEY = 'theme';

function systemPrefersDark() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function ThemeProvider({ children }) {
    const [preference, setPreference] = useState(readStoredPreference); // 'light'|'dark'|'system'
    const [resolved, setResolved] = useState(() =>
        preference === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : preference
    );

    useEffect(() => {
        if (preference !== 'system') { setResolved(preference); return; }
        setResolved(systemPrefersDark() ? 'dark' : 'light');
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (e) => setResolved(e.matches ? 'dark' : 'light');
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [preference]);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', resolved === 'dark');
    }, [resolved]);

    const setTheme = useCallback((next) => {
        setPreference(next);
        try {
            if (next === 'system') localStorage.removeItem(STORAGE_KEY);
            else localStorage.setItem(STORAGE_KEY, next);
        } catch { /* private browsing -- choice just won't persist */ }
    }, []);

    const toggleTheme = useCallback(() => setTheme(resolved === 'dark' ? 'light' : 'dark'), [resolved, setTheme]);

    const value = useMemo(() => ({ preference, resolved, setTheme, toggleTheme }), [preference, resolved, setTheme, toggleTheme]);
    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}
```

Mount `<ThemeProvider>` as the outermost provider in your app entry.

**Flash-of-wrong-theme prevention:** the provider's own effect runs after React hydrates, which is too late -- the page paints in the wrong theme for one frame first. Fix with a blocking inline `<script>` in the HTML `<head>`, before any stylesheet/app bundle loads:

```html
<script>
  (function() {
    var stored = localStorage.getItem('theme');
    var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
  })();
</script>
```

### 3. The toggle

A plain icon button calling `toggleTheme()`, mirroring whatever icon-button convention the rest of the header already uses. If the codebase has an icon-morphing library available, use it for the sun/moon swap (single component, animated shape morph) instead of conditionally rendering two different icon components.

## The audit workflow (this is the part that actually matters)

Building the token system and provider is the easy 20%. The other 80% is finding every place the rest of the codebase never got the memo -- and there will be many, because most components were written before dark mode existed and nobody remembers which ones.

1. **Grep for gaps systematically**, don't rely on visually clicking through the app (you will miss things). A gap-finder that catches hardcoded light-only Tailwind utilities with no `dark:` variant on the same line:

```bash
grep -rn 'bg-white\|bg-gray-\|text-gray-\|border-gray-\|bg-stone-\|text-stone-\|border-stone-\|bg-slate-\|text-slate-\|border-slate-\|shadow-\[\|divide-gray-\|divide-stone-\|ring-gray-' \
  --include='*.jsx' --include='*.tsx' resources/js \
  | grep -v 'dark:'
```

Rank the hit files by count, then work through them from most-hits to least. For each file:
- If a line already has semantic meaning (error text, success banner, muted caption), route it through the matching token instead of adding a `dark:` variant to the hardcoded value -- fixes it permanently and prevents the next contrast/brand tweak from missing that spot.
- If it's a genuine one-off with no token, add the explicit `dark:` class.

2. **Bulk-fix with scripted find/replace, not one Edit call per line** -- when the same `(old-classlist, new-classlist)` pair repeats across many files (it will: the same "white card with gray border" pattern gets pasted everywhere), a small script applying a list of exact-substring replacements across the flagged files is far faster than editing each occurrence by hand. Re-run the grep after each batch to confirm the count actually drops to zero for that pattern, and do a real production build after every batch (a `dark:` typo or unbalanced template string is a silent no-op, not a build error, unless something downstream trips on it).

3. **Known gap categories, roughly in the order they tend to surface:**
   - The main nav/header bar (often styled once, early, before dark mode existed, and never revisited).
   - Modal/dialog surfaces, dropdown menus, popovers -- anything portaled to `document.body` tends to get missed by a component-tree visual scan.
   - Form inputs specifically: a plain `<input>` with no explicit background color renders with the *browser's* default background (usually white) regardless of your dark theme, not just an unstyled Tailwind class -- these are easy to miss because they don't look like a dark-mode bug in the source, only in the rendered page.
   - Status/semantic banners (success, error, warning) -- these are often colored with raw Tailwind palette classes (`bg-green-50 text-green-800`) since they were built before the token system existed, and easily keep zero `dark:` coverage even after everything else is themed, because they only render conditionally and get missed in a quick scan.
   - Chart/data-viz components with a hardcoded hex color for a series (`color: '#10b981'`) instead of reading the token -- works fine visually in light mode, silently wrong (or just inconsistent with a sibling chart on the same page) in dark mode.
   - Anything using a raw hex value duplicated in more than one file (grep for the literal hex across the repo) -- a sign it should have been a token from the start.

4. **Confirmed-NOT-gaps** (don't "fix" these): a white knob on a colored toggle switch track (intentional, correct in both themes, matches iOS convention), a semi-transparent dark scrim behind a mobile nav drawer (correct in both themes by design), a component that already reads tokens throughout via its own established convention different from `dark:` classes (e.g. `SURFACE`/`GLYPH` constants).

## Native shell status bar (Capacitor apps only)

If the web app is wrapped in Capacitor for Android/iOS, working web dark mode does **not** automatically theme the native status bar (the OS chrome showing clock/battery/signal) -- that's a separate native surface the browser doesn't touch.

**The trap:** newer Capacitor versions (8+) run the WebView in an edge-to-edge window. In that mode, `@capacitor/status-bar`'s `setBackgroundColor()` calls the deprecated `Window.setStatusBarColor()` Android API, which silently has **no visible effect** -- no error, the call succeeds, nothing happens. What's actually visible behind the status bar in edge-to-edge mode is the Activity's **decorView background**, which Capacitor's own built-in `SystemBars` plugin (bundled in `@capacitor/android` core, separate from the `@capacitor/status-bar` npm package) repaints on every app launch and config change, straight from the static `android:windowBackground` theme attribute -- completely bypassing whatever the JS side just requested.

Net effect if you don't know this: you call the documented API, it reports success, and the status bar background never changes -- while the *icon color* (`setStyle()`) works fine, since that part isn't affected by edge-to-edge. Icons in the requested color end up rendered on a background that never changed, which can make them invisible if the requested icon color happens to match the stuck background.

**The fix:** a tiny custom native plugin that repaints the decorView directly, called from the same place the JS theme provider already reacts to theme changes:

```java
// android/app/src/main/java/.../ThemeStatusBarPlugin.java
@CapacitorPlugin(name = "ThemeStatusBar")
public class ThemeStatusBarPlugin extends Plugin {
    @PluginMethod
    public void setBackgroundColor(PluginCall call) {
        String colorHex = call.getString("color");
        getActivity().runOnUiThread(() ->
            getActivity().getWindow().getDecorView().setBackgroundColor(Color.parseColor(colorHex))
        );
        call.resolve();
    }
}
```

Register it in `MainActivity.onCreate` (`registerPlugin(ThemeStatusBarPlugin.class);` before `super.onCreate()`), and remove any hardcoded native `setStatusBarColor`/decorView color calls left over from before dark mode existed -- they'll silently win over both the plugin and the JS call if left in place.

In the theme provider's effect that reacts to `resolved` theme changes:

```js
useEffect(() => {
    if (!Capacitor.isNativePlatform()) return;
    const isDark = resolved === 'dark';
    Capacitor.Plugins.ThemeStatusBar?.setBackgroundColor({ color: isDark ? '#161613' : '#FFFFFF' })
        .catch(() => {});
    // Icon color still goes through the real plugin -- this part works natively.
    StatusBar.setStyle({ style: isDark ? Style.Dark : Style.Light }).catch(() => {});
}, [resolved]);
```

Keep the status-bar background hex in sync with the CSS token's actual computed value (e.g. `.dark`'s `--background-hsl` converted to hex) so the status bar visually continues the page rather than being a slightly-off neighbor color.

**Debugging checklist if the native status bar still doesn't update after this:**
1. `adb logcat | grep -i statusbar` while toggling theme -- confirm the plugin call actually fires (`Registering plugin instance: ThemeStatusBar`, then the `setBackgroundColor` call logged).
2. Take a real device screenshot (`adb exec-out screencap -p > file.png`, not `adb shell screencap -p` piped through a shell that mangles binary output) and inspect the actual pixels -- don't trust "it looks probably right," a background that's technically painted the wrong color with icons in a color that happens to still be visible against it can look "close enough" in a quick glance.
3. If icons are invisible but the call reports success: check whether the *requested icon color* matches whatever the background actually resolved to (the classic silent-failure signature described above) rather than assuming the icon API itself is broken.
