<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Record Equipment Return
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Manually record borrowed equipment being returned from a
                    hard-copy return form.
                </p>
            </div>

            <form
                class="min-w-0 space-y-5 p-4 sm:p-6"
                @submit.prevent="submit"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                            :class="
                                !form.useCustom
                                    ? 'border-[#00164d] bg-[#00164d] text-white'
                                    : 'border-[#a8b8d4] bg-transparent text-[#00164d]'
                            "
                            @click="setUseCustom(false)"
                        >
                            From Supply Master
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                            :class="
                                form.useCustom
                                    ? 'border-[#00164d] bg-[#00164d] text-white'
                                    : 'border-[#a8b8d4] bg-transparent text-[#00164d]'
                            "
                            @click="setUseCustom(true)"
                        >
                            Custom Equipment (past data)
                        </button>
                    </div>
                    <p class="text-xs text-[#4a6490]">
                        Use "Custom Equipment" to log past returns that aren't
                        in Supply Master.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                        >
                            Reference No.
                            <span class="ml-1 text-xs font-normal text-[#4a6490]"
                                >(optional)</span
                            >
                        </label>
                        <InputText
                            v-model="form.reference_number"
                            class="w-full"
                            placeholder="e.g. RTN-2024-018"
                        />
                        <p class="mt-1 text-xs text-[#4a6490]">
                            Only fill this in if the hard-copy form has its own
                            reference number.
                        </p>
                    </div>

                    <div v-if="!form.useCustom" class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Equipment
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <Select
                            v-model="form.equipment_id"
                            :options="equipments"
                            optionLabel="label"
                            optionValue="id"
                            placeholder="Select equipment"
                            class="w-full"
                            filter
                            @update:model-value="onSupplyEquipmentSelected"
                        />
                    </div>

                    <template v-if="!form.useCustom">
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Property No.
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.property_number"
                                class="w-full"
                                placeholder="e.g. PROP-2023-0125"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Inventory No.
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.inventory_number"
                                class="w-full"
                                placeholder="e.g. INV-2023-0087"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <div class="md:col-span-2">
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                                >Equipment Name
                                <span class="text-[#ce1126]">*</span></label
                            >
                            <InputText
                                v-model="form.custom_equipment_name"
                                class="w-full"
                                placeholder="e.g. Old Dell Monitor"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Property No.
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.custom_property_number"
                                class="w-full"
                                placeholder="e.g. PROP-2023-0125"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Inventory No.
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.custom_inventory_number"
                                class="w-full"
                                placeholder="e.g. INV-2023-0087"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Type
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.custom_equipment_type"
                                class="w-full"
                                placeholder="e.g. Monitor, Chair, Laptop"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-[#00164d]"
                            >
                                Category
                                <span
                                    class="ml-1 text-xs font-normal text-[#4a6490]"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                v-model="form.custom_equipment_category"
                                class="w-full"
                                placeholder="e.g. IT Equipment, Furniture"
                            />
                        </div>
                    </template>

                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Quantity
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <InputNumber
                            v-model="form.quantity"
                            class="w-full"
                            :min="1"
                            inputClass="w-full"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Department
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <Select
                            v-model="form.department_id"
                            :options="departments"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select department"
                            class="w-full"
                            filter
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Returned By
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <AutoComplete
                            v-model="returnedByInput"
                            :suggestions="borrowerSuggestions"
                            optionLabel="name"
                            dropdown
                            :forceSelection="false"
                            placeholder="Select or type borrower name"
                            class="w-full"
                            inputClass="w-full"
                            :disabled="!form.department_id"
                            @complete="searchBorrowers"
                        />
                        <p class="mt-1 text-xs text-[#4a6490]">
                            Name of the person returning the borrowed equipment.
                            Pick from employees or type if not listed.
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                        >
                            Date Returned
                            <span class="ml-1 text-xs font-normal text-[#4a6490]"
                                >(optional)</span
                            >
                        </label>
                        <input
                            v-model="form.date_returned"
                            type="date"
                            :max="todayIso"
                            class="w-full rounded-md border border-[#a8b8d4] bg-white px-3 py-2 text-sm text-[#00164d] focus:border-[#00164d] focus:outline-none focus:ring-1 focus:ring-[#00164d]"
                        />
                        <p class="mt-1 text-xs text-[#4a6490]">
                            Set this only when recording a past return.
                            Defaults to today.
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <p class="mb-2 text-sm font-medium text-[#00164d]">
                            Return condition
                            <span class="text-[#ce1126]">*</span>
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="option in conditionOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-md border px-3 py-2.5 text-left transition"
                                :class="
                                    form.condition === option.value
                                        ? 'border-[#00164d] bg-[#00164d] text-white'
                                        : 'border-[#a8b8d4] bg-transparent text-[#00164d]'
                                "
                                @click="form.condition = option.value"
                            >
                                <span class="block text-sm font-medium">{{
                                    option.label
                                }}</span>
                                <span
                                    class="mt-0.5 block text-xs"
                                    :class="
                                        form.condition === option.value
                                            ? 'text-white/80'
                                            : 'text-[#4a6490]'
                                    "
                                >
                                    {{ option.description }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                        >
                            Condition / Remarks
                            <span
                                class="ml-1 text-xs font-normal text-[#4a6490]"
                                >(optional)</span
                            >
                        </label>
                        <Textarea
                            v-model="form.reason"
                            class="w-full"
                            rows="2"
                            placeholder="Optional notes (missing parts, hard-copy reference, etc.)"
                        />
                    </div>
                </div>

                <div
                    v-if="!form.useCustom && selectedEquipment"
                    class="border border-[#a8b8d4] bg-transparent p-4 text-sm text-[#00164d]"
                >
                    <p class="font-medium">{{ selectedEquipment.name }}</p>
                    <p class="mt-1 text-[#4a6490]">
                        Property No.:
                        {{ selectedEquipment.property_number || "—" }}
                        · Inventory No.:
                        {{ selectedEquipment.inventory_number || "—" }}
                        · Current available qty:
                        {{ selectedEquipment.quantity ?? 0 }}
                    </p>
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Life span:
                        {{
                            selectedEquipment.life_span_years
                                ? `${selectedEquipment.life_span_years} year${selectedEquipment.life_span_years === 1 ? "" : "s"}`
                                : "—"
                        }}
                        · Date acquired:
                        {{ formatDateOnly(selectedEquipment.date_acquired) }}
                    </p>
                    <div class="mt-3 border-t border-[#d7e0ef] pt-3">
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                        >
                            Specs
                        </p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-[#00164d]">
                            {{
                                selectedEquipment.specs ||
                                "No specs recorded for this equipment."
                            }}
                        </p>
                    </div>
                    <p
                        v-if="selectedEquipmentReachedLifespan"
                        class="mt-3 text-xs font-medium text-[#ce1126]"
                    >
                        This equipment has reached its life span. Even if
                        returned well, it will not be added back for re-issue.
                    </p>
                    <p
                        v-else-if="form.condition === 'good'"
                        class="mt-3 text-xs text-[#4a6490]"
                    >
                        Returned well with no damage will be added back to
                        Supply Master so it can be re-issued.
                    </p>
                    <p v-else class="mt-3 text-xs text-[#4a6490]">
                        Damaged returns are logged only and are not added back
                        for re-issue.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <UiButton type="submit" :loading="loading">
                        Process Return
                    </UiButton>
                    <UiButton
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="resetForm"
                    >
                        Clear Form
                    </UiButton>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredReturnedEquipments.length"
                @reset="resetFilters"
            />

            <UiCard
                v-for="table in returnTables"
                :key="table.key"
                :title="table.title"
                :description="table.description"
            >
                <div v-if="loadingReturned" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">
                        Loading returned equipments...
                    </p>
                </div>

                <div v-else-if="!table.rows.length" class="stock-op-empty">
                    <p class="mt-3 text-sm font-medium text-[#00164d]">
                        {{ table.emptyTitle }}
                    </p>
                    <p class="mt-1 text-xs text-[#4a6490]">
                        {{ table.emptyText }}
                    </p>
                </div>

                <div v-else class="obims-table-wrap">
                    <DataTable
                        :value="table.rows"
                        striped-rows
                        paginator
                        :rows="10"
                        class="rounded-md border border-[#a8b8d4]"
                    >
                        <Column header="Ref. No.">
                            <template #body="{ data }">{{
                                data.reference_number || "—"
                            }}</template>
                        </Column>
                        <Column header="Property No.">
                            <template #body="{ data }">{{
                                propertyNumber(data)
                            }}</template>
                        </Column>
                        <Column header="Inventory No.">
                            <template #body="{ data }">{{
                                inventoryNumber(data)
                            }}</template>
                        </Column>
                        <Column header="Equipment">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-[#00164d]">
                                        {{ equipmentName(data) }}
                                        <span
                                            v-if="!data.equipment_id"
                                            class="ml-1 rounded bg-[#f2c94c] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#00164d]"
                                            >Custom</span
                                        >
                                    </p>
                                    <p class="text-xs text-[#4a6490]">
                                        {{ equipmentCategory(data) }}
                                    </p>
                                </div>
                            </template>
                        </Column>
                        <Column header="Type">
                            <template #body="{ data }">{{
                                equipmentType(data)
                            }}</template>
                        </Column>
                        <Column header="Qty">
                            <template #body="{ data }">{{
                                data.quantity ?? 0
                            }}</template>
                        </Column>
                        <Column header="Status">
                            <template #body="{ data }">
                                <span
                                    class="text-sm"
                                    :class="
                                        data.restocked
                                            ? 'font-medium text-[#00164d]'
                                            : 'text-[#4a6490]'
                                    "
                                >
                                    {{ returnStatus(data) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Department">
                            <template #body="{ data }">{{
                                data.department?.name || "—"
                            }}</template>
                        </Column>
                        <Column header="Returned By">
                            <template #body="{ data }">{{
                                borrowerName(data)
                            }}</template>
                        </Column>
                        <Column header="Date Returned">
                            <template #body="{ data }">{{
                                formatDate(data.date_returned)
                            }}</template>
                        </Column>
                        <Column header="Remarks">
                            <template #body="{ data }">
                                <span class="text-sm text-[#4a6490]">{{
                                    data.reason || "—"
                                }}</span>
                            </template>
                        </Column>
                        <Column header="Actions" style="width: 6rem">
                            <template #body="{ data }">
                                <UiButton
                                    variant="ghost"
                                    size="icon"
                                    title="View return details"
                                    @click="viewReturn(data)"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                </UiButton>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </UiCard>
        </div>

        <Dialog
            v-model:visible="viewDialogVisible"
            modal
            header="Returned Equipment Details"
            :style="{ width: '640px' }"
        >
            <div v-if="selectedReturn" class="space-y-4 pt-2 text-sm text-[#00164d]">
                <div
                    class="border border-[#a8b8d4] bg-[#f4f7fb] p-4"
                >
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Equipment
                        </p>
                        <span
                            v-if="!selectedReturn.equipment_id"
                            class="rounded bg-[#f2c94c] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#00164d]"
                            >Custom / Past Data</span
                        >
                    </div>
                    <p class="mt-1 text-base font-semibold">
                        {{ equipmentName(selectedReturn) }}
                    </p>
                    <p class="mt-0.5 text-xs text-[#4a6490]">
                        {{ equipmentCategory(selectedReturn) }}
                        <span v-if="equipmentType(selectedReturn) !== '—'">
                            · {{ equipmentType(selectedReturn) }}
                        </span>
                    </p>
                    <p class="mt-2 text-xs font-medium text-[#00164d]">
                        {{ returnStatus(selectedReturn) }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Reference No.
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedReturn.reference_number || "—" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Property No.
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ propertyNumber(selectedReturn) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Inventory No.
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ inventoryNumber(selectedReturn) }}
                        </p>
                    </div>
                    <div v-if="selectedReturn.equipment?.barcode">
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Barcode
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedReturn.equipment?.barcode || "—" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Quantity Returned
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedReturn.quantity ?? 0 }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Department
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedReturn.department?.name || "—" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Returned By
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ borrowerName(selectedReturn) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Recorded By
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedReturn.returner?.name || "—" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Condition
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{
                                selectedReturn.condition_label ||
                                returnStatus(selectedReturn)
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Date Returned
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ formatDate(selectedReturn.date_returned) }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                        Condition / Remarks
                    </p>
                    <p
                        class="mt-1 whitespace-pre-line border border-[#a8b8d4] bg-transparent p-3 text-sm text-[#4a6490]"
                    >
                        {{ selectedReturn.reason || "No remarks provided." }}
                    </p>
                </div>

                <div
                    v-if="selectedReturn.equipment?.specs || selectedReturn.equipment?.description"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div v-if="selectedReturn.equipment?.description">
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Description
                        </p>
                        <p class="mt-0.5 text-sm text-[#4a6490]">
                            {{ selectedReturn.equipment.description }}
                        </p>
                    </div>
                    <div v-if="selectedReturn.equipment?.specs">
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Specs
                        </p>
                        <p class="mt-0.5 text-sm text-[#4a6490]">
                            {{ selectedReturn.equipment.specs }}
                        </p>
                    </div>
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="viewDialogVisible = false"
                    >Close</UiButton
                >
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import Select from "primevue/select";
import AutoComplete from "primevue/autocomplete";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import UiButton from "../../components/ui/UiButton.vue";
import UiCard from "../../components/ui/UiCard.vue";
import TableFilters from "../../components/TableFilters.vue";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";

const notify = useNotify();
const departments = ref([]);
const employees = ref([]);
const equipmentRows = ref([]);
const returnedEquipments = ref([]);
const loading = ref(false);
const loadingReturned = ref(false);
const returnedByInput = ref(null);
const borrowerSuggestions = ref([]);
const viewDialogVisible = ref(false);
const selectedReturn = ref(null);

const form = reactive({
    useCustom: false,
    reference_number: "",
    equipment_id: null,
    custom_equipment_name: "",
    custom_property_number: "",
    custom_inventory_number: "",
    custom_equipment_type: "",
    custom_equipment_category: "",
    department_id: null,
    property_number: "",
    inventory_number: "",
    quantity: 1,
    reason: "",
    condition: "good",
    date_returned: "",
});

const conditionOptions = [
    {
        value: "good",
        label: "Returned well",
        description: "No damage. Can be re-issued if still within life span.",
    },
    {
        value: "damaged",
        label: "Returned with damage",
        description: "Logged only. This equipment will not be re-issued.",
    },
];

const equipments = computed(() =>
    equipmentRows.value.map((equipment) => ({
        id: equipment.id,
        label: `${equipment.name}${equipment.property_number ? ` · ${equipment.property_number}` : ""} · Qty: ${equipment.quantity ?? 0}`,
    })),
);

const selectedEquipment = computed(() =>
    equipmentRows.value.find((row) => row.id === form.equipment_id) ?? null,
);

const selectedEquipmentReachedLifespan = computed(() =>
    hasReachedLifespan(selectedEquipment.value, form.date_returned || undefined),
);

const todayIso = computed(() => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
});

const employeeOptions = computed(() => {
    if (!form.department_id) {
        return [];
    }

    return employees.value.filter(
        (employee) => employee.department_id === form.department_id,
    );
});

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Equipment, property no., borrower, department...",
        fields: [
            "equipment.name",
            "equipment.property_number",
            "equipment.inventory_number",
            "equipment.barcode",
            "equipment.type",
            "equipment.category.name",
            "custom_equipment_name",
            "custom_property_number",
            "custom_inventory_number",
            "custom_equipment_type",
            "custom_equipment_category",
            "reference_number",
            "department.name",
            "borrower.name",
            "borrower_name",
            "returner.name",
            "reason",
            "condition",
        ],
    },
]);

const {
    filters,
    filteredItems: filteredReturnedEquipments,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(returnedEquipments, filterConfig);

const goodReturnedEquipments = computed(() =>
    filteredReturnedEquipments.value.filter(
        (row) => returnCondition(row) !== "damaged",
    ),
);

const damagedReturnedEquipments = computed(() =>
    filteredReturnedEquipments.value.filter(
        (row) => returnCondition(row) === "damaged",
    ),
);

const returnTables = computed(() => [
    {
        key: "good",
        title: "Equipments Returned Well",
        description:
            "No-damage returns. Units still within life span are added back to Supply Master so they can be re-issued.",
        rows: goodReturnedEquipments.value,
        emptyTitle: "No well-returned equipments yet",
        emptyText: "Returns marked as well / no damage will appear here.",
    },
    {
        key: "damaged",
        title: "Equipments Returned with Damage",
        description:
            "Damaged returns are logged here and are not added back for re-issue.",
        rows: damagedReturnedEquipments.value,
        emptyTitle: "No damaged returns yet",
        emptyText: "Returns marked as damaged will appear here.",
    },
]);

watch(
    () => form.department_id,
    () => {
        returnedByInput.value = null;
        borrowerSuggestions.value = [];
    },
);

function searchBorrowers(event) {
    const query = (event.query || "").trim().toLowerCase();
    const options = employeeOptions.value;

    borrowerSuggestions.value = query
        ? options.filter((employee) =>
              employee.name.toLowerCase().includes(query),
          )
        : options;
}

function borrowerName(row) {
    return row?.borrower?.name || row?.borrower_name || "—";
}

function equipmentName(row) {
    return row?.equipment?.name || row?.custom_equipment_name || "—";
}

function equipmentCategory(row) {
    return (
        row?.equipment?.category?.name ||
        row?.custom_equipment_category ||
        "—"
    );
}

function equipmentType(row) {
    return row?.equipment?.type || row?.custom_equipment_type || "—";
}

function propertyNumber(row) {
    return (
        row?.equipment?.property_number ||
        row?.custom_property_number ||
        "—"
    );
}

function inventoryNumber(row) {
    return (
        row?.equipment?.inventory_number ||
        row?.custom_inventory_number ||
        "—"
    );
}

function returnCondition(row) {
    return row?.condition === "damaged" ? "damaged" : "good";
}

function returnStatus(row) {
    if (returnCondition(row) === "damaged") {
        return "Damaged — not re-issuable";
    }

    if (row?.restocked) {
        return "Restocked — can re-issue";
    }

    if (row?.lifespan_reached) {
        return "Lifespan reached — not re-issuable";
    }

    if (!row?.equipment_id) {
        return "Custom — not in Supply Master";
    }

    return "Logged only";
}

function hasReachedLifespan(equipment, asOf) {
    if (!equipment?.life_span_years || !equipment?.date_acquired) {
        return false;
    }

    const acquired = new Date(`${String(equipment.date_acquired).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(acquired.getTime())) {
        return false;
    }

    const limit = new Date(acquired);
    limit.setFullYear(limit.getFullYear() + Number(equipment.life_span_years));

    const check = asOf
        ? new Date(`${String(asOf).slice(0, 10)}T00:00:00`)
        : new Date();
    check.setHours(0, 0, 0, 0);
    limit.setHours(0, 0, 0, 0);

    return check >= limit;
}

function formatDateOnly(value) {
    if (!value) {
        return "—";
    }

    const raw = String(value).slice(0, 10);
    const [year, month, day] = raw.split("-");
    if (!year || !month || !day) {
        return raw;
    }

    return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString();
}

function viewReturn(row) {
    selectedReturn.value = row;
    viewDialogVisible.value = true;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : "—";
}

function setUseCustom(useCustom) {
    form.useCustom = useCustom;
    if (useCustom) {
        form.equipment_id = null;
        form.property_number = "";
        form.inventory_number = "";
    } else {
        form.custom_equipment_name = "";
        form.custom_property_number = "";
        form.custom_inventory_number = "";
        form.custom_equipment_type = "";
        form.custom_equipment_category = "";
    }
}

function onSupplyEquipmentSelected(equipmentId) {
    const equipment = equipmentRows.value.find((row) => row.id === equipmentId);
    form.property_number = equipment?.property_number || "";
    form.inventory_number = equipment?.inventory_number || "";
}

function resolveBorrowerPayload() {
    const value = returnedByInput.value;

    if (value && typeof value === "object" && value.id) {
        return {
            borrower_employee_id: value.id,
            borrower_name: null,
        };
    }

    const typedName = typeof value === "string" ? value.trim() : "";

    return {
        borrower_employee_id: null,
        borrower_name: typedName || null,
    };
}

function resetForm() {
    form.useCustom = false;
    form.reference_number = "";
    form.equipment_id = null;
    form.property_number = "";
    form.inventory_number = "";
    form.custom_equipment_name = "";
    form.custom_property_number = "";
    form.custom_inventory_number = "";
    form.custom_equipment_type = "";
    form.custom_equipment_category = "";
    form.department_id = null;
    form.quantity = 1;
    form.reason = "";
    form.condition = "good";
    form.date_returned = "";
    returnedByInput.value = null;
    borrowerSuggestions.value = [];
}

function formatDateForPayload(value) {
    if (!value) return null;
    if (typeof value === "string") {
        return /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value} 00:00:00` : value;
    }
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const pad = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

async function submit() {
    const borrower = resolveBorrowerPayload();
    const customName = (form.custom_equipment_name || "").trim();
    const hasEquipment = form.useCustom
        ? customName.length > 0
        : !!form.equipment_id;

    if (
        !hasEquipment ||
        !form.department_id ||
        !form.quantity ||
        form.quantity < 1 ||
        (!borrower.borrower_employee_id && !borrower.borrower_name)
    ) {
        notify.warn(
            "Please complete equipment, quantity, department, and returned by.",
        );
        return;
    }

    loading.value = true;
    try {
        const payload = form.useCustom
            ? {
                  equipment_id: null,
                  custom_equipment_name: customName,
                  custom_property_number:
                      (form.custom_property_number || "").trim() || null,
                  custom_inventory_number:
                      (form.custom_inventory_number || "").trim() || null,
                  custom_equipment_type:
                      (form.custom_equipment_type || "").trim() || null,
                  custom_equipment_category:
                      (form.custom_equipment_category || "").trim() || null,
              }
            : {
                  equipment_id: form.equipment_id,
                  property_number: (form.property_number || "").trim() || null,
                  inventory_number: (form.inventory_number || "").trim() || null,
              };

        await api.post("/returns", {
            ...payload,
            reference_number:
                (form.reference_number || "").trim() || null,
            department_id: form.department_id,
            quantity: form.quantity,
            reason: form.reason || null,
            condition: form.condition,
            date_returned: formatDateForPayload(form.date_returned),
            ...borrower,
        });
        notify.success(
            form.condition === "good" && !selectedEquipmentReachedLifespan.value && !form.useCustom
                ? "Return recorded. Equipment was added back and can be re-issued."
                : "Equipment return recorded successfully.",
            "Return completed",
        );
        resetForm();
        await Promise.all([loadLookups(), loadReturnedEquipments()]);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to process return.",
        );
    } finally {
        loading.value = false;
    }
}

async function loadLookups() {
    try {
        const [deptRes, empRes, equipmentRes] = await Promise.all([
            api.get("/departments/list"),
            api.get("/employees/list"),
            api.get("/equipments/list"),
        ]);

        departments.value = deptRes.data.data ?? deptRes.data;
        employees.value = empRes.data.data ?? empRes.data;
        equipmentRows.value = equipmentRes.data ?? [];
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                "Unable to load return form options.",
        );
    }
}

async function loadReturnedEquipments() {
    loadingReturned.value = true;
    try {
        const { data } = await api.get("/returns/returned-equipments");
        returnedEquipments.value = data ?? [];
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                "Unable to load returned equipments.",
        );
    } finally {
        loadingReturned.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    await loadReturnedEquipments();
});
</script>
