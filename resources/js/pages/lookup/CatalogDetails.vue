<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Catalog Details
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Search an item or equipment to view its full details.
                </p>

                <form class="mt-4" @submit.prevent="lookupSelected">
                    <label
                        class="mb-1 block text-sm font-medium text-[#00164d]"
                    >
                        Search item or equipment
                    </label>
                    <AutoComplete
                        v-model="searchInput"
                        :suggestions="groupedSuggestions"
                        :option-group-label="'group'"
                        :option-group-children="'items'"
                        optionLabel="label"
                        dropdown
                        :forceSelection="false"
                        placeholder="Type a name, barcode, stock no., or property number..."
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
                                No matching items or equipment found.
                            </div>
                        </template>
                    </AutoComplete>
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Suggestions include supply items and property-tagged
                        equipment. Select one to see the full record.
                    </p>
                </form>
            </div>

            <div class="stock-op-content p-4 sm:p-6">
                <div v-if="!hasSelected" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">
                        Start typing above and choose an item or equipment from
                        the suggestions.
                    </p>
                </div>

                <div v-else-if="loading" class="stock-op-empty">
                    <p class="text-sm text-[#4a6490]">Loading details...</p>
                </div>

                <div
                    v-else-if="selectedType === 'item' && details"
                    class="catalog-detail-panel"
                >
                    <div class="catalog-detail-header">
                        <div class="min-w-0">
                            <p class="catalog-detail-kicker">Item record</p>
                            <h4 class="catalog-detail-title">
                                {{ details.item_name || "—" }}
                            </h4>
                            <p class="catalog-detail-meta">
                                {{ details.category?.name || "No category" }}
                                <span v-if="details.brand">
                                    · {{ details.brand }}
                                </span>
                            </p>
                        </div>
                        <p class="catalog-detail-status">
                            {{ details.status || "—" }}
                        </p>
                    </div>

                    <div class="catalog-detail-form">
                        <div
                            v-for="field in itemDetailFields"
                            :key="field.label"
                            class="catalog-detail-row"
                        >
                            <span class="catalog-detail-label">{{
                                field.label
                            }}</span>
                            <span class="catalog-detail-value">{{
                                field.value
                            }}</span>
                        </div>
                        <div
                            v-if="details.description"
                            class="catalog-detail-row catalog-detail-row-block"
                        >
                            <span class="catalog-detail-label"
                                >Description</span
                            >
                            <span class="catalog-detail-value">{{
                                details.description
                            }}</span>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="selectedType === 'equipment' && details"
                    class="catalog-detail-panel"
                >
                    <div class="catalog-detail-header">
                        <div class="min-w-0">
                            <p class="catalog-detail-kicker">
                                Equipment record
                            </p>
                            <h4 class="catalog-detail-title">
                                {{ details.name || "—" }}
                            </h4>
                            <p class="catalog-detail-meta">
                                {{ details.category?.name || "No category" }}
                                <span v-if="details.type">
                                    · {{ details.type }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="catalog-detail-form">
                        <div
                            v-for="field in equipmentDetailFields"
                            :key="field.label"
                            class="catalog-detail-row"
                        >
                            <span class="catalog-detail-label">{{
                                field.label
                            }}</span>
                            <span class="catalog-detail-value">{{
                                field.value
                            }}</span>
                        </div>
                        <div
                            v-if="details.description"
                            class="catalog-detail-row catalog-detail-row-block"
                        >
                            <span class="catalog-detail-label"
                                >Description</span
                            >
                            <span class="catalog-detail-value">{{
                                details.description
                            }}</span>
                        </div>
                        <div
                            v-if="details.specs"
                            class="catalog-detail-row catalog-detail-row-block"
                        >
                            <span class="catalog-detail-label">Specs</span>
                            <span class="catalog-detail-value">{{
                                details.specs
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import AutoComplete from "primevue/autocomplete";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();

const searchInput = ref(null);
const rawSuggestions = ref([]);
const loading = ref(false);
const hasSelected = ref(false);
const selectedType = ref(null);
const details = ref(null);

const groupedSuggestions = computed(() => {
    const groups = { item: [], equipment: [] };
    for (const suggestion of rawSuggestions.value) {
        if (groups[suggestion.type]) {
            groups[suggestion.type].push(suggestion);
        }
    }

    const output = [];
    if (groups.item.length) {
        output.push({ group: "Items", items: groups.item });
    }
    if (groups.equipment.length) {
        output.push({ group: "Equipment", items: groups.equipment });
    }
    return output;
});

const itemDetailFields = computed(() => {
    const item = details.value || {};
    return [
        { label: "Stock No. / Item No.", value: item.item_number || "—" },
        { label: "Inventory No.", value: item.inventory_number || "—" },
        { label: "Barcode", value: item.barcode || "—" },
        { label: "Brand", value: item.brand || "—" },
        { label: "Category", value: item.category?.name || "—" },
        { label: "Unit", value: item.unit?.abbreviation || item.unit?.name || "—" },
        { label: "Storage Location", value: item.location?.name || "—" },
        { label: "Current Stock", value: item.current_stock ?? "—" },
        { label: "Minimum Stock", value: item.minimum_stock ?? "—" },
    ];
});

const equipmentDetailFields = computed(() => {
    const equipment = details.value || {};
    return [
        {
            label: "Property No.",
            value: equipment.property_number || "—",
        },
        {
            label: "Inventory No.",
            value: equipment.inventory_number || "—",
        },
        { label: "Barcode", value: equipment.barcode || "—" },
        { label: "Category", value: equipment.category?.name || "—" },
        { label: "Type", value: equipment.type || "—" },
        { label: "Quantity", value: equipment.quantity ?? "—" },
        {
            label: "Life Span",
            value: equipment.life_span_years
                ? `${equipment.life_span_years} year${equipment.life_span_years === 1 ? "" : "s"}`
                : "—",
        },
    ];
});

watch(searchInput, (value) => {
    if (value && typeof value === "object") return;
    if (!String(value || "").trim()) {
        clearSelection();
    }
});

function clearSelection() {
    hasSelected.value = false;
    selectedType.value = null;
    details.value = null;
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
            params: { q: query, type: "catalog" },
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
        loadDetails(option);
    }
}

async function lookupSelected() {
    if (searchInput.value && typeof searchInput.value === "object") {
        await loadDetails(searchInput.value);
        return;
    }

    notify.warn("Select an item or equipment from the suggestions.");
}

async function loadDetails(option) {
    if (!option?.type) {
        notify.warn("Select an item or equipment from the suggestions.");
        return;
    }

    loading.value = true;
    hasSelected.value = true;
    selectedType.value = option.type;
    details.value = null;

    try {
        if (option.type === "item") {
            if (!option.item_id) {
                throw new Error("Missing item id.");
            }
            const { data } = await api.get(`/items/${option.item_id}`);
            details.value = data;
        } else if (option.type === "equipment") {
            if (!option.equipment_id) {
                throw new Error("Missing equipment id.");
            }
            const { data } = await api.get(`/equipments/${option.equipment_id}`);
            details.value = data;
        } else {
            notify.error("Only items and equipment can be viewed here.");
            clearSelection();
        }
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                error.message ||
                "Unable to load catalog details.",
        );
        clearSelection();
    } finally {
        loading.value = false;
    }
}

async function applyDeepLink() {
    const params = new URLSearchParams(window.location.search);
    const type = params.get("type");
    const id = Number(params.get("id"));

    if ((type !== "item" && type !== "equipment") || !Number.isFinite(id) || id <= 0) {
        return;
    }

    const option =
        type === "item"
            ? { type: "item", item_id: id, label: `Item #${id}` }
            : { type: "equipment", equipment_id: id, label: `Equipment #${id}` };

    searchInput.value = option;
    await loadDetails(option);

    if (details.value) {
        searchInput.value = {
            ...option,
            label:
                type === "item"
                    ? details.value.item_name || option.label
                    : details.value.name || option.label,
        };
    }
}

onMounted(applyDeepLink);
</script>

<style scoped>
.catalog-detail-panel {
    max-width: 52rem;
    margin: 0 auto;
    border: 1px solid #a8b8d4;
    border-left: 4px solid #001f6b;
    background: linear-gradient(180deg, #f7f9fc 0%, #ffffff 28%);
    padding: 1.25rem 1.25rem 0.5rem;
}

@media (min-width: 640px) {
    .catalog-detail-panel {
        padding: 1.5rem 1.75rem 0.75rem;
    }
}

.catalog-detail-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem 1.25rem;
    padding-bottom: 1.1rem;
    margin-bottom: 0.25rem;
    border-bottom: 1px solid #c9d4e6;
}

.catalog-detail-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #4a6490;
}

.catalog-detail-title {
    margin-top: 0.35rem;
    font-size: 1.35rem;
    line-height: 1.25;
    font-weight: 700;
    color: #00164d;
}

.catalog-detail-meta {
    margin-top: 0.35rem;
    font-size: 0.875rem;
    color: #4a6490;
}

.catalog-detail-status {
    align-self: center;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #001f6b;
    background: #e8eef8;
    border: 1px solid #a8b8d4;
    padding: 0.35rem 0.65rem;
}

.catalog-detail-form {
    display: flex;
    flex-direction: column;
}

.catalog-detail-row {
    display: grid;
    gap: 0.2rem;
    padding: 0.95rem 0;
    border-bottom: 1px solid #e2e8f2;
}

.catalog-detail-row:last-child {
    border-bottom: 0;
}

@media (min-width: 640px) {
    .catalog-detail-row:not(.catalog-detail-row-block) {
        grid-template-columns: 13.5rem minmax(0, 1fr);
        align-items: baseline;
        gap: 1.25rem;
    }
}

.catalog-detail-label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #4a6490;
}

.catalog-detail-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #001f6b;
    white-space: pre-line;
    word-break: break-word;
}

.catalog-detail-row-block .catalog-detail-value {
    margin-top: 0.15rem;
}
</style>
