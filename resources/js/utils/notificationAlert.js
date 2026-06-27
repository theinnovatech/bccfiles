import ToastEventBus from 'primevue/toasteventbus';

const NOTIFICATION_SOUND = '/sounds/dragon-studio-new-notification-3-398649.mp3';

let notificationAudio = null;

function playNotificationSound() {
    try {
        if (!notificationAudio) {
            notificationAudio = new Audio(NOTIFICATION_SOUND);
            notificationAudio.volume = 0.75;
        }

        notificationAudio.currentTime = 0;
        notificationAudio.play().catch(() => {});
    } catch {
        // ignore autoplay restrictions
    }
}

function toastSeverity(type) {
    if (type === 'supply_request_rejected') {
        return 'error';
    }

    return 'success';
}

export function showNotificationToast(payload) {
    playNotificationSound();

    ToastEventBus.emit('add', {
        severity: toastSeverity(payload.type),
        summary: payload.title,
        detail: payload.message,
        life: 6000,
        group: 'obims',
    });
}
