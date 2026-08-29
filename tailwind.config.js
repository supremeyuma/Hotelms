import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/views/**/*.js',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#4f46e5',
                    subtle: '#6366f1',
                },
                ink: {
                    DEFAULT: '#0f172a',
                    muted: '#475569',
                    dim: '#94a3b8',
                },
                surface: {
                    DEFAULT: '#ffffff',
                    alt: '#f8fafc',
                    raised: '#ffffff',
                },
                border: {
                    light: '#e2e8f0',
                    strong: '#cbd5e1',
                },
                success: {
                    DEFAULT: '#10b981',
                    deep: '#059669',
                },
                danger: {
                    DEFAULT: '#dc2626',
                },
                whatsapp: '#059669',
            },
            boxShadow: {
                'surface-raised': '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
                dropdown: '0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)',
                modal: '0 20px 60px rgba(0,0,0,0.15)',
                'whatsapp-glow': '0 20px 50px rgba(5,150,105,0.3)',
                'hero-glow': 'inset 0 0 120px rgba(0,0,0,0.4)',
                'button-hover': '0 8px 25px rgba(0,0,0,0.12)',
            },
        },
    },

    plugins: [
        forms,
        function ({ addUtilities }) {
            addUtilities({
                '.scrollbar-none': {
                    'scrollbar-width': 'none',
                    '-ms-overflow-style': 'none',
                    '&::-webkit-scrollbar': { display: 'none' },
                },
            });
        },
    ],
};
