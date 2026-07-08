import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                narrv: {
                    50: '#f0f7ff',
                    100: '#e0effe',
                    200: '#b9dffd',
                    300: '#7cc5fc',
                    400: '#36a9f8',
                    500: '#0c8ee9',
                    600: '#0070c7',
                    700: '#0159a1',
                    800: '#064b85',
                    900: '#0b3f6e',
                }
            }
        },
    },
    plugins: [],
};
