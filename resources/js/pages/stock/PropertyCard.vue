<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">
                            Property Card Management
                        </h3>
                        <p class="shadcn-card-description">
                            Search an equipment and review its issuance history.
                        </p>
                    </div>
                    <a href="/stock/operations" class="property-card-back-link">
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
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Equipment</label
                    >
                    <Select
                        v-model="filters.equipment_id"
                        :options="equipmentOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        filterPlaceholder="Search equipment or property no...."
                        placeholder="Select equipment"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >From Date</label
                    >
                    <input
                        v-model="filters.from"
                        type="date"
                        class="shadcn-input w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >To Date</label
                    >
                    <input
                        v-model="filters.to"
                        type="date"
                        class="shadcn-input w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Movement Type</label
                    >
                    <Select
                        v-model="filters.movement_type"
                        :options="movementTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All types"
                        class="w-full"
                    />
                </div>
                <div class="flex items-end gap-3 md:col-span-3 xl:col-span-3">
                    <UiButton :loading="loading" @click="loadMovements"
                        >View Property Card</UiButton
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
                v-if="selectedEquipment"
                class="mt-5 grid gap-3 border border-[#a8b8d4] bg-transparent p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]"
                    >
                        Equipment
                    </p>
                    <p class="mt-1 text-sm font-medium text-[#00164d]">
                        {{ selectedEquipment.name }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]"
                    >
                        Property No.
                    </p>
                    <p class="mt-1 text-sm text-[#00164d]">
                        {{ selectedEquipment.property_number || "—" }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]"
                    >
                        Available Qty
                    </p>
                    <p class="mt-1 text-sm font-semibold text-[#001f6b]">
                        {{ selectedEquipment.quantity }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]"
                    >
                        Category
                    </p>
                    <p class="mt-1 text-sm text-[#00164d]">
                        {{ selectedEquipment.category?.name || "—" }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]"
                    >
                        Life Span
                    </p>
                    <p class="mt-1 text-sm text-[#00164d]">
                        {{
                            selectedEquipment.life_span_years
                                ? `${selectedEquipment.life_span_years} yr${selectedEquipment.life_span_years === 1 ? "" : "s"}`
                                : "—"
                        }}
                    </p>
                </div>
            </div>
        </UiCard>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">Property Card Preview</h3>
                        <p class="shadcn-card-description">
                            <span v-if="!hasLoaded"
                                >Select an equipment and click View Property
                                Card.</span
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

            <div v-if="!hasLoaded" class="property-card-placeholder">
                <svg
                    class="h-12 w-12 text-[#a8b8d4]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-4.162a2.25 2.25 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75V5.25A2.25 2.25 0 0011.25 7.5h1.5A2.25 2.25 0 0015 5.25V3.75M9 3.75h6"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#00164d]">
                    No equipment selected
                </p>
                <p class="mt-1 text-xs text-[#4a6490]">
                    Choose an equipment above to view its property card.
                </p>
            </div>

            <div v-else-if="loading" class="property-card-placeholder">
                <svg
                    class="h-8 w-8 animate-spin text-[#001f6b]"
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
                <p class="mt-3 text-sm text-[#4a6490]">
                    Loading property card...
                </p>
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
                        type="property-card"
                        :data="movementRows"
                    />
                    <ReportPdfViewer
                        v-else
                        type="property-card"
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

const equipments = ref([]);
const movementRows = ref([]);
const selectedEquipment = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);
const activeView = ref("table");

const filters = reactive({
    equipment_id: null,
    from: "",
    to: "",
    movement_type: "",
});

const movementTypeOptions = [
    { label: "All types", value: "" },
    { label: "Receipt", value: "receipt" },
    { label: "Issue / Transfer", value: "issue" },
];

const equipmentOptions = computed(() =>
    equipments.value.map((equipment) => ({
        label: equipment.property_number
            ? `${equipment.name} (${equipment.property_number})`
            : equipment.name,
        value: equipment.id,
    })),
);

const pdfParams = computed(() => ({
    equipment_id: filters.equipment_id,
    from: filters.from || undefined,
    to: filters.to || undefined,
    movement_type: filters.movement_type || undefined,
}));

function resetFilters() {
    filters.equipment_id = null;
    filters.from = "";
    filters.to = "";
    filters.movement_type = "";
    selectedEquipment.value = null;
    movementRows.value = [];
    hasLoaded.value = false;
    activeView.value = "table";
}

async function loadEquipments() {
    try {
        const { data } = await api.get("/equipments/list");
        equipments.value = data ?? [];
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load equipments.",
        );
    }
}

async function loadMovements() {
    if (!filters.equipment_id) {
        notify.warn("Please select an equipment first.");
        return;
    }

    loading.value = true;
    hasLoaded.value = true;
    activeView.value = "table";
    movementRows.value = [];
    selectedEquipment.value = null;

    try {
        const { data } = await api.get("/reports/property-card", {
            params: {
                equipment_id: filters.equipment_id,
                from: filters.from || undefined,
                to: filters.to || undefined,
                movement_type: filters.movement_type || undefined,
            },
        });

        selectedEquipment.value =
            data.equipment ??
            equipments.value.find(
                (equipment) => equipment.id === filters.equipment_id,
            ) ??
            null;
        movementRows.value = Array.isArray(data.data) ? data.data : [];
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load property card.",
        );
        movementRows.value = [];
    } finally {
        loading.value = false;
    }
}

function exportPdf() {
    if (!filters.equipment_id) {
        return;
    }

    const params = {
        equipment_id: String(filters.equipment_id),
    };

    if (filters.from) {
        params.from = filters.from;
    }

    if (filters.to) {
        params.to = filters.to;
    }

    if (filters.movement_type) {
        params.movement_type = filters.movement_type;
    }

    api.get("/reports/property-card/pdf", {
        params,
        responseType: "blob",
    })
        .then((response) => {
            const disposition = response.headers["content-disposition"] || "";
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const fallback = selectedEquipment.value?.property_number
                ? `Property-Card-${selectedEquipment.value.property_number.replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "")}.pdf`
                : "Property-Card-Report.pdf";
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
    await loadEquipments();

    const equipmentId = new URLSearchParams(window.location.search).get(
        "equipment_id",
    );

    if (equipmentId) {
        filters.equipment_id = Number(equipmentId);
        await loadMovements();
    }
});
</script>

<style scoped>
.property-card-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4a6490;
    transition: color 0.15s ease;
}

.property-card-back-link:hover {
    color: #001f6b;
}

.property-card-placeholder {
    display: flex;
    min-height: 280px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px dashed #a8b8d4;
    border-radius: 0;
    background: transparent;
    text-align: center;
}

.report-view-tabs {
    display: flex;
    width: 100%;
    gap: 0;
    border-radius: 0;
    border: 1px solid #a8b8d4;
    background: transparent;
    padding: 0;
}

@media (min-width: 640px) {
    .report-view-tabs {
        display: inline-flex;
        width: auto;
    }
}

.report-view-tab {
    flex: 1;
    border-radius: 0;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4a6490;
    transition: all 0.15s ease;
    text-align: center;
    border-right: 1px solid #a8b8d4;
}

.report-view-tab:last-child {
    border-right: 0;
}

@media (min-width: 640px) {
    .report-view-tab {
        flex: none;
        padding: 0.5rem 1rem;
    }
}

.report-view-tab:hover {
    color: #001f6b;
}

.report-view-tab-active {
    background: #001f6b;
    color: white;
    box-shadow: none;
}
</style>
