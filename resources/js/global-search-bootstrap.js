import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import GlobalSearch from './components/GlobalSearch.vue';
import { useAuthStore } from './stores/auth';

export async function bootstrapGlobalSearch() {
    const mountEl = document.getElementById('global-search-app');

    if (!mountEl) {
        return;
    }

    const pinia = createPinia();

    const app = createApp({
        render: () => h(GlobalSearch),
    });

    app.use(pinia);

    const auth = useAuthStore(pinia);
    await auth.fetchUser();

    if (!auth.user) {
        return;
    }

    app.mount(mountEl);
}
