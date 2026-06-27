import { useToast } from 'primevue/usetoast';

export function useNotify() {
    const toast = useToast();

    function show(type, title, message, life) {
        toast.add({
            severity: type,
            summary: title,
            detail: message,
            life: life ?? (type === 'error' ? 6000 : 4500),
            group: 'obims',
        });
    }

    return {
        success(message, title = 'Success') {
            show('success', title, message);
        },
        error(message, title = 'Something went wrong') {
            show('error', title, message, 6000);
        },
        warn(message, title = 'Warning') {
            show('warn', title, message);
        },
        info(message, title = 'Notice') {
            show('info', title, message);
        },
    };
}
