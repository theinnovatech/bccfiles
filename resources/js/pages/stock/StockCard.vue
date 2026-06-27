<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">Stock Card Management</h3>
                        <p class="shadcn-card-description">
                            Search an item and review its quantity movement
                            history.
                        </p>
                    </div>
                    <a href="/stock/operations" class="stock-card-back-link">
                        <i
                            class="pi pi-arrow-left text-xs"
                            aria-hidden="true"
                        ></i>
                        Back to Stock Operations
                    </a>
                </div>
            </template>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Item</label
                    >
                    <Select
                        v-model="filters.item_id"
                        :options="itemOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        filterPlaceholder="Search item or barcode..."
                        placeholder="Select an item"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >From Date</label
                    >
                    <input
                        v-model="filters.from"
                        type="date"
                        class="shadcn-input w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >To Date</label
                    >
                    <input
                        v-model="filters.to"
                        type="date"
                        class="shadcn-input w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Movement Type</label
                    >
                    <Select
                        v-model="filters.transaction_type"
                        :options="transactionTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All types"
                        class="w-full"
                    />
                </div>
                <div class="flex items-end gap-3 md:col-span-3 xl:col-span-3">
                    <UiButton :loading="loading" @click="loadMovements"
                        >View Stock Card</UiButton
                    >
                    <UiButton
                        variant="outline"
                        :disabled="loading"
                        @click="resetFilters"
                        >Reset</UiButton
                    >
                </div>
            </div>

            <div
                v-if="selectedItem"
                class="mt-5 grid gap-3 rounded-lg border border-[#eef2fa] bg-[#f8faff] p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#5b7fbf]"
                    >
                        Item
                    </p>
                    <p class="mt-1 text-sm font-medium text-[#002a7a]">
                        {{ selectedItem.item_name }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#5b7fbf]"
                    >
                        Barcode
                    </p>
                    <p class="mt-1 text-sm text-[#002a7a]">
                        {{ selectedItem.barcode }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#5b7fbf]"
                    >
                        Current Stock
                    </p>
                    <p class="mt-1 text-sm font-semibold text-[#0038a8]">
                        {{ selectedItem.current_stock }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#5b7fbf]"
                    >
                        Location
                    </p>
                    <p class="mt-1 text-sm text-[#002a7a]">
                        {{ selectedItem.location?.name || "—" }}
                    </p>
                </div>
            </div>
        </UiCard>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">Stock Card Preview</h3>
                        <p class="shadcn-card-description">
                            <span v-if="!hasLoaded"
                                >Select an item and click View Stock Card.</span
                            >
                            <span v-else-if="loading"
                                >Loading movement history...</span
                            >
                            <span v-else-if="movementRows.length"
                                >{{ movementRows.length }} movement{{
                                    movementRows.length === 1 ? "" : "s"
                                }}
                                found</span
                            >
                            <span v-else
                                >No movement records found for the selected
                                filters.</span
                            >
                        </p>
                    </div>
                    <UiButton
                        v-if="hasLoaded && !loading && movementRows.length"
                        class="w-full sm:w-auto"
                        @click="exportPdf"
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
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                            />
                        </svg>
                        Download PDF
                    </UiButton>
                </div>
            </template>

            <div v-if="!hasLoaded" class="stock-card-placeholder">
                <svg
                    class="h-12 w-12 text-[#c8d6ef]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#002a7a]">
                    No item selected
                </p>
                <p class="mt-1 text-xs text-[#5b7fbf]">
                    Choose an item above to view its stock card.
                </p>
            </div>

            <div v-else-if="loading" class="stock-card-placeholder">
                <svg
                    class="h-8 w-8 animate-spin text-[#0038a8]"
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                    />
                </svg>
                <p class="mt-3 text-sm text-[#5b7fbf]">Loading stock card...</p>
            </div>

            <template v-else>
                <div class="report-view-tabs w-full sm:w-auto">
                    <button
                        type="button"
                        class="report-view-tab"
                        :class="{
                            'report-view-tab-active': activeView === 'table',
                        }"
                        @click="activeView = 'table'"
                    >
                        Table View
                    </button>
                    <button
                        type="button"
                        class="report-view-tab"
                        :class="{
                            'report-view-tab-active': activeView === 'pdf',
                        }"
                        @click="activeView = 'pdf'"
                    >
                        PDF View
                    </button>
                </div>

                <div class="mt-4">
                    <ReportPreview
                        v-if="activeView === 'table'"
                        type="stock-card"
                        :data="movementRows"
                    />
                    <ReportPdfViewer
                        v-else
                        type="stock-card"
                        :has-data="movementRows.length > 0"
                        :params="pdfParams"
                    />
                </div>
            </template>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import Select from "primevue/select";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import ReportPreview from "../../components/ReportPreview.vue";
import ReportPdfViewer from "../../components/ReportPdfViewer.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();

const items = ref([]);
const movementRows = ref([]);
const selectedItem = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);
const activeView = ref("table");

const filters = reactive({
    item_id: null,
    from: "",
    to: "",
    transaction_type: "",
});

const transactionTypeOptions = [
    { label: "All types", value: "" },
    { label: "Stock In", value: "IN" },
    { label: "Stock Out", value: "OUT" },
    { label: "Return", value: "RETURN" },
    { label: "Adjustment", value: "ADJUSTMENT" },
];

const itemOptions = computed(() =>
    items.value.map((item) => ({
        label: `${item.item_name} (${item.barcode})`,
        value: item.id,
    })),
);

const pdfParams = computed(() => ({
    item_id: filters.item_id,
    from: filters.from || undefined,
    to: filters.to || undefined,
    transaction_type: filters.transaction_type || undefined,
}));

function resetFilters() {
    filters.item_id = null;
    filters.from = "";
    filters.to = "";
    filters.transaction_type = "";
    selectedItem.value = null;
    movementRows.value = [];
    hasLoaded.value = false;
    activeView.value = "table";
}

async function loadItems() {
    try {
        const { data } = await api.get("/items/list");
        items.value = data.data ?? data;
    } catch (error) {
        notify.error(error.response?.data?.message || "Unable to load items.");
    }
}

async function loadMovements() {
    if (!filters.item_id) {
        notify.warn("Please select an item first.");
        return;
    }

    loading.value = true;
    hasLoaded.value = true;
    activeView.value = "table";
    movementRows.value = [];
    selectedItem.value = null;

    try {
        const { data } = await api.get("/reports/stock-card", {
            params: {
                item_id: filters.item_id,
                from: filters.from || undefined,
                to: filters.to || undefined,
                transaction_type: filters.transaction_type || undefined,
            },
        });

        selectedItem.value =
            data.item ??
            items.value.find((item) => item.id === filters.item_id) ??
            null;
        movementRows.value = Array.isArray(data.data) ? data.data : [];
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load stock card.",
        );
        movementRows.value = [];
    } finally {
        loading.value = false;
    }
}

function exportPdf() {
    if (!filters.item_id) {
        return;
    }

    const params = {
        item_id: String(filters.item_id),
    };

    if (filters.from) {
        params.from = filters.from;
    }

    if (filters.to) {
        params.to = filters.to;
    }

    if (filters.transaction_type) {
        params.transaction_type = filters.transaction_type;
    }

    api.get("/reports/stock-card/pdf", {
        params,
        responseType: "blob",
    })
        .then((response) => {
            const disposition = response.headers["content-disposition"] || "";
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const fallback = selectedItem.value?.item_name
                ? `Stock-Card-${selectedItem.value.item_name.replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "")}.pdf`
                : "Stock-Card-Report.pdf";
            const filename = match?.[1] || fallback;
            const url = URL.createObjectURL(
                new Blob([response.data], { type: "application/pdf" }),
            );
            const link = document.createElement("a");

            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        })
        .catch(() => {
            notify.error("Unable to download the PDF.");
        });
}

onMounted(async () => {
    await loadItems();

    const itemId = new URLSearchParams(window.location.search).get("item_id");

    if (itemId) {
        filters.item_id = Number(itemId);
        await loadMovements();
    }
});
</script>

<style scoped>
.stock-card-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #5b7fbf;
    transition: color 0.15s ease;
}

.stock-card-back-link:hover {
    color: #0038a8;
}

.stock-card-placeholder {
    display: flex;
    min-height: 280px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px dashed #c8d6ef;
    border-radius: 0.5rem;
    background: #f0f4fb;
    text-align: center;
}

.report-view-tabs {
    display: flex;
    width: 100%;
    gap: 0.25rem;
    border-radius: 0.5rem;
    border: 1px solid #c8d6ef;
    background: #eef2fa;
    padding: 0.25rem;
}

@media (min-width: 640px) {
    .report-view-tabs {
        display: inline-flex;
        width: auto;
    }
}

.report-view-tab {
    flex: 1;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #5b7fbf;
    transition: all 0.15s ease;
    text-align: center;
}

@media (min-width: 640px) {
    .report-view-tab {
        flex: none;
        padding: 0.5rem 1rem;
    }
}

.report-view-tab:hover {
    color: #0038a8;
}

.report-view-tab-active {
    background: white;
    color: #0038a8;
    box-shadow: 0 1px 2px rgb(0 56 168 / 0.08);
}
</style>
