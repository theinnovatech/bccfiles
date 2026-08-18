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
                    class="catalog-result"
                >
                    <div class="catalog-result-hero">
                        <div class="min-w-0">
                            <p class="catalog-result-kicker">Item record</p>
                            <h4 class="catalog-result-title">
                                {{ details.item_name || "—" }}
                            </h4>
                            <p class="catalog-result-meta">
                                {{ details.category?.name || "No category" }}
                                <span v-if="details.brand">
                                    · {{ details.brand }}
                                </span>
                            </p>
                        </div>
                        <span class="catalog-result-badge">
                            {{ details.status || "On file" }}
                        </span>
                    </div>

                    <div class="catalog-result-grid">
                        <div
                            v-for="field in itemDetailFields"
                            :key="field.label"
                            class="catalog-result-tile"
                        >
                            <p class="catalog-result-tile-label">
                                {{ field.label }}
                            </p>
                            <p class="catalog-result-tile-value">
                                {{ field.value }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="details.description"
                        class="catalog-result-notes"
                    >
                        <p class="catalog-result-tile-label">Description</p>
                        <p class="catalog-result-notes-text">
                            {{ details.description }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="selectedType === 'equipment' && details"
                    class="catalog-result"
                >
                    <div class="catalog-result-hero">
                        <div class="min-w-0">
                            <p class="catalog-result-kicker">
                                Equipment record
                            </p>
                            <h4 class="catalog-result-title">
                                {{ details.name || "—" }}
                            </h4>
                            <p class="catalog-result-meta">
                                {{ details.category?.name || "No category" }}
                                <span v-if="details.type">
                                    · {{ details.type }}
                                </span>
                            </p>
                        </div>
                        <span
                            class="catalog-origin-badge"
                            :class="
                                isReturnedEquipment
                                    ? 'catalog-origin-returned'
                                    : 'catalog-origin-fresh'
                            "
                        >
                            {{ equipmentOriginWord }}
                        </span>
                    </div>

                    <div
                        class="catalog-origin-banner"
                        :class="
                            isReturnedEquipment
                                ? 'catalog-origin-banner-returned'
                                : 'catalog-origin-banner-fresh'
                        "
                    >
                        <span class="catalog-origin-banner-icon" aria-hidden="true">
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                                />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="catalog-origin-banner-title">
                                {{ equipmentOriginWord }}
                            </p>
                            <p class="catalog-origin-banner-text">
                                {{ equipmentOriginHelp }}
                            </p>
                        </div>
                    </div>

                    <div class="catalog-result-grid">
                        <div
                            v-for="field in equipmentDetailFields"
                            :key="field.label"
                            class="catalog-result-tile"
                        >
                            <p class="catalog-result-tile-label">
                                {{ field.label }}
                            </p>
                            <p class="catalog-result-tile-value">
                                {{ field.value }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="details.description"
                        class="catalog-result-notes"
                    >
                        <p class="catalog-result-tile-label">Description</p>
                        <p class="catalog-result-notes-text">
                            {{ details.description }}
                        </p>
                    </div>
                    <div v-if="details.specs" class="catalog-result-notes">
                        <p class="catalog-result-tile-label">Specs</p>
                        <p class="catalog-result-notes-text">
                            {{ details.specs }}
                        </p>
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
import {
    formatEquipmentLifeSpan,
    equipmentOriginLabel,
} from "../../utils/equipmentLifeSpan";

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

const isReturnedEquipment = computed(() => {
    const equipment = details.value || {};
    return (
        equipment.origin === "returned" || Boolean(equipment.source_return_id)
    );
});

const equipmentOriginWord = computed(() =>
    equipmentOriginLabel(details.value),
);

const equipmentOriginHelp = computed(() =>
    isReturnedEquipment.value
        ? "Used. This equipment was returned before and is on hand again."
        : "New. This equipment is first-time stock from supply.",
);

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
            label: "Date Acquired",
            value: formatDateOnly(equipment.date_acquired),
        },
        {
            label: "Life Span",
            value: formatEquipmentLifeSpan(equipment),
        },
    ];
});

function formatDateOnly(value) {
    if (!value) {
        return "—";
    }

    const raw = String(value).slice(0, 10);
    const [year, month, day] = raw.split("-");
    if (!year || !month || !day) {
        return raw;
    }

    return new Date(
        Number(year),
        Number(month) - 1,
        Number(day),
    ).toLocaleDateString();
}

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
.catalog-result {
    max-width: 56rem;
    margin: 0 auto;
    overflow: hidden;
    border: 1px solid #a8b8d4;
    background: #fff;
}

.catalog-result-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem 1.25rem;
    padding: 1.25rem 1.25rem 1.1rem;
    background: linear-gradient(180deg, #f4f7fb 0%, #ffffff 100%);
    border-bottom: 1px solid #d7e0ef;
}

@media (min-width: 640px) {
    .catalog-result-hero {
        padding: 1.5rem 1.75rem 1.25rem;
    }
}

.catalog-result-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #4a6490;
}

.catalog-result-title {
    margin-top: 0.3rem;
    font-size: 1.4rem;
    line-height: 1.25;
    font-weight: 700;
    color: #00164d;
}

.catalog-result-meta {
    margin-top: 0.3rem;
    font-size: 0.875rem;
    color: #4a6490;
}

.catalog-result-badge,
.catalog-origin-badge {
    align-self: center;
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border: 1px solid #a8b8d4;
}

.catalog-result-badge {
    color: #001f6b;
    background: #e8eef8;
}

.catalog-origin-fresh {
    color: #0f5132;
    background: #e8f6ee;
    border-color: #9dceb0;
}

.catalog-origin-returned {
    color: #8a5a00;
    background: #fff8e8;
    border-color: #e0c48a;
}

.catalog-origin-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: 1rem 1.25rem 0;
    padding: 0.9rem 1rem;
    border: 1px solid #a8b8d4;
}

@media (min-width: 640px) {
    .catalog-origin-banner {
        margin: 1.15rem 1.75rem 0;
    }
}

.catalog-origin-banner-fresh {
    background: #f3faf6;
    border-color: #9dceb0;
}

.catalog-origin-banner-returned {
    background: #fffaf0;
    border-color: #e0c48a;
}

.catalog-origin-banner-icon {
    display: flex;
    height: 2rem;
    width: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid currentColor;
    background: #fff;
}

.catalog-origin-banner-fresh .catalog-origin-banner-icon {
    color: #0f5132;
}

.catalog-origin-banner-returned .catalog-origin-banner-icon {
    color: #8a5a00;
}

.catalog-origin-banner-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #00164d;
}

.catalog-origin-banner-text {
    margin-top: 0.15rem;
    font-size: 0.8125rem;
    line-height: 1.4;
    color: #4a6490;
}

.catalog-result-grid {
    display: grid;
    gap: 0.75rem;
    padding: 1.15rem 1.25rem 1.25rem;
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .catalog-result-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 1.25rem 1.75rem 1.5rem;
    }
}

@media (min-width: 900px) {
    .catalog-result-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.catalog-result-tile {
    min-width: 0;
    border: 1px solid #d7e0ef;
    background: #f8fafc;
    padding: 0.85rem 0.9rem;
}

.catalog-result-tile-label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #4a6490;
}

.catalog-result-tile-value {
    margin-top: 0.3rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #00164d;
    word-break: break-word;
}

.catalog-result-notes {
    margin: 0 1.25rem 1.15rem;
    border: 1px solid #d7e0ef;
    background: #fff;
    padding: 0.9rem 1rem;
}

@media (min-width: 640px) {
    .catalog-result-notes {
        margin: 0 1.75rem 1.5rem;
    }
}

.catalog-result-notes-text {
    margin-top: 0.35rem;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #00164d;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
