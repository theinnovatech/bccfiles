<template>
    <div class="space-y-6">
        <StockOpScanner
            v-model="barcode"
            title="Receive equipment"
            placeholder="Scan barcode / property no., or type manually, then press Enter"
            @scan="lookupEquipment"
        >
            <template #aside>
                <label class="stock-op-scan-field-label">Or select equipment</label>
                <Select
                    v-model="selectedEquipmentId"
                    :options="equipmentOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    filterPlaceholder="Search equipment..."
                    placeholder="Select equipment"
                    class="w-full"
                    showClear
                    @update:model-value="onSelectEquipment"
                />
            </template>
        </StockOpScanner>

        <div v-if="equipment" class="stock-op-workspace">
            <StockOpItemSummary
                :item="summaryItem"
                :fields="[
                    {
                        label: 'Inventory No.',
                        value: equipment.inventory_number || '—',
                    },
                    {
                        label: 'Property No.',
                        value: equipment.property_number || '—',
                    },
                    {
                        label: 'Barcode',
                        value: equipment.barcode || '—',
                    },
                    {
                        label: 'Current quantity',
                        value: equipment.quantity,
                    },
                    {
                        label: 'Category',
                        value: equipment.category?.name || '—',
                    },
                    {
                        label: 'Type',
                        value: equipment.type || '—',
                    },
                ]"
            />

            <div class="stock-op-form-panel">
                <div class="stock-op-form-header">
                    <h4 class="stock-op-form-title">Receive quantity</h4>
                    <p class="stock-op-form-desc">
                        Quantity will be added to the current on-hand equipment
                        balance.
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
                            >New quantity after receive</span
                        >
                        <strong class="stock-op-highlight-value">{{
                            newQuantityAfterReceive
                        }}</strong>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <UiButton type="submit" :loading="loading"
                            >Receive Equipment</UiButton
                        >
                        <UiButton
                            type="button"
                            variant="outline"
                            :disabled="loading"
                            @click="clearEquipment"
                            >Cancel</UiButton
                        >
                    </div>
                </form>
            </div>
        </div>

        <div v-else class="stock-op-empty">
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
                No equipment selected
            </p>
            <p class="mt-1 text-xs text-[#4a6490]">
                Enter a barcode or property number, or select equipment.
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
import StockOpScanner from "../../components/stock/StockOpScanner.vue";
import StockOpItemSummary from "../../components/stock/StockOpItemSummary.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();
const barcode = ref("");
const equipment = ref(null);
const equipments = ref([]);
const selectedEquipmentId = ref(null);
const quantity = ref(0);
const remarks = ref("");
const loading = ref(false);

const equipmentOptions = computed(() =>
    equipments.value.map((row) => ({
        value: row.id,
        label: row.property_number
            ? `${row.name} (${row.property_number})`
            : row.name,
    })),
);

const summaryItem = computed(() => {
    if (!equipment.value) {
        return { item_name: "", barcode: "" };
    }

    return {
        item_name: equipment.value.name,
        barcode:
            equipment.value.barcode ||
            equipment.value.property_number ||
            "",
    };
});

const newQuantityAfterReceive = computed(() => {
    if (!equipment.value) {
        return 0;
    }

    return (Number(equipment.value.quantity) || 0) + (Number(quantity.value) || 0);
});

function onQuantityInput(event) {
    quantity.value = event.value ?? 0;
}

function clearEquipment() {
    equipment.value = null;
    barcode.value = "";
    selectedEquipmentId.value = null;
    quantity.value = 0;
    remarks.value = "";
}

watch(barcode, (next) => {
    if (!next.trim()) {
        if (!selectedEquipmentId.value) {
            equipment.value = null;
            quantity.value = 0;
            remarks.value = "";
        }
    }
});

async function loadEquipments() {
    try {
        const { data } = await api.get("/equipments/list");
        equipments.value = data ?? [];
    } catch {
        equipments.value = [];
    }
}

function applyEquipment(row) {
    equipment.value = row;
    selectedEquipmentId.value = row.id;
    barcode.value = row.barcode || row.property_number || "";
    quantity.value = 0;
    remarks.value = "";
}

async function lookupEquipment(code) {
    try {
        const { data } = await api.get(
            `/equipments/barcode/${encodeURIComponent(code)}`,
        );
        if (!data.equipment) {
            notify.warn("Equipment not registered.", "Not found");
            clearEquipment();
            return;
        }
        applyEquipment(data.equipment);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to look up equipment.",
        );
        clearEquipment();
    }
}

function onSelectEquipment(id) {
    if (!id) {
        clearEquipment();
        return;
    }

    const row = equipments.value.find((entry) => entry.id === id);
    if (!row) {
        notify.warn("Equipment not found in the list.", "Not found");
        return;
    }

    applyEquipment(row);
}

async function submit() {
    const receivedQty = Number(quantity.value) || 0;

    if (!equipment.value) {
        notify.warn("Select equipment first.", "No equipment");
        return;
    }

    if (receivedQty <= 0) {
        notify.warn("Enter a quantity greater than zero.", "Invalid quantity");
        return;
    }

    loading.value = true;
    try {
        const { data } = await api.post("/equipments/receive", {
            equipment_id: equipment.value.id,
            quantity: receivedQty,
            remarks: remarks.value || null,
        });
        notify.success("Equipment received successfully.", "Equipment received");
        if (data.equipment) {
            const index = equipments.value.findIndex(
                (row) => row.id === data.equipment.id,
            );
            if (index >= 0) {
                equipments.value[index] = data.equipment;
            }
        }
        clearEquipment();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to receive equipment.",
            "Receive failed",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(loadEquipments);
</script>
