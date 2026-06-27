<template>
    <input
        ref="inputRef"
        v-model="value"
        type="text"
        class="shadcn-input"
        :class="{ 'shadcn-input--lg': large }"
        :placeholder="placeholder"
        autocomplete="off"
        @keydown="onInputKeyDown"
        @blur="onBlur"
    />
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scan barcode or type manually, then press Enter' },
    large: { type: Boolean, default: true },
    captureGlobal: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'scan', 'clear']);

const value = ref(props.modelValue);
const inputRef = ref(null);

let scanBuffer = '';
let lastKeyTime = 0;
let lastScannedCode = '';
let lastScannedAt = 0;

const SCAN_GAP_MS = 100;
const MIN_BARCODE_LENGTH = 2;
const DUPLICATE_SCAN_MS = 500;

watch(() => props.modelValue, (next) => {
    value.value = next;
});

watch(value, (next) => {
    emit('update:modelValue', next);

    if (!next.trim()) {
        emit('clear');
    }
});

function isOtherEditableFocused() {
    const active = document.activeElement;

    if (!active || active === inputRef.value) {
        return false;
    }

    if (active.isContentEditable) {
        return true;
    }

    const tag = active.tagName;

    if (tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }

    if (tag === 'INPUT') {
        const type = (active.type || 'text').toLowerCase();

        return !['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'hidden'].includes(type);
    }

    return Boolean(active.closest('input, textarea, select, [contenteditable="true"]'));
}

function resetScanBuffer() {
    scanBuffer = '';
    lastKeyTime = 0;
}

function processScan(barcode) {
    const code = barcode.trim();

    if (!code) {
        return;
    }

    const now = Date.now();

    if (code === lastScannedCode && now - lastScannedAt < DUPLICATE_SCAN_MS) {
        return;
    }

    lastScannedCode = code;
    lastScannedAt = now;
    emit('scan', code);
    resetScanBuffer();
    value.value = code;
}

function emitScan() {
    const code = (scanBuffer || value.value).trim();

    if (code.length < MIN_BARCODE_LENGTH) {
        return;
    }

    processScan(code);
}

function appendScanCharacter(key) {
    const now = Date.now();
    const gap = lastKeyTime > 0 ? now - lastKeyTime : SCAN_GAP_MS + 1;

    if (scanBuffer.length > 0 && gap <= SCAN_GAP_MS) {
        scanBuffer += key;
    } else {
        scanBuffer = key;
    }

    lastKeyTime = now;
    value.value = scanBuffer;
}

function onInputKeyDown(event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        emitScan();
        resetScanBuffer();
        return;
    }

    // While the field is focused, allow normal typing (manual entry).
    // Scanners still work because they fill the input and send Enter.
    resetScanBuffer();
}

function onGlobalKeyDown(event) {
    if (!props.captureGlobal) {
        return;
    }

    if (document.activeElement === inputRef.value) {
        return;
    }

    if (isOtherEditableFocused()) {
        resetScanBuffer();
        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (event.key === 'Enter') {
        if (scanBuffer.length >= MIN_BARCODE_LENGTH) {
            event.preventDefault();
            processScan(scanBuffer);
        }

        return;
    }

    if (event.key.length !== 1) {
        return;
    }

    event.preventDefault();
    appendScanCharacter(event.key);
}

function onBlur() {
    if (Date.now() - lastKeyTime < 500) {
        return;
    }

    emitScan();
}

onMounted(() => {
    inputRef.value?.focus();

    if (props.captureGlobal) {
        window.addEventListener('keydown', onGlobalKeyDown, true);
    }
});

onBeforeUnmount(() => {
    if (props.captureGlobal) {
        window.removeEventListener('keydown', onGlobalKeyDown, true);
    }
});

defineExpose({
    focus: () => inputRef.value?.focus(),
    clear: () => {
        resetScanBuffer();
        value.value = '';
    },
});
</script>
