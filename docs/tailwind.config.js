/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: ['class', '[data-theme="dark"]'],
    content: [
        './docs/**/*.{md,mdx}',
        './src/**/*.{ts,tsx}',
        './src/css/**/*.{css}'
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: 'var(--brand-50)',
                    100: 'var(--brand-100)',
                    200: 'var(--brand-200)',
                    300: 'var(--brand-300)',
                    400: 'var(--brand-400)',
                    500: 'var(--brand-500)',
                    600: 'var(--brand-600)',
                    700: 'var(--brand-700)',
                    800: 'var(--brand-800)',
                    900: 'var(--brand-900)'
                }
            },
            maxWidth: {
                doc: 'var(--ds-content-max)'
            },
            borderRadius: {
                '2xl': '1rem'
            }
        }
    },
    plugins: []
};