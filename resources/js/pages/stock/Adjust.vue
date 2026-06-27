<template>
    <div :class="embedded ? 'space-y-6' : 'mx-auto max-w-2xl space-y-6'">
        <div v-if="!embedded" class="shadcn-card p-6">
            <h3 class="mb-4 text-lg font-semibold">Stock Adjustment</h3>
            <BarcodeScannerInput v-model="barcode" @scan="lookupItem" @clear="onBarcodeClear" />
        </div>

        <StockOpScanner
            v-else
            v-model="barcode"
            title="Scan to adjust stock"
            hint="Use a barcode scanner, or type the code manually and press Enter."
            @scan="lookupItem"
            @clear="onBarcodeClear"
        />

        <div v-if="item" class="stock-op-workspace">
            <StockOpItemSummary
                :item="item"
                :fields="[
                    { label: 'Barcode', value: item.barcode },
                    { label: 'System stock', value: item.current_stock },
                    { label: 'Category', value: item.category?.name || '—' },
                    { label: 'Unit', value: item.unit?.name || '—' },
                ]"
            />

            <div class="stock-op-form-panel">
                <div class="stock-op-form-header">
                    <h4 class="stock-op-form-title">Adjustment details</h4>
                    <p class="stock-op-form-desc">Set the actual count and reason for the variance.</p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Actual count</label>
                        <InputNumber v-model="actualCount" class="w-full" :min="0" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Reason</label>
                        <Select v-model="reason" :options="reasons" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Remarks</label>
                        <Textarea v-model="remarks" class="w-full" rows="2" placeholder="Optional explanation" />
                    </div>
                    <div class="stock-op-highlight" :class="adjustmentClass">
                        <span class="stock-op-highlight-label">Adjustment amount</span>
                        <strong class="stock-op-highlight-value">{{ adjustmentAmount }}</strong>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <UiButton type="submit" :loading="loading">Apply Adjustment</UiButton>
                        <UiButton type="button" variant="outline" :disabled="loading" @click="clearItem">Cancel</UiButton>
                    </div>
                </form>
            </div>
        </div>

        <div v-else-if="embedded" class="stock-op-empty">
            <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">No item selected</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">Scan an item barcode to record a stock adjustment.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import UiButton from '../../components/ui/UiButton.vue';
import BarcodeScannerInput from '../../components/BarcodeScannerInput.vue';
import StockOpScanner from '../../components/stock/StockOpScanner.vue';
import StockOpItemSummary from '../../components/stock/StockOpItemSummary.vue';
import { useNotify } from '../../composables/useNotify';
import api from '../../services/api';

defineProps({
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const barcode = ref('');
const item = ref(null);
const actualCount = ref(0);
const reason = ref('damaged');
const remarks = ref('');
const loading = ref(false);
const reasons = [
    { label: 'Damaged', value: 'damaged' },
    { label: 'Expired', value: 'expired' },
    { label: 'Lost', value: 'lost' },
    { label: 'Miscount', value: 'miscount' },
    { label: 'Correction', value: 'correction' },
];

const adjustmentAmount = computed(() => {
    if (!item.value) {
        return 0;
    }

    return (actualCount.value ?? 0) - item.value.current_stock;
});

const adjustmentClass = computed(() => {
    if (adjustmentAmount.value > 0) {
        return 'stock-op-highlight-positive';
    }

    if (adjustmentAmount.value < 0) {
        return 'stock-op-highlight-negative';
    }

    return '';
});

function clearItem() {
    item.value = null;
    barcode.value = '';
    actualCount.value = 0;
    reason.value = 'damaged';
    remarks.value = '';
}

function onBarcodeClear() {
    item.value = null;
    actualCount.value = 0;
    reason.value = 'damaged';
    remarks.value = '';
}

watch(barcode, (next) => {
    if (!next.trim()) {
        onBarcodeClear();
    }
});

async function lookupItem(code) {
    try {
        const { data } = await api.get(`/items/barcode/${encodeURIComponent(code)}`);
        if (!data.item) {
            notify.warn('Item not registered.', 'Not found');
            clearItem();
            return;
        }
        item.value = data.item;
        actualCount.value = data.item.current_stock;
        reason.value = 'damaged';
        remarks.value = '';
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to look up item.');
        clearItem();
    }
}

async function submit() {
    loading.value = true;
    try {
        const { data } = await api.post('/stock/adjust', {
            barcode: item.value.barcode,
            actual_count: actualCount.value,
            reason: reason.value,
            remarks: remarks.value || null,
        });
        notify.success(
            `Stock adjusted by ${data.adjustment > 0 ? '+' : ''}${data.adjustment}.`,
            'Adjustment applied',
        );
        clearItem();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to apply adjustment.', 'Adjustment failed');
    } finally {
        loading.value = false;
    }
}
</script>
