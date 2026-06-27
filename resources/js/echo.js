import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getEcho() {
    const key = import.meta.env.VITE_PUSHER_APP_KEY;

    if (!key) {
        return null;
    }

    if (!echoInstance) {
        window.Pusher = Pusher;

        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            },
        });
    }

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}
