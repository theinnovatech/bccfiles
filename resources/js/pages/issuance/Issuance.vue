<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#00164d]">
                            Record Issuance
                        </h3>
                        <p class="mt-1 text-sm text-[#4a6490]">
                            Choose items or equipments, then enter the issuance
                            details from the hard-copy form.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="tab in issuanceTypeTabs"
                        :key="tab.key"
                        type="button"
                        class="stock-op-tab-card"
                        :class="{
                            'stock-op-tab-card-active':
                                form.issuance_type === tab.key,
                        }"
                        @click="setIssuanceType(tab.key)"
                    >
                        <span class="stock-op-tab-icon" aria-hidden="true">
                            <component :is="tab.icon" />
                        </span>
                        <span class="stock-op-tab-label">{{ tab.label }}</span>
                        <span class="stock-op-tab-desc">{{
                            tab.description
                        }}</span>
                    </button>
                </div>
            </div>

            <form
                class="min-w-0 space-y-5 p-4 sm:p-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 md:grid-cols-2">
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
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Received By
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <AutoComplete
                            v-model="receivedByInput"
                            :suggestions="receiverSuggestions"
                            optionLabel="name"
                            dropdown
                            :forceSelection="false"
                            placeholder="Select or type receiver name"
                            class="w-full"
                            inputClass="w-full"
                            :disabled="!form.department_id"
                            @complete="searchReceivers"
                        />
                        <p class="mt-1 text-xs text-[#4a6490]">
                            Pick an employee or type a name if not in the list.
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Remarks</label
                        >
                        <Textarea
                            v-model="form.remarks"
                            class="w-full"
                            rows="2"
                            placeholder="Optional notes from the hard-copy form"
                        />
                    </div>
                </div>

                <div
                    class="border border-[#a8b8d4] bg-transparent p-4"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h4 class="font-semibold text-[#00164d]">
                                {{
                                    form.issuance_type === "items"
                                        ? "Items to Issue"
                                        : "Equipments to Issue"
                                }}
                            </h4>
                            <p class="mt-0.5 text-sm text-[#4a6490]">
                                Add one or more
                                {{
                                    form.issuance_type === "items"
                                        ? "items"
                                        : "equipments"
                                }}
                                and quantities.
                            </p>
                        </div>
                        <UiButton
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addLine"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Line
                        </UiButton>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(line, index) in form.items"
                            :key="index"
                            class="grid gap-3 border border-[#a8b8d4] bg-transparent p-3 md:grid-cols-[minmax(0,1fr)_160px_auto] md:items-center"
                        >
                            <Select
                                v-if="form.issuance_type === 'items'"
                                v-model="line.item_id"
                                :options="items"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="Select item"
                                class="min-w-0 w-full"
                                filter
                            />
                            <Select
                                v-else
                                v-model="line.equipment_id"
                                :options="equipments"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="Select equipment"
                                class="min-w-0 w-full"
                                filter
                            />
                            <InputNumber
                                v-model="line.quantity"
                                :min="1"
                                placeholder="Qty"
                                class="min-w-0 w-full"
                                inputClass="w-full"
                            />
                            <UiButton
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-[#ce1126] hover:bg-[#fff1f2] hover:text-[#ce1126]"
                                :disabled="form.items.length === 1"
                                @click="removeLine(index)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                    />
                                </svg>
                            </UiButton>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <UiButton type="submit" :loading="loading">
                        Process Issuance
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
            title="Issuance History"
            description="Recent issuances recorded from hard-copy forms."
        >
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredIssuances.length"
                @reset="resetFilters"
            />

            <div class="obims-table-wrap">
                <DataTable
                    :value="filteredIssuances"
                    :loading="loadingList"
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#a8b8d4]"
                >
                    <Column field="issuance_number" header="Issuance No." />
                    <Column header="Type">
                        <template #body="{ data }">{{
                            issuanceTypeLabel(data)
                        }}</template>
                    </Column>
                    <Column header="Department">
                        <template #body="{ data }">{{
                            departmentName(data)
                        }}</template>
                    </Column>
                    <Column header="Issued By">
                        <template #body="{ data }">{{
                            data.issuer?.name
                        }}</template>
                    </Column>
                    <Column header="Received By">
                        <template #body="{ data }">{{
                            receiverName(data)
                        }}</template>
                    </Column>
                    <Column header="Issued">
                        <template #body="{ data }">
                            <span class="text-sm text-[#00164d]">{{
                                formatIssuedLines(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Total Qty">
                        <template #body="{ data }">{{
                            totalQuantity(data)
                        }}</template>
                    </Column>
                    <Column header="Date">
                        <template #body="{ data }">{{
                            formatDate(data.issued_date)
                        }}</template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, h, onMounted, reactive, ref, watch } from "vue";
import { useNotify } from "../../composables/useNotify";
import Select from "primevue/select";
import AutoComplete from "primevue/autocomplete";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import TableFilters from "../../components/TableFilters.vue";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";

const IconItems = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
                }),
            ],
        );
    },
};

const IconEquipments = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5",
                }),
            ],
        );
    },
};

const issuanceTypeTabs = [
    {
        key: "items",
        label: "Items",
        description: "Issue common supplies and consumables",
        icon: IconItems,
    },
    {
        key: "equipments",
        label: "Equipments",
        description: "Issue property-tagged equipment",
        icon: IconEquipments,
    },
];

const notify = useNotify();
const departments = ref([]);
const employees = ref([]);
const items = ref([]);
const equipments = ref([]);
const issuances = ref([]);
const loading = ref(false);
const loadingList = ref(false);

const form = reactive({
    issuance_type: "items",
    department_id: null,
    remarks: "",
    items: [emptyLine()],
});

const receivedByInput = ref(null);
const receiverSuggestions = ref([]);

function emptyLine() {
    return { item_id: null, equipment_id: null, quantity: 1 };
}

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
        placeholder: "Issuance no., department, receiver...",
        fields: [
            "issuance_number",
            "department.name",
            "receiver.department.name",
            "receiver.name",
            "received_by_name",
            "issuer.name",
        ],
    },
]);

const {
    filters,
    filteredItems: filteredIssuances,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(issuances, filterConfig);

watch(
    () => form.department_id,
    () => {
        receivedByInput.value = null;
        receiverSuggestions.value = [];
    },
);

function searchReceivers(event) {
    const query = (event.query || "").trim().toLowerCase();
    const options = employeeOptions.value;

    receiverSuggestions.value = query
        ? options.filter((employee) =>
              employee.name.toLowerCase().includes(query),
          )
        : options;
}

function setIssuanceType(type) {
    if (form.issuance_type === type) {
        return;
    }

    form.issuance_type = type;
    form.items = [emptyLine()];
}

function addLine() {
    form.items.push(emptyLine());
}

function removeLine(index) {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
}

function resetForm() {
    form.department_id = null;
    receivedByInput.value = null;
    receiverSuggestions.value = [];
    form.remarks = "";
    form.items = [emptyLine()];
}

function departmentName(issuance) {
    return (
        issuance.department?.name ||
        issuance.receiver?.department?.name ||
        "—"
    );
}

function receiverName(issuance) {
    return issuance.receiver?.name || issuance.received_by_name || "—";
}

function resolveReceiverPayload() {
    const value = receivedByInput.value;

    if (value && typeof value === "object" && value.id) {
        return {
            received_by: value.id,
            received_by_name: null,
        };
    }

    const typedName = typeof value === "string" ? value.trim() : "";

    return {
        received_by: null,
        received_by_name: typedName || null,
    };
}

function issuanceTypeLabel(issuance) {
    const hasEquipment = (issuance.details ?? []).some(
        (detail) => detail.equipment_id || detail.equipment,
    );
    const hasItem = (issuance.details ?? []).some(
        (detail) => detail.item_id || detail.item,
    );

    if (hasEquipment && !hasItem) return "Equipments";
    if (hasItem && !hasEquipment) return "Items";
    if (hasEquipment && hasItem) return "Mixed";
    return "—";
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : "";
}

function formatIssuedLines(issuance) {
    return (issuance.details ?? [])
        .map((detail) => {
            const name =
                detail.equipment?.name ?? detail.item?.item_name ?? "Line item";
            return `${name} (${detail.quantity})`;
        })
        .join(", ");
}

function totalQuantity(issuance) {
    return (issuance.details ?? []).reduce(
        (sum, detail) => sum + detail.quantity,
        0,
    );
}

function buildPayload() {
    return {
        issuance_type: form.issuance_type,
        department_id: form.department_id,
        ...resolveReceiverPayload(),
        remarks: form.remarks || null,
        items: form.items.map((line) => {
            if (form.issuance_type === "items") {
                return {
                    item_id: line.item_id,
                    quantity: line.quantity,
                };
            }

            return {
                equipment_id: line.equipment_id,
                quantity: line.quantity,
            };
        }),
    };
}

async function submit() {
    const receiver = resolveReceiverPayload();

    if (!form.department_id || (!receiver.received_by && !receiver.received_by_name)) {
        notify.warn("Please select a department and enter who received the items.");
        return;
    }

    const incomplete = form.items.some((line) => {
        if (!line.quantity || line.quantity < 1) return true;
        return form.issuance_type === "items"
            ? !line.item_id
            : !line.equipment_id;
    });

    if (incomplete) {
        notify.warn("Please complete all issuance lines before submitting.");
        return;
    }

    loading.value = true;
    try {
        await api.post("/issuances", buildPayload());
        notify.success(
            "Issuance was recorded successfully.",
            "Issuance completed",
        );
        resetForm();
        await loadIssuances();
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                error.message ||
                "Unable to process issuance.",
            "Issuance failed",
        );
    } finally {
        loading.value = false;
    }
}

async function loadLookups() {
    try {
        const [deptRes, empRes, itemRes, equipmentRes] = await Promise.all([
            api.get("/departments/list"),
            api.get("/employees/list"),
            api.get("/items/list", { params: { all: 1 } }),
            api.get("/equipments/list"),
        ]);

        departments.value = deptRes.data.data ?? deptRes.data;
        employees.value = empRes.data.data ?? empRes.data;
        items.value = (Array.isArray(itemRes.data) ? itemRes.data : itemRes.data?.data ?? []).map((item) => ({
            id: item.id,
            label: `${item.item_name}${item.barcode || item.item_number ? ` (${item.barcode || item.item_number})` : ""} · Stock: ${item.current_stock ?? 0}`,
        }));
        equipments.value = (equipmentRes.data ?? []).map((equipment) => ({
            id: equipment.id,
            label: `${equipment.name}${equipment.property_number ? ` · ${equipment.property_number}` : ""} · Qty: ${equipment.quantity ?? 0}`,
        }));
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load issuance form data.",
        );
    }
}

async function loadIssuances() {
    loadingList.value = true;
    try {
        const { data } = await api.get("/issuances/list");
        issuances.value = data.data ?? data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load issuance history.",
        );
    } finally {
        loadingList.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    await loadIssuances();
});
</script>
