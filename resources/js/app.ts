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

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Your Custom Theme (Maniratn Gold)
const ManiratnGold = definePreset(Nora, {
    semantic: {
        primary: {
            50: '#f2f7f6',
            100: '#e2eeec',
            200: '#c4ddd8',
            300: '#9bc5bd',
            400: '#6ca89e',
            500: '#4a8b80',
            600: '#356f66',
            700: '#2b5a53',
            800: '#254943',
            900: '#1c3633',
            950: '#102421',
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
                    darkModeSelector: '.dark',
                    cssLayer: {
                        name: 'primevue',
                        order: 'theme, base, primevue, components, utilities',
                    },
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
        color: '#c08f34',
    },
});
