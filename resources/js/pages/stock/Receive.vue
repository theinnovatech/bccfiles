<template>
    <div :class="embedded ? 'space-y-6' : 'mx-auto max-w-2xl space-y-6'">
        <div v-if="!embedded" class="shadcn-card p-6">
            <h3 class="mb-4 text-lg font-semibold">Stock Receiving</h3>
            <BarcodeScannerInput
                v-model="barcode"
                placeholder="Scan barcode / item no., or type manually, then press Enter"
                @scan="lookupItem"
            />
        </div>

        <StockOpScanner
            v-else
            v-model="barcode"
            title="Receive stock"
            placeholder="Scan barcode / item no., or type manually, then press Enter"
            @scan="lookupItem"
        >
            <template #aside>
                <label class="stock-op-scan-field-label">Or select item</label>
                <Select
                    v-model="selectedItemId"
                    :options="itemOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    filterPlaceholder="Search item..."
                    placeholder="Select item"
                    class="w-full"
                    showClear
                    @update:model-value="onSelectItem"
                />
            </template>
        </StockOpScanner>

        <div v-if="item" class="stock-op-workspace">
            <StockOpItemSummary
                :item="item"
                :fields="[
                    { label: 'Barcode', value: item.barcode || '—' },
                    { label: 'Inventory No.', value: item.inventory_number || '—' },
                    { label: 'Item No.', value: item.item_number || '—' },
                    { label: 'Current stock', value: item.current_stock },
                    { label: 'Category', value: item.category?.name || '—' },
                    { label: 'Location', value: item.location?.name || '—' },
                ]"
            />

            <div class="stock-op-form-panel">
                <div class="stock-op-form-header">
                    <h4 class="stock-op-form-title">Receive quantity</h4>
                    <p class="stock-op-form-desc">
                        Stock will be added to the current on-hand balance.
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Quantity received
                            <span class="text-[#ce1126]">*</span></label
                        >
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
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Remarks</label
                        >
                        <Textarea
                            v-model="remarks"
                            class="w-full"
                            rows="2"
                            placeholder="Optional notes about this delivery"
                        />
                    </div>
                    <div class="stock-op-highlight">
                        <span class="stock-op-highlight-label"
                            >New stock after receive</span
                        >
                        <strong class="stock-op-highlight-value">{{
                            newStockAfterReceive
                        }}</strong>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <UiButton type="submit" :loading="loading"
                            >Receive Stock</UiButton
                        >
                        <UiButton
                            type="button"
                            variant="outline"
                            :disabled="loading"
                            @click="clearItem"
                            >Cancel</UiButton
                        >
                    </div>
                </form>
            </div>
        </div>

        <div v-else-if="embedded" class="stock-op-empty">
            <svg
                class="h-10 w-10 text-[#a8b8d4]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#00164d]">
                No item selected
            </p>
            <p class="mt-1 text-xs text-[#4a6490]">
                Enter a barcode or item number, or select an item.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import InputNumber from "primevue/inputnumber";
import Select from "primevue/select";
import Textarea from "primevue/textarea";
import UiButton from "../../components/ui/UiButton.vue";
import BarcodeScannerInput from "../../components/BarcodeScannerInput.vue";
import StockOpScanner from "../../components/stock/StockOpScanner.vue";
import StockOpItemSummary from "../../components/stock/StockOpItemSummary.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

defineProps({
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const barcode = ref("");
const item = ref(null);
const items = ref([]);
const selectedItemId = ref(null);
const quantity = ref(0);
const remarks = ref("");
const loading = ref(false);

const itemOptions = computed(() =>
    items.value.map((row) => ({
        value: row.id,
        label: row.item_number
            ? `${row.item_name} (${row.barcode || row.item_number})`
            : row.item_name,
    })),
);

const newStockAfterReceive = computed(() => {
    if (!item.value) {
        return 0;
    }

    const receivedQty = Number(quantity.value) || 0;

    return item.value.current_stock + receivedQty;
});

function itemLookupCode(row) {
    return row?.barcode || row?.item_number || "";
}

function onQuantityInput(event) {
    quantity.value = event.value ?? 0;
}

function clearItem() {
    item.value = null;
    barcode.value = "";
    selectedItemId.value = null;
    quantity.value = 0;
    remarks.value = "";
}

function applyItem(row) {
    item.value = row;
    selectedItemId.value = row.id;
    barcode.value = itemLookupCode(row);
    quantity.value = 0;
    remarks.value = "";
}

watch(barcode, (next) => {
    if (!next.trim() && !selectedItemId.value) {
        item.value = null;
        quantity.value = 0;
        remarks.value = "";
    }
});

async function loadItems() {
    try {
        const { data } = await api.get("/items/list", { params: { all: 1 } });
        items.value = data.data ?? data ?? [];
    } catch {
        items.value = [];
    }
}

async function lookupItem(code) {
    try {
        const { data } = await api.get(
            `/items/barcode/${encodeURIComponent(code)}`,
        );
        if (!data.item) {
            notify.warn("Item not registered.", "Not found");
            clearItem();
            return;
        }
        applyItem(data.item);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to look up item.",
        );
        clearItem();
    }
}

function onSelectItem(id) {
    if (!id) {
        clearItem();
        return;
    }

    const row = items.value.find((entry) => entry.id === id);
    if (!row) {
        notify.warn("Item not found in the list.", "Not found");
        return;
    }

    applyItem(row);
}

async function submit() {
    const receivedQty = Number(quantity.value) || 0;

    if (!item.value) {
        notify.warn("Select an item first.", "No item");
        return;
    }

    if (receivedQty <= 0) {
        notify.warn("Enter a quantity greater than zero.", "Invalid quantity");
        return;
    }

    const code = itemLookupCode(item.value);

    if (!code) {
        notify.warn("This item has no barcode or item number.", "Cannot receive");
        return;
    }

    loading.value = true;
    try {
        const { data } = await api.post("/stock/receive", {
            barcode: code,
            quantity: receivedQty,
            remarks: remarks.value || null,
        });
        notify.success("Stock received successfully.", "Stock received");
        if (data.item) {
            const index = items.value.findIndex((row) => row.id === data.item.id);
            if (index >= 0) {
                items.value[index] = data.item;
            }
        }
        clearItem();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to receive stock.",
            "Receive failed",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(loadItems);
</script>
