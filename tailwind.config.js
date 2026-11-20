import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            colors: {
                'primary': '#001a41',
                'primary-container': '#001a41',
                'on-primary': '#ffffff',
                'on-primary-container': '#6f84b0',
                'primary-fixed': '#d8e2ff',
                'primary-fixed-dim': '#b2c6f7',
                'on-primary-fixed': '#001a41',
                'on-primary-fixed-variant': '#32466f',

                'secondary': '#0054cd',
                'secondary-container': '#316ee9',
                'on-secondary': '#ffffff',
                'on-secondary-container': '#fefcff',
                'secondary-fixed': '#dae2ff',
                'secondary-fixed-dim': '#b2c5ff',
                'on-secondary-fixed': '#001847',
                'on-secondary-fixed-variant': '#0040a1',

                'tertiary': '#000000',
                'tertiary-container': '#360f00',
                'on-tertiary': '#ffffff',
                'on-tertiary-container': '#b47458',
                'tertiary-fixed': '#ffdbcd',
                'tertiary-fixed-dim': '#ffb596',
                'on-tertiary-fixed': '#360f00',
                'on-tertiary-fixed-variant': '#6c3921',

                'surface': '#f7fafc',
                'surface-bright': '#f7fafc',
                'surface-dim': '#d7dadc',
                'surface-container-lowest': '#ffffff',
                'surface-container-low': '#f1f4f6',
                'surface-container': '#ebeef0',
                'surface-container-high': '#e5e9eb',
                'surface-container-highest': '#e0e3e5',

                'surface-variant': '#e0e3e5',
                'on-surface': '#181c1e',
                'on-surface-variant': '#44474e',
                'surface-tint': '#4a5e88',

                'inverse-surface': '#2d3133',
                'inverse-on-surface': '#eef1f3',
                'inverse-primary': '#b2c6f7',

                'background': '#f7fafc',
                'on-background': '#181c1e',

                'outline': '#75777f',
                'outline-variant': '#c5c6cf',

                'error': '#ba1a1a',
                'error-container': '#ffdad6',
                'on-error': '#ffffff',
                'on-error-container': '#93000a',

                'success': '#007d44',
                'warning': '#924a00',
                'info': '#0061a4',
            },

            borderRadius: {
                'DEFAULT': '0.25rem',
                'lg': '0.5rem',
                'xl': '0.75rem',
                'full': '9999px',
            },

            spacing: {
                'gutter-component': '1rem',
                'container-split': '50%',
                'stack-sm': '0.5rem',
                'stack-md': '1.5rem',
                'stack-lg': '2.5rem',
                'margin-page': '2rem',
            },

            fontFamily: {
                'sans': ['Inter', ...defaultTheme.fontFamily.sans],
                'label-sm': ['Inter'],
                'label-md': ['Inter'],
                'body-sm': ['Inter'],
                'body-md': ['Inter'],
                'headline-sm': ['Inter'],
                'headline-lg': ['Inter'],
                'headline-lg-mobile': ['Inter'],
                'headline-xl': ['Inter'],
            },

            fontSize: {
                'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
                'label-md': ['14px', { lineHeight: '16px', fontWeight: '600' }],
                'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'headline-sm': ['14px', { lineHeight: '20px', letterSpacing: '0.05em', fontWeight: '700' }],
                'headline-lg': ['24px', { lineHeight: '32px', letterSpacing: '-0.01em', fontWeight: '700' }],
                'headline-lg-mobile': ['22px', { lineHeight: '28px', fontWeight: '700' }],
                'headline-xl': ['32px', { lineHeight: '40px', letterSpacing: '-0.02em', fontWeight: '700' }],
            },

            boxShadow: {
                'ambient': '0 10px 30px rgba(0, 26, 65, 0.04)',
            },
        },
    },

    plugins: [forms, typography],
};
