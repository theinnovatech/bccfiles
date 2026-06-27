<template>
    <div class="notification-center" ref="rootRef">
        <button
            type="button"
            class="notification-bell"
            aria-label="Notifications"
            @click="toggleOpen"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span v-if="store.unreadCount > 0" class="notification-badge">
                {{ store.unreadCount > 9 ? '9+' : store.unreadCount }}
            </span>
        </button>

        <div v-if="open" class="notification-panel">
            <div class="notification-panel-header">
                <p class="notification-panel-title">Notifications</p>
                <div class="notification-panel-actions">
                    <button
                        v-if="store.unreadCount > 0"
                        type="button"
                        class="notification-mark-all"
                        @click="store.markAllAsRead()"
                    >
                        Mark all read
                    </button>
                    <button
                        type="button"
                        class="notification-close"
                        aria-label="Close notifications"
                        @click="open = false"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="!store.items.length" class="notification-empty">
                No notifications yet.
            </div>

            <ul v-else class="notification-list">
                <li
                    v-for="item in store.items"
                    :key="item.id"
                    class="notification-item"
                    :class="{ 'notification-item-unread': !item.read_at }"
                    @click="openNotification(item)"
                >
                    <p class="notification-item-title">{{ item.title }}</p>
                    <p class="notification-item-message">{{ item.message }}</p>
                    <p class="notification-item-time">{{ formatTime(item.created_at) }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useNotificationsStore } from '../stores/notifications';

const store = useNotificationsStore();
const open = ref(false);
const rootRef = ref(null);

function toggleOpen() {
    open.value = !open.value;
}

function formatTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString();
}

async function openNotification(item) {
    await store.markAsRead(item);

    const url = item.data?.url;

    if (url) {
        window.location.href = url;
    }
}

function handleDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>
