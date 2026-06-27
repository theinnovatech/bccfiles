<template>
    <div class="space-y-6">
        <template v-if="!editingId">
            <StockOpScanner
                v-model="barcode"
                title="Scan equipment barcode"
                hint="Scan or type a barcode to register equipment. If the barcode is new, complete the form below."
                @scan="lookupBarcode"
                @clear="onBarcodeClear"
            />

            <div>
                <UiButton variant="outline" @click="proceedWithoutBarcode">
                    Equipment with no barcode
                </UiButton>
            </div>
        </template>

        <div
            v-if="existingEquipment"
            class="stock-op-alert stock-op-alert-warn"
        >
            <div class="stock-op-alert-icon" aria-hidden="true">
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <div>
                <p class="stock-op-alert-title">Equipment already registered</p>
                <p class="stock-op-alert-text">
                    {{ existingEquipment.name }} ·
                    {{ existingEquipment.property_number }}
                </p>
            </div>
        </div>

        <div v-if="showForm" class="stock-op-form-panel">
            <div class="stock-op-form-header">
                <h4 class="stock-op-form-title">
                    {{ editingId ? "Edit equipment" : "Register equipment" }}
                </h4>
                <p class="stock-op-form-desc">
                    Record equipment details separate from inventory items.
                </p>
            </div>

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="save">
                <div v-if="editingId">
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Property No.</label
                    >
                    <InputText
                        v-model="form.property_number"
                        class="w-full"
                        readonly
                    />
                </div>
                <div v-else class="md:col-span-2">
                    <p
                        class="rounded-md border border-[#c8d6ef] bg-[#eef2fa] px-3 py-2 text-xs text-[#5b7fbf]"
                    >
                        Property number will be generated automatically when you
                        save this equipment.
                    </p>
                </div>

                <div v-if="form.barcode">
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Barcode</label
                    >
                    <InputText v-model="form.barcode" class="w-full" readonly />
                </div>
                <div
                    v-else-if="noBarcodeMode || editingId"
                    class="md:col-span-2"
                >
                    <p
                        class="rounded-md border border-dashed border-[#c8d6ef] bg-white px-3 py-2 text-xs text-[#5b7fbf]"
                    >
                        No barcode assigned for this equipment.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Name</label
                    >
                    <InputText v-model="form.name" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Category</label
                    >
                    <Select
                        v-model="form.equipment_category_id"
                        :options="categories"
                        option-label="name"
                        option-value="id"
                        class="w-full"
                        placeholder="Select category"
                        required
                    />
                    <p
                        v-if="!categories.length"
                        class="mt-1 text-xs text-[#5b7fbf]"
                    >
                        Add categories first in Master Data → Equipment
                        Categories.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Type</label
                    >
                    <InputText v-model="form.type" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Qty</label
                    >
                    <InputNumber
                        v-model="form.quantity"
                        class="w-full"
                        :min="1"
                        required
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Description</label
                    >
                    <Textarea
                        v-model="form.description"
                        class="w-full"
                        rows="3"
                        placeholder="Brief description of the equipment"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Specs</label
                    >
                    <Textarea
                        v-model="form.specs"
                        class="w-full"
                        rows="3"
                        placeholder="Specifications, model, serial number, etc."
                    />
                </div>
                <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row">
                    <UiButton type="submit" :loading="saving">
                        {{ editingId ? "Update Equipment" : "Save Equipment" }}
                    </UiButton>
                    <UiButton
                        v-if="editingId"
                        type="button"
                        variant="outline"
                        @click="resetForm"
                        >Cancel edit</UiButton
                    >
                    <UiButton
                        v-else
                        type="button"
                        variant="outline"
                        @click="resetForm"
                        >Clear form</UiButton
                    >
                </div>
            </form>
        </div>

        <div
            v-else-if="!editingId && !existingEquipment"
            class="stock-op-empty"
        >
            <svg
                class="h-10 w-10 text-[#c8d6ef]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 7h2l2-3h10l2 3h2a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V9a2 2 0 012-2z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 11v4m0 0v4m0-4h4m-4 0H8"
                />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">
                No barcode scanned yet
            </p>
            <p class="mt-1 text-xs text-[#5b7fbf]">
                Scan a barcode above or continue without one.
            </p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import UiButton from "../../components/ui/UiButton.vue";
import StockOpScanner from "../../components/stock/StockOpScanner.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

defineProps({
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const barcode = ref("");
const existingEquipment = ref(null);
const showForm = ref(false);
const noBarcodeMode = ref(false);
const saving = ref(false);
const editingId = ref(null);
const categories = ref([]);
const form = reactive({
    property_number: "",
    barcode: "",
    name: "",
    equipment_category_id: null,
    description: "",
    type: "",
    quantity: 1,
    specs: "",
});

function resetForm() {
    editingId.value = null;
    showForm.value = false;
    noBarcodeMode.value = false;
    existingEquipment.value = null;
    barcode.value = "";
    form.property_number = "";
    form.barcode = "";
    form.name = "";
    form.equipment_category_id = null;
    form.description = "";
    form.type = "";
    form.quantity = 1;
    form.specs = "";

    const url = new URL(window.location.href);
    url.searchParams.delete("edit");
    window.history.replaceState({}, "", url);
}

function startEdit(equipment) {
    editingId.value = equipment.id;
    showForm.value = true;
    noBarcodeMode.value = !equipment.barcode;
    form.property_number = equipment.property_number || "";
    form.barcode = equipment.barcode || "";
    form.name = equipment.name;
    form.equipment_category_id = equipment.equipment_category_id;
    form.description = equipment.description || "";
    form.type = equipment.type;
    form.quantity = equipment.quantity ?? 1;
    form.specs = equipment.specs || "";
}

function onBarcodeClear() {
    showForm.value = false;
    noBarcodeMode.value = false;
    existingEquipment.value = null;
    form.barcode = "";
}

function proceedWithoutBarcode() {
    existingEquipment.value = null;
    noBarcodeMode.value = true;
    form.barcode = "";
    showForm.value = true;
}

async function lookupBarcode(code) {
    existingEquipment.value = null;
    showForm.value = false;
    noBarcodeMode.value = false;

    try {
        const { data } = await api.get(
            `/equipments/barcode/${encodeURIComponent(code)}`,
        );

        if (data.equipment) {
            existingEquipment.value = data.equipment;
            return;
        }

        form.barcode = code;
        showForm.value = true;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to look up barcode.",
        );
    }
}

watch(barcode, (next) => {
    if (!next.trim() && !editingId.value) {
        onBarcodeClear();
    }
});

async function loadCategories() {
    try {
        const { data } = await api.get("/equipment-categories/list");
        categories.value = data.filter(
            (category) => category.is_active !== false,
        );
    } catch {
        notify.error("Unable to load equipment categories.");
    }
}

async function save() {
    saving.value = true;

    try {
        const payload = {
            barcode: form.barcode || null,
            name: form.name,
            equipment_category_id: form.equipment_category_id,
            description: form.description || null,
            type: form.type,
            quantity: form.quantity,
            specs: form.specs || null,
        };

        if (editingId.value) {
            await api.put(`/equipments/${editingId.value}`, payload);
            notify.success("Equipment updated.");
        } else {
            const { data } = await api.post("/equipments", payload);
            notify.success(
                `Equipment saved with property no. ${data.property_number}.`,
            );
        }

        resetForm();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to save equipment.",
        );
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await loadCategories();

    const editId = new URLSearchParams(window.location.search).get("edit");

    if (!editId) {
        return;
    }

    try {
        const { data } = await api.get(`/equipments/${editId}`);
        startEdit(data);
    } catch {
        notify.error("Unable to load equipment for editing.");
    }
});
</script>
