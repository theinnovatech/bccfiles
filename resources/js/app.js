import './layout';
import { bootstrapNotifications } from './notifications-bootstrap';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

const ObimsPreset = definePreset(Aura, {
    semantic: {
        formField: {
            paddingY: '0',
            paddingX: '0.75rem',
        },
    },
});
import ToastService from 'primevue/toastservice';
import Toast from 'primevue/toast';
import VueApexCharts from 'vue3-apexcharts';
import UiConfirmDialog from './components/ui/UiConfirmDialog.vue';
import { pageModules } from './pages';
import { useAuthStore } from './stores/auth';

function setupApp(app, pinia) {
    app.component('apexchart', VueApexCharts);
    app.use(pinia);
    app.use(PrimeVue, {
        theme: {
            preset: ObimsPreset,
            options: {
                darkModeSelector: false,
                cssLayer: {
                    name: 'primevue',
                    order: 'theme, base, primevue',
                },
            },
        },
    });
    app.use(ToastService);
}

async function bootstrap() {
    const mountEl = document.getElementById('page-app')
        ?? document.getElementById('login-app')
        ?? document.getElementById('about-app');

    if (!mountEl) {
        return;
    }

    const pageKey = mountEl.dataset.page;
    const pageProps = JSON.parse(mountEl.dataset.props || '{}');
    const loadPage = pageModules[pageKey];

    if (!loadPage) {
        throw new Error(`Unknown page: ${pageKey}`);
    }

    const { default: PageComponent } = await loadPage();
    const pinia = createPinia();

    const app = createApp({
        render: () => h('div', [
            h(Toast, {
                group: 'obims',
                position: 'top-right',
                breakpoints: {
                    '639px': {
                        left: '0.75rem',
                        right: '0.75rem',
                        width: 'auto',
                        top: 'max(0.75rem, env(safe-area-inset-top, 0px))',
                    },
                },
            }),
            h(UiConfirmDialog),
            h(PageComponent, pageProps),
        ]),
    });

    setupApp(app, pinia);

    if (mountEl.id === 'page-app') {
        await useAuthStore(pinia).fetchUser();
    }

    app.mount(mountEl);
}

bootstrap().catch((error) => {
    const mountEl = document.getElementById('page-app')
        ?? document.getElementById('login-app')
        ?? document.getElementById('about-app');
    if (mountEl) {
        mountEl.innerHTML = '<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">Unable to load this page. Please refresh and try again.</div>';
    }
    console.error(error);
});

bootstrapNotifications().catch((error) => {
    console.error('Notification bootstrap failed:', error);
});
