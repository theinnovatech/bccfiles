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
                    {{
                        existingEquipment.inventory_number ||
                        existingEquipment.property_number
                    }}
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
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Property No.
                        <span
                            v-if="!editingId && !numbering.isAuto('equipment_property_number')"
                            class="text-[#ce1126]"
                            >*</span
                        ></label
                    >
                    <InputText
                        v-model="form.property_number"
                        class="w-full"
                        :readonly="!canEditNumbers"
                        :placeholder="editingId ? '' : numbering.placeholder('equipment_property_number')"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        {{
                            editingId
                                ? "Official number of this record. Changing it never creates a new record; leading zeros and letters are kept as typed."
                                : numbering.hint("equipment_property_number")
                        }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Inventory No.</label
                    >
                    <InputText
                        v-model="form.inventory_number"
                        class="w-full"
                        :readonly="!canEditNumbers"
                        :placeholder="editingId ? 'Not assigned' : numbering.placeholder('equipment_inventory_number')"
                    />
                    <p v-if="!editingId" class="mt-1 text-xs text-[#4a6490]">
                        {{ numbering.hint("equipment_inventory_number") }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Property Card No.</label
                    >
                    <InputText
                        v-model="form.property_card_number"
                        class="w-full"
                        :readonly="!canEditNumbers"
                        :placeholder="editingId ? 'Not assigned' : numbering.placeholder('property_card_number')"
                    />
                    <p v-if="!editingId" class="mt-1 text-xs text-[#4a6490]">
                        {{ numbering.hint("property_card_number") }}
                    </p>
                </div>

                <div v-if="form.barcode">
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Barcode</label
                    >
                    <InputText v-model="form.barcode" class="w-full" readonly />
                </div>
                <div
                    v-else-if="noBarcodeMode || editingId"
                    class="md:col-span-2"
                >
                    <p
                        class="border border-dashed border-[#a8b8d4] bg-transparent px-3 py-2 text-xs text-[#4a6490]"
                    >
                        No barcode assigned for this equipment.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Name
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputText v-model="form.name" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Category
                        <span class="text-[#ce1126]">*</span></label
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
                        class="mt-1 text-xs text-[#4a6490]"
                    >
                        Add categories first in Settings → Equipment
                        Categories.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Type
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputText v-model="form.type" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Brand</label
                    >
                    <InputText
                        v-model="form.brand"
                        class="w-full"
                        maxlength="255"
                        placeholder="e.g. Lenovo (as shown on the unit)"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Model</label
                    >
                    <InputText
                        v-model="form.model"
                        class="w-full"
                        maxlength="255"
                        placeholder="e.g. ThinkPad E14 Gen 5"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Qty
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputNumber
                        v-model="form.quantity"
                        class="w-full"
                        :min="1"
                        required
                    />
                </div>
                <EquipmentSerialFields
                    v-model="form.serial_numbers"
                    :quantity="form.quantity || 1"
                />
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Life Span (Years)
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputNumber
                        v-model="form.life_span_years"
                        class="w-full"
                        :min="editingId ? 0 : 1"
                        :max="100"
                        placeholder="e.g. 5"
                        required
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        After a return before the limit, this shows remaining
                        years instead of the original life span.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Property class</label>
                    <Select
                        v-model="form.property_class"
                        :options="propertyClassOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        placeholder="Not classified yet"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">Semi-expendable units go on the ICS and the IIRUP.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Unit cost</label>
                    <InputNumber
                        v-model="form.unit_cost"
                        class="w-full"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        :min="0"
                        placeholder="Optional"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">Documented acquisition cost. Leave blank if unknown; it prints blank on forms.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
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
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Specs</label
                    >
                    <Textarea
                        v-model="form.specs"
                        class="w-full"
                        rows="3"
                        placeholder="Specifications, model, or other details"
                    />
                </div>
                <div
                    v-if="editingId && units.length"
                    class="md:col-span-2 rounded-md border border-[#c9d4e6] p-3"
                >
                    <p class="text-sm font-medium text-[#00164d]">
                        Numbers recorded on each unit
                    </p>
                    <p class="mb-2 text-xs text-[#4a6490]">
                        Each physical unit keeps its own property and inventory
                        numbers through issuance, return and repair. Correct a
                        unit's numbers here; the unit and its history stay the
                        same.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="text-left text-xs text-[#4a6490]">
                                    <th class="py-1 pr-2">Serial No.</th>
                                    <th class="py-1 pr-2">Status</th>
                                    <th class="py-1 pr-2">Property No.</th>
                                    <th class="py-1 pr-2">Inventory No.</th>
                                    <th class="py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="unit in units" :key="unit.id">
                                    <td class="py-1 pr-2 font-mono">
                                        {{ unit.serial_number }}
                                    </td>
                                    <td class="py-1 pr-2">
                                        {{ unitStatusLabel(unit.status) }}
                                    </td>
                                    <td class="py-1 pr-2">
                                        <InputText
                                            v-model="unit.edit_property_number"
                                            class="w-full"
                                            size="small"
                                            :readonly="!canEditNumbers"
                                        />
                                    </td>
                                    <td class="py-1 pr-2">
                                        <InputText
                                            v-model="unit.edit_inventory_number"
                                            class="w-full"
                                            size="small"
                                            :readonly="!canEditNumbers"
                                        />
                                    </td>
                                    <td class="py-1">
                                        <UiButton
                                            v-if="canEditNumbers"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            :disabled="!unitChanged(unit)"
                                            :loading="unit.saving"
                                            @click="saveUnit(unit)"
                                            >Save</UiButton
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                    d="M3 7h2l2-3h10l2 3h2a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V9a2 2 0 012-2z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 11v4m0 0v4m0-4h4m-4 0H8"
                />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#00164d]">
                No barcode scanned yet
            </p>
            <p class="mt-1 text-xs text-[#4a6490]">
                Scan a barcode above or continue without one.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import UiButton from "../../components/ui/UiButton.vue";
import StockOpScanner from "../../components/stock/StockOpScanner.vue";
import EquipmentSerialFields from "../../components/stock/EquipmentSerialFields.vue";
import { useNotify } from "../../composables/useNotify";
import { useDocumentNumbering } from "../../composables/useDocumentNumbering";
import { useAuthStore } from "../../stores/auth";
import api from "../../services/api";
import {
    filledSerialNumbers,
    inStockSerials,
    syncSerialSlots,
} from "../../utils/equipmentSerials";

defineProps({
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const numbering = useDocumentNumbering();
const auth = useAuthStore();
const canEditNumbers = computed(
    () => !editingId.value || auth.isAdmin || auth.isSupplyOfficer,
);
const units = ref([]);
const UNIT_STATUS = {
    in_stock: "Available",
    issued: "Issued",
    unavailable: "Unserviceable (awaiting assessment)",
    for_repair: "Under repair",
    for_disposal: "For disposal",
    disposed: "Disposed",
};
const barcode = ref("");
const existingEquipment = ref(null);
const showForm = ref(false);
const noBarcodeMode = ref(false);
const saving = ref(false);
const editingId = ref(null);
const categories = ref([]);
const form = reactive({
    inventory_number: "",
    property_number: "",
    property_card_number: "",
    barcode: "",
    name: "",
    brand: "",
    model: "",
    equipment_category_id: null,
    description: "",
    type: "",
    quantity: 1,
    serial_numbers: [""],
    life_span_years: null,
    specs: "",
    unit_cost: null,
    property_class: null,
});

const propertyClassOptions = [
    { label: "Not classified yet", value: null },
    { label: "Semi-expendable property (ICS / IIRUP)", value: "semi_expendable" },
    { label: "Property, plant and equipment (PAR / Property Card)", value: "ppe" },
];


function resetForm() {
    editingId.value = null;
    showForm.value = false;
    noBarcodeMode.value = false;
    existingEquipment.value = null;
    barcode.value = "";
    form.inventory_number = "";
    form.property_number = "";
    form.property_card_number = "";
    form.barcode = "";
    form.name = "";
    form.brand = "";
    form.model = "";
    units.value = [];
    form.equipment_category_id = null;
    form.description = "";
    form.type = "";
    form.quantity = 1;
    form.serial_numbers = [""];
    form.life_span_years = null;
    form.specs = "";
    form.unit_cost = null;
    form.property_class = null;

    const url = new URL(window.location.href);
    url.searchParams.delete("edit");
    window.history.replaceState({}, "", url);
}

function startEdit(equipment) {
    editingId.value = equipment.id;
    showForm.value = true;
    noBarcodeMode.value = !equipment.barcode;
    form.inventory_number = equipment.inventory_number || "";
    form.property_number = equipment.property_number || "";
    form.property_card_number = equipment.property_card_number || "";
    form.barcode = equipment.barcode || "";
    form.name = equipment.name;
    form.brand = equipment.brand || "";
    form.model = equipment.model || "";
    units.value = (equipment.serial_numbers ?? []).map((unit) => ({
        ...unit,
        edit_property_number: unit.property_number || "",
        edit_inventory_number: unit.inventory_number || "",
        saving: false,
    }));
    form.equipment_category_id = equipment.equipment_category_id;
    form.description = equipment.description || "";
    form.type = equipment.type;
    form.quantity = equipment.quantity ?? 1;
    form.serial_numbers = syncSerialSlots(
        inStockSerials(equipment).map((row) => row.serial_number),
        equipment.quantity ?? 1,
    );
    form.life_span_years = equipment.life_span_years ?? null;
    form.specs = equipment.specs || "";
    form.unit_cost = equipment.unit_cost != null ? Number(equipment.unit_cost) : null;
    form.property_class = equipment.property_class || null;
}

function unitStatusLabel(status) {
    return UNIT_STATUS[status] || status;
}

function unitChanged(unit) {
    return (
        (unit.edit_property_number || "").trim() !== (unit.property_number || "") ||
        (unit.edit_inventory_number || "").trim() !== (unit.inventory_number || "")
    );
}

async function saveUnit(unit) {
    unit.saving = true;
    try {
        const { data } = await api.put(
            `/equipments/${editingId.value}/units/${unit.id}`,
            {
                property_number: unit.edit_property_number || null,
                inventory_number: unit.edit_inventory_number || null,
            },
        );
        unit.property_number = data.property_number;
        unit.inventory_number = data.inventory_number;
        unit.edit_property_number = data.property_number || "";
        unit.edit_inventory_number = data.inventory_number || "";
        notify.success(`Numbers saved for unit ${unit.serial_number}.`);
    } catch (error) {
        notify.error(firstError(error, "Unable to save the unit's numbers."));
    } finally {
        unit.saving = false;
    }
}

function firstError(error, fallback) {
    const errors = error.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors)[0];
        if (first) {
            return Array.isArray(first) ? first[0] : first;
        }
    }

    return error.response?.data?.message || fallback;
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

watch(
    () => form.quantity,
    (quantity) => {
        form.serial_numbers = syncSerialSlots(form.serial_numbers, quantity);
    },
);

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
    if (!form.name?.trim()) {
        notify.warn("Please enter the equipment name.");
        return;
    }

    if (!form.equipment_category_id) {
        notify.warn("Please select an equipment category.");
        return;
    }

    if (!form.type?.trim()) {
        notify.warn("Please enter the equipment type.");
        return;
    }

    if (
        !form.property_number?.trim() &&
        (editingId.value || !numbering.isAuto("equipment_property_number"))
    ) {
        notify.warn(
            editingId.value
                ? "The property number cannot be blank."
                : "Enter the property number assigned by the organization (numbering is set to manual).",
        );
        return;
    }

    if (!form.quantity || form.quantity < 1) {
        notify.warn("Please enter a quantity of at least 1.");
        return;
    }

    const serials = filledSerialNumbers(form.serial_numbers);
    if (serials.length !== Number(form.quantity)) {
        notify.warn(
            `Enter exactly ${form.quantity} serial number(s), matching the quantity.`,
        );
        return;
    }

    if (new Set(serials.map((value) => value.toLowerCase())).size !== serials.length) {
        notify.warn("Serial numbers on this equipment must be unique.");
        return;
    }

    if (
        form.life_span_years == null ||
        form.life_span_years < (editingId.value ? 0 : 1)
    ) {
        notify.warn("Please enter the equipment life span in years.");
        return;
    }

    saving.value = true;

    try {
        const payload = {
            barcode: form.barcode || null,
            name: form.name,
            brand: form.brand?.trim() || null,
            model: form.model?.trim() || null,
            property_number: form.property_number?.trim() || null,
            inventory_number: form.inventory_number?.trim() || null,
            property_card_number: form.property_card_number?.trim() || null,
            equipment_category_id: form.equipment_category_id,
            description: form.description || null,
            type: form.type,
            quantity: form.quantity,
            serial_numbers: serials,
            life_span_years: form.life_span_years,
            specs: form.specs || null,
            unit_cost: form.unit_cost ?? null,
            property_class: form.property_class || null,
        };

        if (editingId.value) {
            await api.put(`/equipments/${editingId.value}`, payload);
            notify.success("Equipment updated.");
        } else {
            const { data } = await api.post("/equipments", payload);
            notify.success(
                `Equipment saved with property no. ${data.property_number}${data.inventory_number ? `, inventory no. ${data.inventory_number}` : ""}.`,
            );
            numbering.load(true);
        }

        resetForm();
    } catch (error) {
        notify.error(firstError(error, "Unable to save equipment."));
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    numbering.load();
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
