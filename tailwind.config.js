import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const withOpacity = (variable) => `hsl(var(${variable}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './resources/js/**/*.tsx',
        './resources/js/**/*.ts',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Helvetica Now"', 'Helvetica', 'Arial', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                background: withOpacity('--background-hsl'),
                foreground: withOpacity('--foreground-hsl'),
                card: {
                    DEFAULT: withOpacity('--card-hsl'),
                    foreground: withOpacity('--card-foreground-hsl'),
                },
                border: withOpacity('--border-hsl'),
                ring: withOpacity('--ring-hsl'),
                muted: {
                    DEFAULT: withOpacity('--muted-hsl'),
                    foreground: withOpacity('--muted-foreground-hsl'),
                },
                primary: {
                    DEFAULT: withOpacity('--primary-hsl'),
                    foreground: withOpacity('--primary-foreground-hsl'),
                },
                accent: withOpacity('--accent-hsl'),
                hover: withOpacity('--hover-hsl'),
                active: withOpacity('--active-hsl'),
                destructive: withOpacity('--destructive-hsl'),
                success: withOpacity('--success-hsl'),
                info: withOpacity('--info-hsl'),
            },
        },
    },

    plugins: [forms],
};
