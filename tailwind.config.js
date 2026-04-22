import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Apple-like system font stack (SF on macOS/iOS, sensible fallbacks elsewhere).
                sans: [
                    'ui-sans-serif',
                    'system-ui',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"SF Pro Display"',
                    '"SF Pro Text"',
                    '"Helvetica Neue"',
                    'Arial',
                    'Figtree',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
        },
    },

    plugins: [forms],
};
