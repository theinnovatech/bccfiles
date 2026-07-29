<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Person Lookup
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Search an employee or type a name to see items they received
                    and equipment they borrowed.
                </p>

                <form
                    class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]"
                    @submit.prevent="lookup"
                >
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Employee or name</label
                        >
                        <AutoComplete
                            v-model="personInput"
                            :suggestions="personSuggestions"
                            optionLabel="label"
                            dropdown
                            :forceSelection="false"
                            placeholder="Select employee or type a name"
                            class="w-full"
                            inputClass="w-full"
                            @complete="searchPeople"
                        />
                    </div>
                    <div class="flex items-end">
                        <UiButton type="submit" :loading="loading" class="w-full md:w-auto">
                            Look up
                        </UiButton>
                    </div>
                </form>
            </div>

            <div class="stock-op-content space-y-6 p-4 sm:p-6">
                <div v-if="!hasSearched" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">
                        Enter a name above to view their issuance and borrow
                        history.
                    </p>
                </div>

                <template v-else>
                    <div
                        class="border border-[#a8b8d4] bg-transparent p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-[#4a6490]"
                                >
                                    Person
                                </p>
                                <h4 class="mt-1 text-lg font-semibold text-[#00164d]">
                                    {{ result.person?.name || "—" }}
                                </h4>
                                <p class="mt-1 text-sm text-[#4a6490]">
                                    <span v-if="result.person?.employee_number">
                                        ID: {{ result.person.employee_number }}
                                        ·
                                    </span>
                                    {{ result.person?.department || "No department" }}
                                    <span v-if="result.person?.position">
                                        · {{ result.person.position }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="border border-[#a8b8d4] bg-transparent px-4 py-3"
                            >
                                <p class="text-xs text-[#4a6490]">
                                    {{ card.label }}
                                </p>
                                <p
                                    class="mt-1 text-xl font-semibold text-[#00164d]"
                                >
                                    {{ card.value }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <section class="space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#00164d]">
                                Items received
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                Supplies issued to this person.
                            </p>
                        </div>
                        <div
                            v-if="!result.items_received?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No items received.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.items_received"
                                paginator
                                :rows="10"
                                class="rounded-md border border-[#a8b8d4]"
                            >
                                <Column
                                    field="issued_date"
                                    header="Date"
                                    style="width: 7rem"
                                />
                                <Column
                                    field="issuance_number"
                                    header="Issuance #"
                                />
                                <Column field="item_name" header="Item" />
                                <Column field="barcode" header="Barcode" />
                                <Column field="department" header="Department" />
                                <Column
                                    field="quantity"
                                    header="Qty"
                                    style="width: 5rem"
                                />
                                <Column field="unit" header="Unit" />
                            </DataTable>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#00164d]">
                                Equipment borrowed
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                Equipment issued / borrowed by this person.
                            </p>
                        </div>
                        <div
                            v-if="!result.equipment_borrowed?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No equipment borrowed.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.equipment_borrowed"
                                paginator
                                :rows="10"
                                class="rounded-md border border-[#a8b8d4]"
                            >
                                <Column
                                    field="issued_date"
                                    header="Date"
                                    style="width: 7rem"
                                />
                                <Column
                                    field="issuance_number"
                                    header="Issuance #"
                                />
                                <Column
                                    field="equipment_name"
                                    header="Equipment"
                                />
                                <Column
                                    field="property_number"
                                    header="Property #"
                                />
                                <Column field="department" header="Department" />
                                <Column
                                    field="quantity"
                                    header="Qty"
                                    style="width: 5rem"
                                />
                            </DataTable>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#00164d]">
                                Still outstanding
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                Equipment issued to them that has not been fully
                                returned yet.
                            </p>
                        </div>
                        <div
                            v-if="!result.equipment_outstanding?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No outstanding equipment.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.equipment_outstanding"
                                paginator
                                :rows="10"
                                class="rounded-md border border-[#a8b8d4]"
                            >
                                <Column
                                    field="equipment_name"
                                    header="Equipment"
                                />
                                <Column
                                    field="property_number"
                                    header="Property #"
                                />
                                <Column
                                    field="issued_quantity"
                                    header="Issued"
                                    style="width: 6rem"
                                />
                                <Column
                                    field="returned_quantity"
                                    header="Returned"
                                    style="width: 6rem"
                                />
                                <Column
                                    field="outstanding_quantity"
                                    header="Outstanding"
                                    style="width: 7rem"
                                />
                            </DataTable>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#00164d]">
                                Equipment returns
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                Equipment return records under this name.
                            </p>
                        </div>
                        <div
                            v-if="!result.equipment_returned?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No equipment returns recorded.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.equipment_returned"
                                paginator
                                :rows="10"
                                class="rounded-md border border-[#a8b8d4]"
                            >
                                <Column
                                    field="date_returned"
                                    header="Date"
                                    style="width: 7rem"
                                />
                                <Column
                                    field="equipment_name"
                                    header="Equipment"
                                />
                                <Column
                                    field="property_number"
                                    header="Property #"
                                />
                                <Column field="department" header="Department" />
                                <Column
                                    field="quantity"
                                    header="Qty"
                                    style="width: 5rem"
                                />
                                <Column field="remarks" header="Remarks">
                                    <template #body="{ data }">
                                        {{ data.remarks || "—" }}
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import AutoComplete from "primevue/autocomplete";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiButton from "../../components/ui/UiButton.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();

const employees = ref([]);
const personInput = ref(null);
const personSuggestions = ref([]);
const loading = ref(false);
const hasSearched = ref(false);
const emptyResult = () => ({
    person: null,
    summary: {},
    items_received: [],
    equipment_borrowed: [],
    equipment_returned: [],
    equipment_outstanding: [],
});
const result = ref(emptyResult());

function clearResults() {
    hasSearched.value = false;
    result.value = emptyResult();
}

watch(personInput, (value) => {
    if (value && typeof value === "object") {
        return;
    }

    if (!String(value || "").trim()) {
        clearResults();
    }
});

const summaryCards = computed(() => [
    {
        label: "Items received",
        value: result.value.summary?.items_received ?? 0,
    },
    {
        label: "Equipment borrowed",
        value: result.value.summary?.equipment_borrowed ?? 0,
    },
    {
        label: "Still outstanding",
        value: result.value.summary?.equipment_outstanding ?? 0,
    },
    {
        label: "Equipment returned",
        value: result.value.summary?.equipment_returned ?? 0,
    },
]);

function employeeLabel(employee) {
    const dept = employee.department?.name
        ? ` · ${employee.department.name}`
        : "";
    return `${employee.name}${dept}`;
}

function searchPeople(event) {
    const query = (event.query || "").toLowerCase().trim();

    personSuggestions.value = employees.value
        .filter((employee) => {
            if (!query) return true;
            return (
                employee.name.toLowerCase().includes(query) ||
                (employee.employee_number || "").toLowerCase().includes(query) ||
                (employee.department?.name || "").toLowerCase().includes(query)
            );
        })
        .slice(0, 20)
        .map((employee) => ({
            ...employee,
            label: employeeLabel(employee),
        }));
}

function resolveQuery() {
    if (personInput.value && typeof personInput.value === "object") {
        return {
            employee_id: personInput.value.id,
            name: personInput.value.name || "",
        };
    }

    const name = String(personInput.value || "").trim();
    return { name };
}

async function lookup() {
    const query = resolveQuery();

    if (!query.employee_id && !query.name) {
        notify.error("Select an employee or type a name first.");
        return;
    }

    loading.value = true;
    try {
        const { data } = await api.get("/lookups/by-person", { params: query });
        result.value = data;
        hasSearched.value = true;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to look up this person.",
        );
    } finally {
        loading.value = false;
    }
}

async function loadEmployees() {
    try {
        const { data } = await api.get("/employees/list");
        employees.value = data ?? [];
    } catch {
        employees.value = [];
    }
}

onMounted(loadEmployees);
</script>
