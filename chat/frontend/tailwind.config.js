/** @type {import('tailwindcss').Config} */
export default {
    content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                    950: '#052e16',
                },
                chat: {
                    bg: '#0f1117',
                    panel: '#161b22',
                    card: '#1c2333',
                    hover: '#21262d',
                    border: '#30363d',
                    input: '#21262d',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            animation: {
                'fade-in': 'fadeIn .15s ease-out',
                'slide-up': 'slideUp .2s ease-out',
                'pulse-dot': 'pulseDot 1.5s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: { from: { opacity: '0' }, to: { opacity: '1' } },
                slideUp: { from: { transform: 'translateY(6px)', opacity: '0' }, to: { transform: 'translateY(0)', opacity: '1' } },
                pulseDot: { '0%,100%': { opacity: '1' }, '50%': { opacity: '.3' } },
            },
        },
    },
    plugins: [],
}
