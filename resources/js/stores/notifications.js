import { defineStore } from 'pinia';
import api from '../services/api';
import { getEcho } from '../echo';
import { showNotificationToast } from '../utils/notificationAlert';

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        items: [],
        unreadCount: 0,
        loaded: false,
        subscribedUserId: null,
    }),
    actions: {
        async load() {
            try {
                const { data } = await api.get('/notifications/list');
                this.items = data.notifications ?? [];
                this.unreadCount = data.unread_count ?? 0;
            } catch {
                this.items = [];
                this.unreadCount = 0;
            } finally {
                this.loaded = true;
            }
        },

        subscribe(userId) {
            if (!userId || this.subscribedUserId === userId) {
                return;
            }

            const echo = getEcho();

            if (!echo) {
                return;
            }

            if (this.subscribedUserId) {
                echo.leave(`private-App.Models.User.${this.subscribedUserId}`);
            }

            this.subscribedUserId = userId;

            echo.private(`App.Models.User.${userId}`)
                .listen('.obims.notification', (payload) => {
                    this.handleRealtime(payload);
                });
        },

        handleRealtime(payload) {
            const exists = this.items.some((item) => item.id === payload.id);

            if (!exists) {
                this.items.unshift(payload);

                if (!payload.read_at) {
                    this.unreadCount += 1;
                }
            }

            showNotificationToast(payload);
        },

        async markAsRead(notification) {
            if (notification.read_at) {
                return;
            }

            try {
                await api.post(`/notifications/${notification.id}/read`);
                notification.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            } catch {
                // ignore
            }
        },

        async markAllAsRead() {
            try {
                await api.post('/notifications/read-all');
                this.items.forEach((item) => {
                    if (!item.read_at) {
                        item.read_at = new Date().toISOString();
                    }
                });
                this.unreadCount = 0;
            } catch {
                // ignore
            }
        },
    },
});
