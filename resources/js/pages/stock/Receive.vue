<template>
    <div :class="embedded ? 'space-y-6' : 'mx-auto max-w-2xl space-y-6'">
        <div v-if="!embedded" class="shadcn-card p-6">
            <h3 class="mb-4 text-lg font-semibold">Stock Receiving</h3>
            <BarcodeScannerInput v-model="barcode" @scan="lookupItem" />
        </div>

        <StockOpScanner
            v-else
            v-model="barcode"
            title="Scan to receive stock"
            hint="Find a registered item and enter the quantity received."
            @scan="lookupItem"
        />

        <div v-if="item" class="stock-op-workspace">
            <StockOpItemSummary
                :item="item"
                :fields="[
                    { label: 'Barcode', value: item.barcode },
                    { label: 'Current stock', value: item.current_stock },
                    { label: 'Category', value: item.category?.name || '—' },
                    { label: 'Location', value: item.location?.name || '—' },
                ]"
            />

            <div class="stock-op-form-panel">
                <div class="stock-op-form-header">
                    <h4 class="stock-op-form-title">Receive quantity</h4>
                    <p class="stock-op-form-desc">Stock will be added to the current on-hand balance.</p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Quantity received</label>
                        <InputNumber
                            v-model="quantity"
                            class="w-full"
                            :min="0"
                            :use-grouping="false"
                            input-class="w-full"
                            @input="onQuantityInput"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Remarks</label>
                        <Textarea v-model="remarks" class="w-full" rows="2" placeholder="Optional notes about this delivery" />
                    </div>
                    <div class="stock-op-highlight">
                        <span class="stock-op-highlight-label">New stock after receive</span>
                        <strong class="stock-op-highlight-value">{{ newStockAfterReceive }}</strong>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <UiButton type="submit" :loading="loading">Receive Stock</UiButton>
                        <UiButton type="button" variant="outline" :disabled="loading" @click="clearItem">Cancel</UiButton>
                    </div>
                </form>
            </div>
        </div>

        <div v-else-if="embedded" class="stock-op-empty">
            <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">No item selected</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">Scan a registered item barcode to receive stock.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
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
const quantity = ref(0);
const remarks = ref('');
const loading = ref(false);

const newStockAfterReceive = computed(() => {
    if (!item.value) {
        return 0;
    }

    const receivedQty = Number(quantity.value) || 0;

    return item.value.current_stock + receivedQty;
});

function onQuantityInput(event) {
    quantity.value = event.value ?? 0;
}

function clearItem() {
    item.value = null;
    barcode.value = '';
    quantity.value = 0;
    remarks.value = '';
}

watch(barcode, (next) => {
    if (!next.trim()) {
        item.value = null;
        quantity.value = 0;
        remarks.value = '';
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
        quantity.value = 0;
        remarks.value = '';
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to look up item.');
        clearItem();
    }
}

async function submit() {
    const receivedQty = Number(quantity.value) || 0;

    if (receivedQty <= 0) {
        notify.warn('Enter a quantity greater than zero.', 'Invalid quantity');
        return;
    }

    loading.value = true;
    try {
        await api.post('/stock/receive', {
            barcode: item.value.barcode,
            quantity: receivedQty,
            remarks: remarks.value || null,
        });
        notify.success('Stock received successfully.', 'Stock received');
        clearItem();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to receive stock.', 'Receive failed');
    } finally {
        loading.value = false;
    }
}
</script>
