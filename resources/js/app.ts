import '../css/app.css';

// PrimeVue styles
import 'primeicons/primeicons.css';

// Sakai layout styles
import './assets/styles.scss';

import { createInertiaApp } from '@inertiajs/vue3';
import { definePreset } from '@primevue/themes';
import Nora from '@primevue/themes/nora';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';
import ToastService from 'primevue/toastservice';

// PrimeVue directives
import AnimateOnScroll from 'primevue/animateonscroll';
import BadgeDirective from 'primevue/badgedirective';
import Ripple from 'primevue/ripple';
import StyleClass from 'primevue/styleclass';
import Tooltip from 'primevue/tooltip';

import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Your Custom Theme (Maniratn Gold)
const ManiratnGold = definePreset(Nora, {
    semantic: {
        primary: {
            50: '#eff6ff',
            100: '#dbeafe',
            200: '#bfdbfe',
            300: '#93c5fd',
            400: '#60a5fa',
            500: '#3b82f6',
            600: '#2563eb',
            700: '#1d4ed8',
            800: '#1e40af',
            900: '#1e3a8a',
            950: '#172554',
        },
    },
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        app.use(ZiggyVue);
        app.use(ToastService);
        app.use(ConfirmationService);
        app.use(DialogService);

        app.use(PrimeVue, {
            theme: {
                preset: ManiratnGold,
                options: {
                    // IMPORTANT: Sakai uses '.app-dark' by default in its layout files.
                    // You have two choices:
                    //   Option A: Change this to '.app-dark' and update your useAppearance composable
                    //   Option B: Keep '.dark' and update Sakai's layout files to use '.dark'
                    // Choose whichever is easier for your project.
                    darkModeSelector: '.dark',
                },
            },
            ripple: true,
        });

        // PrimeVue directives
        app.directive('ripple', Ripple);
        app.directive('tooltip', Tooltip);
        app.directive('styleclass', StyleClass);
        app.directive('badge', BadgeDirective);
        app.directive('animateonscroll', AnimateOnScroll);

        // Your custom global property
        app.config.globalProperties.$formatMoney = (value: number) => {
            const amount = Number(value) || 0;
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                maximumFractionDigits: 2,
            }).format(amount);
        };

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
