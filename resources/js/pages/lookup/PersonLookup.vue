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
                    Choose a lookup type, then search for related issuance and
                    return history.
                </p>

                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="tab in lookupTabs"
                        :key="tab.key"
                        type="button"
                        class="stock-op-tab-card"
                        :class="{
                            'stock-op-tab-card-active':
                                activeLookup === tab.key,
                        }"
                        @click="setLookupType(tab.key)"
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

                <form
                    class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-start"
                    @submit.prevent="lookup"
                >
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                        >
                            {{ activeMeta.searchLabel }}
                        </label>
                        <AutoComplete
                            v-model="searchInput"
                            :suggestions="groupedSuggestions"
                            :option-group-label="'group'"
                            :option-group-children="'items'"
                            optionLabel="label"
                            dropdown
                            :forceSelection="false"
                            :placeholder="activeMeta.placeholder"
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
                                    with what you typed.
                                </div>
                            </template>
                        </AutoComplete>
                        <p class="mt-1 text-xs text-[#4a6490]">
                            {{ activeMeta.helpText }}
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

                <div
                    v-if="filteredRecentLookups.length"
                    class="mt-4 border border-[#a8b8d4] bg-transparent p-3"
                >
                    <div
                        class="mb-2 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold text-[#00164d]"
                            >
                                Recent Lookups
                            </p>
                            <p class="text-xs text-[#4a6490]">
                                Click a recent
                                {{ activeMeta.groupLabel.toLowerCase() }}
                                lookup to open it again.
                            </p>
                        </div>
                        <UiButton
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="clearRecentLookups"
                        >
                            Clear
                        </UiButton>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-2">
                        <template
                            v-for="(entry, index) in filteredRecentLookups"
                            :key="entry.key"
                        >
                            <span
                                v-if="index > 0"
                                class="recent-lookup-separator"
                                aria-hidden="true"
                                >|</span
                            >
                            <button
                                type="button"
                                class="recent-lookup-link"
                                :disabled="loading"
                                @click="openRecentLookup(entry)"
                            >
                                <span class="recent-lookup-link-label">{{
                                    entry.label
                                }}</span>
                                <span
                                    v-if="entry.subtitle"
                                    class="recent-lookup-link-sub"
                                >
                                    — {{ entry.subtitle }}
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="stock-op-content space-y-6 p-4 sm:p-6">
                <div v-if="!hasSearched" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">
                        {{ activeMeta.emptyText }}
                    </p>
                </div>

                <!-- PERSON MODE -->
                <template v-else-if="result.target?.type === 'person'">
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
                <template v-else-if="result.target?.type === 'item'">
                    <div class="border border-[#a8b8d4] bg-transparent p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
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

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
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
                <template v-else-if="result.target?.type === 'equipment'">
                    <div class="border border-[#a8b8d4] bg-transparent p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
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
                                    <span
                                        v-if="result.target?.property_number"
                                    >
                                        Property:
                                        {{ result.target.property_number }} ·
                                    </span>
                                    <span
                                        v-if="result.target?.inventory_number"
                                    >
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

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
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
import { computed, h, onMounted, ref, watch } from "vue";
import AutoComplete from "primevue/autocomplete";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiButton from "../../components/ui/UiButton.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();
const RECENT_LOOKUPS_KEY = "obims.recentLookups";
const MAX_RECENT_LOOKUPS = 8;

const IconPerson = {
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
                    d: "M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z",
                }),
            ],
        );
    },
};

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

const lookupTabs = [
    {
        key: "person",
        label: "Person",
        description: "Issuance and return history by employee",
        icon: IconPerson,
    },
    {
        key: "item",
        label: "Items",
        description: "Who received a supply item",
        icon: IconItems,
    },
    {
        key: "equipment",
        label: "Equipment",
        description: "Borrowers and return history by property",
        icon: IconEquipments,
    },
];

const lookupMeta = {
    person: {
        searchLabel: "Search by employee name or ID",
        placeholder: "Type a name or employee number...",
        helpText: "Shows items received, equipment borrowed, outstanding, and returns for that person.",
        emptyText: "Start typing above to look up a person.",
        groupLabel: "People",
    },
    item: {
        searchLabel: "Search by item name or barcode",
        placeholder: "Type an item name, barcode, or item number...",
        helpText: "Shows who received this consumable/supply item and how much was issued.",
        emptyText: "Start typing above to look up an item.",
        groupLabel: "Items",
    },
    equipment: {
        searchLabel: "Search by equipment name or property number",
        placeholder: "Type a name, property, inventory, or barcode...",
        helpText: "Shows borrowers and return history for this property-tagged equipment.",
        emptyText: "Start typing above to look up equipment.",
        groupLabel: "Equipment",
    },
};

const activeLookup = ref("person");
const searchInput = ref(null);
const rawSuggestions = ref([]);
const loading = ref(false);
const hasSearched = ref(false);
const result = ref({});
const recentLookups = ref([]);

const activeMeta = computed(
    () => lookupMeta[activeLookup.value] || lookupMeta.person,
);

const filteredRecentLookups = computed(() =>
    recentLookups.value.filter((entry) => entry.type === activeLookup.value),
);

function clearResults() {
    hasSearched.value = false;
    result.value = {};
}

function setLookupType(type) {
    if (activeLookup.value === type) return;
    activeLookup.value = type;
    searchInput.value = null;
    rawSuggestions.value = [];
    clearResults();
}

watch(searchInput, (value) => {
    if (value && typeof value === "object") return;
    if (!String(value || "").trim()) {
        clearResults();
    }
});

const groupedSuggestions = computed(() => {
    const items = rawSuggestions.value.filter(
        (suggestion) => suggestion.type === activeLookup.value,
    );
    if (!items.length) return [];
    return [{ group: activeMeta.value.groupLabel, items }];
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

function loadRecentLookups() {
    try {
        const raw = localStorage.getItem(RECENT_LOOKUPS_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        recentLookups.value = Array.isArray(parsed) ? parsed : [];
    } catch {
        recentLookups.value = [];
    }
}

function persistRecentLookups() {
    localStorage.setItem(
        RECENT_LOOKUPS_KEY,
        JSON.stringify(recentLookups.value.slice(0, MAX_RECENT_LOOKUPS)),
    );
}

function buildRecentEntry(option, target) {
    const type = target?.type || option.type;
    const name = target?.name || option.name || option.label || option.q || "";
    const employeeId = target?.employee_id ?? option.employee_id ?? null;
    const itemId = target?.item_id ?? option.item_id ?? null;
    const equipmentId = target?.equipment_id ?? option.equipment_id ?? null;

    let subtitle = option.subtitle || "";
    if (!subtitle && type === "person") {
        subtitle = [
            target?.employee_number ? `ID: ${target.employee_number}` : null,
            target?.department,
        ]
            .filter(Boolean)
            .join(" · ");
    } else if (!subtitle && type === "item") {
        subtitle = [
            target?.barcode ? `Barcode: ${target.barcode}` : null,
            target?.item_number || null,
            target?.unit || null,
        ]
            .filter(Boolean)
            .join(" · ");
    } else if (!subtitle && type === "equipment") {
        subtitle = [
            target?.property_number
                ? `Property: ${target.property_number}`
                : null,
            target?.category || null,
        ]
            .filter(Boolean)
            .join(" · ");
    }

    const keyParts = [type, employeeId, itemId, equipmentId, name.toLowerCase()];
    return {
        key: keyParts.filter((part) => part !== null && part !== undefined && part !== "").join(":"),
        type,
        label: name,
        subtitle,
        employee_id: employeeId,
        item_id: itemId,
        equipment_id: equipmentId,
        name,
        looked_up_at: new Date().toISOString(),
    };
}

function rememberLookup(option, target) {
    const entry = buildRecentEntry(option, target);
    if (!entry.label) return;

    recentLookups.value = [
        entry,
        ...recentLookups.value.filter((row) => row.key !== entry.key),
    ].slice(0, MAX_RECENT_LOOKUPS);

    persistRecentLookups();
}

function clearRecentLookups() {
    recentLookups.value = recentLookups.value.filter(
        (entry) => entry.type !== activeLookup.value,
    );
    persistRecentLookups();
}

function openRecentLookup(entry) {
    activeLookup.value = entry.type;
    searchInput.value = {
        type: entry.type,
        label: entry.label,
        name: entry.name,
        subtitle: entry.subtitle,
        employee_id: entry.employee_id,
        item_id: entry.item_id,
        equipment_id: entry.equipment_id,
    };
    lookupBy(searchInput.value);
}

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
            params: { q: query, type: activeLookup.value },
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
        if (option.type !== activeLookup.value) {
            activeLookup.value = option.type;
        }
        lookupBy(option);
    }
}

async function lookup() {
    if (searchInput.value && typeof searchInput.value === "object") {
        await lookupBy(searchInput.value);
        return;
    }

    const query = String(searchInput.value || "").trim();
    if (!query) {
        notify.error(activeMeta.value.placeholder);
        return;
    }

    if (activeLookup.value === "person") {
        await lookupBy({ type: "person", name: query });
        return;
    }

    if (activeLookup.value === "item") {
        await lookupBy({ type: "item", q: query });
        return;
    }

    await lookupBy({ type: "equipment", q: query });
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
                params: {
                    item_id: option.item_id ?? undefined,
                    q: option.q ?? option.name ?? undefined,
                },
            });
        } else if (option.type === "equipment") {
            response = await api.get("/lookups/by-equipment", {
                params: {
                    equipment_id: option.equipment_id ?? undefined,
                    q: option.q ?? option.name ?? undefined,
                },
            });
        } else {
            notify.error("Unsupported search target.");
            return;
        }

        result.value = response.data ?? {};
        hasSearched.value = true;
        if (result.value.target?.type) {
            activeLookup.value = result.value.target.type;
        }
        rememberLookup(option, result.value.target);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to complete the lookup.",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    loadRecentLookups();

    const params = new URLSearchParams(window.location.search);
    const type = params.get("type");
    if (type !== "person" && type !== "item" && type !== "equipment") {
        return;
    }

    activeLookup.value = type;

    const option = {
        type,
        label: params.get("name") || "",
        name: params.get("name") || undefined,
        employee_id: params.get("employee_id")
            ? Number(params.get("employee_id"))
            : undefined,
        item_id: params.get("item_id") ? Number(params.get("item_id")) : undefined,
        equipment_id: params.get("equipment_id")
            ? Number(params.get("equipment_id"))
            : undefined,
    };

    const hasTarget =
        (type === "person" && (option.employee_id || option.name)) ||
        (type === "item" && (option.item_id || option.name)) ||
        (type === "equipment" && (option.equipment_id || option.name));

    if (!hasTarget) {
        return;
    }

    searchInput.value = {
        ...option,
        label: option.label || option.name || `${type} lookup`,
    };
    await lookupBy(option);
});
</script>

<style scoped>
.recent-lookup-separator {
    color: #a8b8d4;
    font-size: 0.875rem;
    user-select: none;
}

.recent-lookup-link {
    display: inline;
    max-width: 100%;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    color: #001f6b;
    transition: color 0.15s ease, opacity 0.15s ease;
}

.recent-lookup-link:hover:not(:disabled) {
    color: #0033a0;
    text-decoration: underline;
}

.recent-lookup-link:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.recent-lookup-link-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: inherit;
}

.recent-lookup-link-sub {
    font-size: 0.8125rem;
    font-weight: 400;
    color: #4a6490;
}
</style>
