/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            // MediConnect India design tokens.
            // Palette rationale: a calm clinical teal as primary (trust, cleanliness,
            // distinct from alarming "hospital red"), a deep slate for text/structure,
            // and a warm amber accent reserved for attention states (not decoration).
            colors: {
                primary: {
                    50: '#eff9f9',
                    100: '#d7f0f0',
                    200: '#b3e2e1',
                    300: '#82cccb',
                    400: '#4dadac',
                    500: '#2f8f8e',
                    600: '#237373',
                    700: '#205d5e',
                    800: '#1f4c4d',
                    900: '#1c4041',
                    950: '#0c2425',
                },
                accent: {
                    50: '#fef8ec',
                    100: '#fcecc7',
                    200: '#f9d78a',
                    300: '#f6bd4d',
                    400: '#f3a524',
                    500: '#ec8a0c',
                    600: '#cf6807',
                    700: '#ac490a',
                    800: '#8c390f',
                    900: '#742f10',
                },
                surface: {
                    DEFAULT: '#ffffff',
                    subtle: '#f7f9f9',
                    muted: '#eef2f2',
                },
                ink: {
                    DEFAULT: '#152426',
                    muted: '#4c6265',
                    subtle: '#7c8f91',
                },
                success: {
                    50: '#effaf1',
                    500: '#1f9d4c',
                    600: '#188040',
                    700: '#146633',
                },
                warning: {
                    50: '#fff8eb',
                    500: '#e2a412',
                    600: '#bd850c',
                    700: '#8f640d',
                },
                danger: {
                    50: '#fdf1f1',
                    500: '#d1414a',
                    600: '#b32e39',
                    700: '#93242f',
                },
            },
            fontFamily: {
                sans: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            borderRadius: {
                DEFAULT: '0.5rem',
                lg: '0.75rem',
                xl: '1rem',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgba(21, 36, 38, 0.06), 0 1px 3px 0 rgba(21, 36, 38, 0.08)',
                popover: '0 4px 6px -1px rgba(21, 36, 38, 0.08), 0 10px 15px -3px rgba(21, 36, 38, 0.06)',
            },
            spacing: {
                18: '4.5rem',
                22: '5.5rem',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
