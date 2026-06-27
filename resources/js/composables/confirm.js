import { reactive } from 'vue';

export const confirmState = reactive({
    visible: false,
    title: 'Confirm action',
    message: '',
    detail: '',
    acceptLabel: 'Confirm',
    rejectLabel: 'Cancel',
    loading: false,
    variant: 'default',
});

let pendingResolve = null;
let pendingAccept = null;

export function openConfirm(options) {
    return new Promise((resolve) => {
        pendingResolve = resolve;
        pendingAccept = options.onAccept ?? null;

        confirmState.title = options.title ?? 'Confirm action';
        confirmState.message = options.message ?? '';
        confirmState.detail = options.detail ?? '';
        confirmState.acceptLabel = options.acceptLabel ?? 'Confirm';
        confirmState.rejectLabel = options.rejectLabel ?? 'Cancel';
        confirmState.variant = options.variant ?? 'default';
        confirmState.loading = false;
        confirmState.visible = true;
    });
}

export function confirmDelete(options) {
    return openConfirm({
        variant: 'destructive',
        acceptLabel: options.acceptLabel ?? 'Delete',
        title: options.title ?? 'Delete record?',
        ...options,
    });
}

export async function handleConfirmAccept() {
    confirmState.loading = true;

    try {
        if (pendingAccept) {
            await pendingAccept();
        }

        pendingResolve?.(true);
    } catch (error) {
        pendingResolve?.(false);
        throw error;
    } finally {
        confirmState.visible = false;
        confirmState.loading = false;
        pendingResolve = null;
        pendingAccept = null;
    }
}

export function handleConfirmReject() {
    confirmState.visible = false;
    pendingResolve?.(false);
    pendingResolve = null;
    pendingAccept = null;
}
