import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import NotificationBell from './components/NotificationBell.vue';
import { useAuthStore } from './stores/auth';
import { useNotificationsStore } from './stores/notifications';

export async function bootstrapNotifications() {
    const mountEl = document.getElementById('notification-app');

    if (!mountEl) {
        return;
    }

    const pinia = createPinia();

    const app = createApp({
        render: () => h(NotificationBell),
    });

    app.use(pinia);

    const auth = useAuthStore(pinia);
    await auth.fetchUser();

    if (!auth.user) {
        return;
    }

    const notifications = useNotificationsStore(pinia);
    await notifications.load();
    notifications.subscribe(auth.user.id);

    app.mount(mountEl);
}
