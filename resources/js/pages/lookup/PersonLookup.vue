<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Records Lookup
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Search a person, item, or equipment to see related issuance
                    and return history.
                </p>

                <form
                    class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-start"
                    @submit.prevent="lookup"
                >
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Search by person, item, or equipment</label
                        >
                        <AutoComplete
                            v-model="searchInput"
                            :suggestions="groupedSuggestions"
                            :option-group-label="'group'"
                            :option-group-children="'items'"
                            optionLabel="label"
                            dropdown
                            :forceSelection="false"
                            placeholder="Type a name, item, barcode, or property number..."
                            class="w-full"
                            inputClass="w-full"
                            :delay="250"
                            @complete="onComplete"
                            @item-select="onItemSelect"
                        >
                            <template #optiongroup="{ option }">
                                <div
                                    class="flex items-center gap-2 border-b border-[#e5eaf3] bg-[#f4f7fb] px-3 py-1.5"
                                >
                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-wide text-[#00164d]"
                                    >
                                        {{ option.group }}
                                    </span>
                                    <span class="text-[10px] text-[#4a6490]">
                                        ({{ option.items.length }})
                                    </span>
                                </div>
                            </template>
                            <template #option="{ option }">
                                <div class="flex flex-col py-0.5">
                                    <span class="font-medium text-[#00164d]">
                                        {{ option.label }}
                                    </span>
                                    <span
                                        v-if="option.subtitle"
                                        class="text-xs text-[#4a6490]"
                                    >
                                        {{ option.subtitle }}
                                    </span>
                                </div>
                            </template>
                            <template #empty>
                                <div class="p-3 text-sm text-[#4a6490]">
                                    No matches found. Press "Look up" to search
                                    by name.
                                </div>
                            </template>
                        </AutoComplete>
                        <p class="mt-1 text-xs text-[#4a6490]">
                            Suggestions include employees, items (by name or
                            barcode), and equipment (by name, property, or
                            inventory number).
                        </p>
                    </div>
                    <div>
                        <span
                            class="invisible mb-1 block text-sm font-medium"
                            aria-hidden="true"
                            >Look up</span
                        >
                        <UiButton
                            type="submit"
                            :loading="loading"
                            class="w-full md:w-auto"
                        >
                            Look up
                        </UiButton>
                    </div>
                </form>
            </div>

            <div class="stock-op-content space-y-6 p-4 sm:p-6">
                <div v-if="!hasSearched" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">
                        Start typing above to search for a person, item, or
                        equipment.
                    </p>
                </div>

                <!-- PERSON MODE -->
                <template
                    v-else-if="result.target?.type === 'person'"
                >
                    <div class="border border-[#a8b8d4] bg-transparent p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-[#4a6490]"
                                >
                                    Person
                                </p>
                                <h4
                                    class="mt-1 text-lg font-semibold text-[#00164d]"
                                >
                                    {{ result.target?.name || "—" }}
                                </h4>
                                <p class="mt-1 text-sm text-[#4a6490]">
                                    <span v-if="result.target?.employee_number">
                                        ID:
                                        {{ result.target.employee_number }} ·
                                    </span>
                                    {{
                                        result.target?.department ||
                                        "No department"
                                    }}
                                    <span v-if="result.target?.position">
                                        · {{ result.target.position }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div
                                v-for="card in personSummaryCards"
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
                                <Column
                                    field="department"
                                    header="Department"
                                />
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
                                <Column
                                    field="department"
                                    header="Department"
                                />
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
                                Equipment issued to them that has not been
                                fully returned yet.
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
                                <Column
                                    field="department"
                                    header="Department"
                                />
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

                <!-- ITEM MODE -->
                <template
                    v-else-if="result.target?.type === 'item'"
                >
                    <div class="border border-[#a8b8d4] bg-transparent p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-[#4a6490]"
                                >
                                    Item
                                </p>
                                <h4
                                    class="mt-1 text-lg font-semibold text-[#00164d]"
                                >
                                    {{ result.target?.name || "—" }}
                                </h4>
                                <p class="mt-1 text-sm text-[#4a6490]">
                                    <span v-if="result.target?.barcode">
                                        Barcode:
                                        {{ result.target.barcode }} ·
                                    </span>
                                    <span v-if="result.target?.category">
                                        {{ result.target.category }} ·
                                    </span>
                                    <span v-if="result.target?.unit">
                                        Unit: {{ result.target.unit }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div
                                v-for="card in itemSummaryCards"
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
                                Recipients of this item
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                People who have received this item through
                                issuances.
                            </p>
                        </div>
                        <div
                            v-if="!result.recipients?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No recipients recorded for this item.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.recipients"
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
                                    field="person_name"
                                    header="Recipient"
                                />
                                <Column
                                    field="department"
                                    header="Department"
                                />
                                <Column field="barcode" header="Barcode" />
                                <Column
                                    field="quantity"
                                    header="Qty"
                                    style="width: 5rem"
                                />
                                <Column field="unit" header="Unit" />
                            </DataTable>
                        </div>
                    </section>
                </template>

                <!-- EQUIPMENT MODE -->
                <template
                    v-else-if="result.target?.type === 'equipment'"
                >
                    <div class="border border-[#a8b8d4] bg-transparent p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-[#4a6490]"
                                >
                                    Equipment
                                </p>
                                <h4
                                    class="mt-1 text-lg font-semibold text-[#00164d]"
                                >
                                    {{ result.target?.name || "—" }}
                                </h4>
                                <p class="mt-1 text-sm text-[#4a6490]">
                                    <span v-if="result.target?.property_number">
                                        Property:
                                        {{ result.target.property_number }} ·
                                    </span>
                                    <span v-if="result.target?.inventory_number">
                                        Inventory:
                                        {{ result.target.inventory_number }} ·
                                    </span>
                                    <span v-if="result.target?.category">
                                        {{ result.target.category }}
                                    </span>
                                    <span v-if="result.target?.type_label">
                                        · {{ result.target.type_label }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div
                                v-for="card in equipmentSummaryCards"
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
                                Borrowers of this equipment
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                People who have borrowed / been issued this
                                equipment.
                            </p>
                        </div>
                        <div
                            v-if="!result.borrowers?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No borrowers recorded for this equipment.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.borrowers"
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
                                    field="person_name"
                                    header="Borrower"
                                />
                                <Column
                                    field="department"
                                    header="Department"
                                />
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
                                Return history
                            </h4>
                            <p class="text-xs text-[#4a6490]">
                                All return records for this equipment.
                            </p>
                        </div>
                        <div
                            v-if="!result.returns?.length"
                            class="stock-op-empty"
                        >
                            <p class="text-sm text-[#4a6490]">
                                No returns recorded for this equipment.
                            </p>
                        </div>
                        <div v-else class="obims-table-wrap">
                            <DataTable
                                :value="result.returns"
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
                                    field="person_name"
                                    header="Returned By"
                                />
                                <Column
                                    field="department"
                                    header="Department"
                                />
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
import { computed, ref, watch } from "vue";
import AutoComplete from "primevue/autocomplete";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiButton from "../../components/ui/UiButton.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();

const searchInput = ref(null);
const rawSuggestions = ref([]);
const loading = ref(false);
const hasSearched = ref(false);

const result = ref({});

function clearResults() {
    hasSearched.value = false;
    result.value = {};
}

watch(searchInput, (value) => {
    if (value && typeof value === "object") return;
    if (!String(value || "").trim()) {
        clearResults();
    }
});

const groupedSuggestions = computed(() => {
    const groups = { person: [], item: [], equipment: [] };
    for (const suggestion of rawSuggestions.value) {
        if (groups[suggestion.type]) {
            groups[suggestion.type].push(suggestion);
        }
    }
    const output = [];
    if (groups.person.length)
        output.push({ group: "People", items: groups.person });
    if (groups.item.length)
        output.push({ group: "Items", items: groups.item });
    if (groups.equipment.length)
        output.push({ group: "Equipment", items: groups.equipment });
    return output;
});

const personSummaryCards = computed(() => [
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

const itemSummaryCards = computed(() => [
    {
        label: "Total quantity issued",
        value: result.value.summary?.total_quantity_issued ?? 0,
    },
    {
        label: "Unique recipients",
        value: result.value.summary?.unique_recipients ?? 0,
    },
    {
        label: "Issuance records",
        value: result.value.summary?.issuance_records ?? 0,
    },
    {
        label: "Current stock",
        value: result.value.target?.current_stock ?? 0,
    },
]);

const equipmentSummaryCards = computed(() => [
    {
        label: "Total issued",
        value: result.value.summary?.total_issued ?? 0,
    },
    {
        label: "Total returned",
        value: result.value.summary?.total_returned ?? 0,
    },
    {
        label: "Outstanding",
        value: result.value.summary?.outstanding ?? 0,
    },
    {
        label: "Unique borrowers",
        value: result.value.summary?.unique_borrowers ?? 0,
    },
]);

let searchAbort = null;

async function onComplete(event) {
    const query = (event.query || "").trim();
    if (!query) {
        rawSuggestions.value = [];
        return;
    }

    if (searchAbort) searchAbort.abort();
    searchAbort = new AbortController();

    try {
        const { data } = await api.get("/lookups/suggestions", {
            params: { q: query },
            signal: searchAbort.signal,
        });
        rawSuggestions.value = Array.isArray(data) ? data : [];
    } catch (error) {
        if (error.name !== "CanceledError" && error.code !== "ERR_CANCELED") {
            rawSuggestions.value = [];
        }
    }
}

function onItemSelect(event) {
    const option = event.value;
    if (option && typeof option === "object" && option.type) {
        lookupBy(option);
    }
}

async function lookup() {
    if (searchInput.value && typeof searchInput.value === "object") {
        await lookupBy(searchInput.value);
        return;
    }

    const name = String(searchInput.value || "").trim();
    if (!name) {
        notify.error("Type a name, item, or equipment to search.");
        return;
    }

    await lookupBy({ type: "person", name });
}

async function lookupBy(option) {
    loading.value = true;
    try {
        let response;
        if (option.type === "person") {
            response = await api.get("/lookups/by-person", {
                params: {
                    employee_id: option.employee_id ?? undefined,
                    name: option.name ?? undefined,
                },
            });
        } else if (option.type === "item") {
            response = await api.get("/lookups/by-item", {
                params: { item_id: option.item_id },
            });
        } else if (option.type === "equipment") {
            response = await api.get("/lookups/by-equipment", {
                params: { equipment_id: option.equipment_id },
            });
        } else {
            notify.error("Unsupported search target.");
            return;
        }

        result.value = response.data ?? {};
        hasSearched.value = true;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to complete the lookup.",
        );
    } finally {
        loading.value = false;
    }
}
</script>
