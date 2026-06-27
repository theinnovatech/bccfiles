<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div>
                    <h3 class="shadcn-card-title">Reports</h3>
                    <p class="shadcn-card-description">
                        Select a report type to preview and download as PDF.
                    </p>
                </div>
            </template>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="report in reports"
                    :key="report.type"
                    type="button"
                    class="report-type-btn"
                    :class="{
                        'report-type-btn-active': activeType === report.type,
                    }"
                    @click="loadReport(report.type)"
                >
                    <svg
                        class="h-4 w-4 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                        />
                    </svg>
                    <span>{{ report.label }}</span>
                </button>
            </div>
        </UiCard>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">
                            {{ activeLabel || "Report Preview" }}
                        </h3>
                        <p class="shadcn-card-description">
                            <span v-if="!activeType"
                                >Choose a report above to preview it here.</span
                            >
                            <span v-else-if="loading"
                                >Loading report data...</span
                            >
                            <span v-else-if="reportRows.length"
                                >{{ reportRows.length }} record{{
                                    reportRows.length === 1 ? "" : "s"
                                }}
                                found</span
                            >
                            <span v-else
                                >No records available for this report.</span
                            >
                        </p>
                    </div>
                    <UiButton
                        v-if="activeType && !loading && reportRows.length"
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

            <div v-if="!activeType" class="report-placeholder">
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
                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#002a7a]">
                    No report selected
                </p>
                <p class="mt-1 text-xs text-[#5b7fbf]">
                    Pick a report type to preview its contents.
                </p>
            </div>

            <div v-else-if="loading" class="report-placeholder">
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
                <p class="mt-3 text-sm text-[#5b7fbf]">Loading report...</p>
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
                        :type="activeType"
                        :data="reportRows"
                    />
                    <ReportPdfViewer
                        v-else
                        :type="activeType"
                        :has-data="reportRows.length > 0"
                    />
                </div>
            </template>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import ReportPreview from "../../components/ReportPreview.vue";
import ReportPdfViewer from "../../components/ReportPdfViewer.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

const notify = useNotify();

const reports = [
    { type: "inventory", label: "Supply Inventory Report" },
    { type: "equipment-inventory", label: "Equipment Inventory Report" },
    { type: "stock-movements", label: "Stock Movement Report" },
    { type: "issuance", label: "Issuance Report" },
    { type: "returns", label: "Return Report" },
    { type: "low-stock", label: "Low Stock Report" },
    { type: "physical-inventory", label: "Physical Inventory Report" },
    { type: "monthly-consumption", label: "Monthly Supply Consumption" },
];

const activeType = ref("");
const activeView = ref("pdf");
const reportRows = ref([]);
const loading = ref(false);

const activeLabel = computed(
    () => reports.find((report) => report.type === activeType.value)?.label,
);

async function loadReport(type) {
    activeType.value = type;
    activeView.value = "pdf";
    loading.value = true;
    reportRows.value = [];

    try {
        const { data } = await api.get(`/reports/${type}`);
        reportRows.value = Array.isArray(data.data)
            ? data.data
            : data.data
              ? [data.data]
              : [];
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load report preview.",
        );
        reportRows.value = [];
    } finally {
        loading.value = false;
    }
}

function exportPdf() {
    window.open(`/reports/${activeType.value}/pdf`, "_blank");
}
</script>

<style scoped>
.report-type-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #c8d6ef;
    background: white;
    padding: 0.875rem 1rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 500;
    color: #002a7a;
    transition: all 0.15s ease;
}

.report-type-btn:hover {
    border-color: #0038a8;
    background: #eef2fa;
}

.report-type-btn-active {
    border-color: #0038a8;
    background: #0038a8;
    color: white;
}

.report-placeholder {
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
