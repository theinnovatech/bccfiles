<template>
    <div class="stock-op-scan">
        <div class="stock-op-scan-icon" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h2l2-3h10l2 3h2a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V9a2 2 0 012-2z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m0 0v4m0-4h4m-4 0H8" />
            </svg>
        </div>
        <div class="stock-op-scan-body">
            <p class="stock-op-scan-title">{{ title }}</p>
            <p class="stock-op-scan-hint">{{ hint }}</p>
            <BarcodeScannerInput ref="scannerRef" v-model="barcode" class="mt-3" @scan="onScan" @clear="onClear" />
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import BarcodeScannerInput from '../BarcodeScannerInput.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    title: { type: String, default: 'Scan barcode' },
    hint: { type: String, default: 'Use a barcode scanner, or type the code manually and press Enter.' },
});

const emit = defineEmits(['update:modelValue', 'scan', 'clear']);

const barcode = ref(props.modelValue);
const scannerRef = ref(null);

watch(() => props.modelValue, (value) => {
    barcode.value = value;
});

watch(barcode, (value) => emit('update:modelValue', value));

function onScan(code) {
    emit('scan', code);
}

function onClear() {
    emit('clear');
}

defineExpose({
    focus: () => scannerRef.value?.focus(),
});
</script>
