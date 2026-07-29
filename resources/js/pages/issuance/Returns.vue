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
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
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
                        />
                    </div>

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

                    <div class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Condition / Remarks</label
                        >
                        <Textarea
                            v-model="form.reason"
                            class="w-full"
                            rows="2"
                            placeholder="Optional notes (condition, missing parts, hard-copy reference, etc.)"
                        />
                    </div>
                </div>

                <div
                    v-if="selectedEquipment"
                    class="border border-[#a8b8d4] bg-transparent p-4 text-sm text-[#00164d]"
                >
                    <p class="font-medium">{{ selectedEquipment.name }}</p>
                    <p class="mt-1 text-[#4a6490]">
                        Property No.:
                        {{ selectedEquipment.property_number || "—" }} · Current
                        available qty: {{ selectedEquipment.quantity ?? 0 }}
                        <span v-if="form.quantity">
                            → after return:
                            {{
                                (selectedEquipment.quantity ?? 0) +
                                (Number(form.quantity) || 0)
                            }}
                        </span>
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

        <UiCard
            title="Equipment Return History"
            description="Recent equipment returns recorded from hard-copy forms."
        >
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredReturns.length"
                @reset="resetFilters"
            />

            <div v-if="loadingList" class="stock-op-empty">
                <p class="text-sm text-[#4a6490]">Loading returns...</p>
            </div>

            <div v-else-if="!filteredReturns.length" class="stock-op-empty">
                <p class="mt-3 text-sm font-medium text-[#00164d]">
                    No equipment returns recorded yet
                </p>
                <p class="mt-1 text-xs text-[#4a6490]">
                    Processed returns will appear here.
                </p>
            </div>

            <div v-else class="obims-table-wrap">
                <DataTable
                    :value="filteredReturns"
                    striped-rows
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#a8b8d4]"
                >
                    <Column header="Date">
                        <template #body="{ data }">{{
                            formatDate(data.date_returned)
                        }}</template>
                    </Column>
                    <Column header="Equipment">
                        <template #body="{ data }">
                            <div>
                                <p class="font-medium text-[#00164d]">
                                    {{ data.equipment?.name || "—" }}
                                </p>
                                <p class="text-xs text-[#4a6490]">
                                    {{
                                        data.equipment?.property_number ||
                                        data.equipment?.barcode ||
                                        "—"
                                    }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column header="Department">
                        <template #body="{ data }">{{
                            data.department?.name || "—"
                        }}</template>
                    </Column>
                    <Column field="quantity" header="Qty" />
                    <Column header="Returned By">
                        <template #body="{ data }">{{
                            borrowerName(data)
                        }}</template>
                    </Column>
                    <Column header="Recorded By">
                        <template #body="{ data }">{{
                            data.returner?.name || "—"
                        }}</template>
                    </Column>
                    <Column header="Remarks">
                        <template #body="{ data }">
                            <span class="text-sm text-[#4a6490]">{{
                                data.reason || "—"
                            }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import Select from "primevue/select";
import AutoComplete from "primevue/autocomplete";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
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
const returns = ref([]);
const loading = ref(false);
const loadingList = ref(false);
const returnedByInput = ref(null);
const borrowerSuggestions = ref([]);

const form = reactive({
    equipment_id: null,
    department_id: null,
    quantity: 1,
    reason: "",
});

const equipments = computed(() =>
    equipmentRows.value.map((equipment) => ({
        id: equipment.id,
        label: `${equipment.name}${equipment.property_number ? ` · ${equipment.property_number}` : ""} · Qty: ${equipment.quantity ?? 0}`,
    })),
);

const selectedEquipment = computed(() =>
    equipmentRows.value.find((row) => row.id === form.equipment_id) ?? null,
);

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
            "equipment.barcode",
            "department.name",
            "borrower.name",
            "borrower_name",
            "returner.name",
            "reason",
        ],
    },
]);

const {
    filters,
    filteredItems: filteredReturns,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(returns, filterConfig);

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
    return row.borrower?.name || row.borrower_name || "—";
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : "—";
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
    form.equipment_id = null;
    form.department_id = null;
    form.quantity = 1;
    form.reason = "";
    returnedByInput.value = null;
    borrowerSuggestions.value = [];
}

async function submit() {
    const borrower = resolveBorrowerPayload();

    if (
        !form.equipment_id ||
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
        await api.post("/returns", {
            equipment_id: form.equipment_id,
            department_id: form.department_id,
            quantity: form.quantity,
            reason: form.reason || null,
            ...borrower,
        });
        notify.success(
            "Equipment return recorded successfully.",
            "Return completed",
        );
        resetForm();
        await Promise.all([loadLookups(), loadReturns()]);
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

async function loadReturns() {
    loadingList.value = true;
    try {
        const { data } = await api.get("/returns/list");
        returns.value = data.data ?? data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load returns.",
        );
    } finally {
        loadingList.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    await loadReturns();
});
</script>
