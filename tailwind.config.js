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
        './components/**/*.{js,jsx,ts,tsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Google Sans"', 'Arial', ...defaultTheme.fontFamily.sans],
                display: ['Montserrat', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                xs: ['0.75rem', { lineHeight: '1rem' }],
                sm: ['0.875rem', { lineHeight: '1.25rem' }],
                base: ['1rem', { lineHeight: '1.5rem' }],
                lg: ['1.125rem', { lineHeight: '1.5rem' }],
                xl: ['1.25rem', { lineHeight: '1.75rem' }],
                '2xl': ['1.5rem', { lineHeight: '2rem' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
                '5xl': ['2.75rem', { lineHeight: '1.1' }],
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
