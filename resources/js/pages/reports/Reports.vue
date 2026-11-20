<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div>
                    <h3 class="shadcn-card-title">Reports</h3>
                    <p class="shadcn-card-description">
                        Select a report type, set filters, then preview or download as PDF or Excel (.xlsx).
                        Downloads use the same filters as the preview.
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
                            <span v-else-if="isForm"
                                >Office form (IIRUP, semi-expendable) on the supplied layout.</span
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
                    <div v-if="activeType && !loading && (reportRows.length || isForm)" class="flex w-full flex-wrap gap-2 sm:w-auto">
                    <UiButton
                        variant="outline"
                        class="w-full sm:w-auto"
                        :loading="exportingXlsx"
                        @click="exportXlsx"
                    >
                        Download Excel (.xlsx)
                    </UiButton>
                    <UiButton
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
                </div>
            </template>

            <div v-if="activeType && visibleFilters.length" class="mb-4 grid gap-3 border border-[#a8b8d4] p-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="filter in visibleFilters" :key="filter.key">
                    <label class="mb-1 block text-xs font-medium text-[#4a6490]">{{ filter.label }}</label>
                    <input
                        v-if="filter.type === 'date'"
                        v-model="filterValues[filter.key]"
                        type="date"
                        class="shadcn-input"
                    />
                    <input
                        v-else-if="filter.type === 'number'"
                        v-model="filterValues[filter.key]"
                        type="number"
                        class="shadcn-input"
                        :placeholder="filter.placeholder"
                    />
                    <select v-else v-model="filterValues[filter.key]" class="shadcn-input">
                        <option v-for="option in filter.options" :key="String(option.value)" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <UiButton size="sm" @click="loadReport(activeType, false)">Apply filters</UiButton>
                    <UiButton variant="outline" size="sm" @click="clearFilters">Clear</UiButton>
                </div>
            </div>

            <div
                v-if="isForm"
                class="mb-4 space-y-3 border border-[#a8b8d4] p-3 text-sm text-[#00164d]"
            >
                <div class="flex flex-wrap items-end gap-3">
                    <div v-if="canIssueIirup" class="min-w-[14rem] flex-1">
                        <label class="mb-1 block text-xs font-medium text-[#4a6490]">
                            IIRUP No.
                            <span v-if="!numbering.isAuto('iirup_number')" class="text-[#ce1126]">*</span>
                        </label>
                        <input
                            v-model="iirupNumber"
                            type="text"
                            class="shadcn-input"
                            :placeholder="numbering.placeholder('iirup_number')"
                        />
                    </div>
                    <UiButton
                        v-if="canIssueIirup"
                        size="sm"
                        :loading="issuingIirup"
                        @click="issueIirup"
                    >
                        Issue IIRUP as of {{ filterValues.as_of || "today" }}
                    </UiButton>
                </div>
                <p v-if="canIssueIirup" class="text-xs text-[#4a6490]">
                    {{ numbering.hint("iirup_number") }} Issuing keeps a numbered copy of the report as it
                    stands; the preview below is a draft.
                </p>
                <div v-if="iirupDocuments.length">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]">Issued IIRUPs</p>
                    <ul class="mt-1 divide-y divide-[#d7e0ef]">
                        <li
                            v-for="doc in iirupDocuments"
                            :key="doc.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-1.5"
                        >
                            <span>
                                <span class="font-mono font-semibold">{{ doc.document_number }}</span>
                                · as of {{ doc.as_of_date }}
                                <span class="text-xs text-[#4a6490]">
                                    · issued {{ new Date(doc.issued_at).toLocaleDateString() }}
                                    <template v-if="doc.issuer"> by {{ doc.issuer.name }}</template>
                                </span>
                            </span>
                            <span class="flex gap-2">
                                <UiButton variant="outline" size="sm" @click="openIirup(doc)">PDF</UiButton>
                                <UiButton variant="outline" size="sm" @click="downloadIirup(doc)">Excel</UiButton>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="!activeType" class="report-placeholder">
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
                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#00164d]">
                    No report selected
                </p>
                <p class="mt-1 text-xs text-[#4a6490]">
                    Pick a report type to preview its contents.
                </p>
            </div>

            <div v-else-if="loading" class="report-placeholder">
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
                <p class="mt-3 text-sm text-[#4a6490]">Loading report...</p>
            </div>

            <template v-else>
                <div v-if="!isForm" class="report-view-tabs w-full sm:w-auto">
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
                        v-if="activeView === 'table' && !isForm"
                        :type="activeType"
                        :data="reportRows"
                    />
                    <ReportPdfViewer
                        v-else
                        :type="activeType"
                        :has-data="isForm || reportRows.length > 0"
                        :params="appliedParams"
                        :base-url="isForm ? '/forms/iirup/pdf' : ''"
                    />
                </div>
            </template>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import ReportPreview from "../../components/ReportPreview.vue";
import ReportPdfViewer from "../../components/ReportPdfViewer.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";
import { downloadFile, openInNewTab } from "../../utils/downloads";
import { useAuthStore } from "../../stores/auth";
import { useDocumentNumbering } from "../../composables/useDocumentNumbering";

const notify = useNotify();
const auth = useAuthStore();
const numbering = useDocumentNumbering();
const canIssueIirup = computed(() => auth.isAdmin || auth.isSupplyOfficer);
const iirupNumber = ref("");
const issuingIirup = ref(false);
const iirupDocuments = ref([]);

async function loadIirupDocuments() {
    try {
        const { data } = await api.get("/forms/iirup-documents");
        iirupDocuments.value = data ?? [];
    } catch {
        iirupDocuments.value = [];
    }
}

async function issueIirup() {
    if (!numbering.isAuto("iirup_number") && !iirupNumber.value.trim()) {
        notify.warn("Enter the IIRUP No. assigned by the organization (numbering is set to manual).");
        return;
    }
    issuingIirup.value = true;
    try {
        const today = new Date();
        const pad = (n) => String(n).padStart(2, "0");
        const asOf = filterValues.as_of || `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
        const { data } = await api.post("/forms/iirup/issue", {
            as_of: asOf,
            number: iirupNumber.value.trim() || null,
        });
        notify.success(data.message, "IIRUP issued");
        iirupNumber.value = "";
        numbering.load(true);
        await loadIirupDocuments();
    } catch (error) {
        const errors = error.response?.data?.errors;
        const first = errors ? Object.values(errors)[0] : null;
        notify.error((Array.isArray(first) ? first[0] : first) || error.response?.data?.message || "Unable to issue the IIRUP.");
    } finally {
        issuingIirup.value = false;
    }
}

function openIirup(doc) {
    openInNewTab(`/forms/iirup-documents/${doc.id}/pdf`, { preview: 1 });
}

async function downloadIirup(doc) {
    try {
        await downloadFile(`/forms/iirup-documents/${doc.id}/xlsx`, {}, `IIRUP-${doc.document_number}.xlsx`);
    } catch (error) {
        notify.error(error.message, "Download failed");
    }
}

const reports = [
    { type: "inventory", label: "Supply Inventory Report" },
    { type: "equipment-inventory", label: "Equipment Inventory Report" },
    { type: "stock-movements", label: "Stock Movement Report" },
    { type: "issuance", label: "Issuance Report" },
    { type: "returns", label: "Equipment Return Report" },
    { type: "low-stock", label: "Low Stock Report" },
    { type: "monthly-consumption", label: "Monthly Supply Consumption" },
    { type: "equipment-status", label: "Equipment Status Report" },
    { type: "delivery-inspections", label: "Delivery Inspection Report" },
    { type: "iirup", label: "IIRUP — Unserviceable Semi-Expendable Property (form)" },
];

const departments = ref([]);

const FILTERS = {
    from: { key: "from", label: "Date from", type: "date" },
    to: { key: "to", label: "Date to", type: "date" },
};

const filterValues = reactive({});
const appliedParams = ref({});
const exportingXlsx = ref(false);

const isForm = computed(() => activeType.value === "iirup");

const visibleFilters = computed(() => {
    const departmentFilter = {
        key: "department_id",
        label: "Department / Unit",
        type: "select",
        options: [{ label: "All departments", value: "" }, ...departments.value.map((d) => ({ label: d.name, value: d.id }))],
    };

    return {
        inventory: [{ key: "stock_status", label: "Stock status", type: "select", options: [
            { label: "All", value: "" }, { label: "In Stock", value: "in_stock" }, { label: "Low Stock", value: "low_stock" }, { label: "Out of Stock", value: "out_of_stock" },
        ] }],
        "low-stock": [{ key: "stock_status", label: "Stock status", type: "select", options: [
            { label: "Low + Out of Stock", value: "" }, { label: "Low Stock only", value: "low_stock" }, { label: "Out of Stock only", value: "out_of_stock" },
        ] }],
        "stock-movements": [FILTERS.from, FILTERS.to, { key: "transaction_type", label: "Type", type: "select", options: [
            { label: "All", value: "" }, { label: "IN", value: "IN" }, { label: "OUT", value: "OUT" }, { label: "RETURN", value: "RETURN" }, { label: "ADJUSTMENT", value: "ADJUSTMENT" },
        ] }],
        issuance: [FILTERS.from, FILTERS.to, departmentFilter],
        returns: [FILTERS.from, FILTERS.to, departmentFilter, { key: "condition", label: "Condition", type: "select", options: [
            { label: "All", value: "" }, { label: "Good", value: "good" }, { label: "Unserviceable", value: "damaged" },
        ] }],
        "monthly-consumption": [{ key: "year", label: "Year", type: "number", placeholder: String(new Date().getFullYear()) }],
        "equipment-status": [{ key: "status", label: "Status", type: "select", options: [
            { label: "All statuses", value: "" }, { label: "Available", value: "available" }, { label: "Issued", value: "issued" },
            { label: "For Repair", value: "for_repair" }, { label: "For Disposal", value: "for_disposal" }, { label: "Disposed", value: "disposed" },
            { label: "Active warranty cases", value: "warranty" }, { label: "Unserviceable", value: "awaiting_assessment" },
        ] }],
        "delivery-inspections": [FILTERS.from, FILTERS.to, { key: "status", label: "Status", type: "select", options: [
            { label: "All", value: "" }, { label: "Draft", value: "draft" }, { label: "Posted", value: "posted" },
        ] }],
        iirup: [{ key: "as_of", label: "As of", type: "date" }],
    }[activeType.value] || [];
});

function currentParams() {
    const params = {};
    visibleFilters.value.forEach((filter) => {
        const value = filterValues[filter.key];
        if (value !== "" && value !== null && value !== undefined) {
            params[filter.key] = value;
        }
    });
    return params;
}

function clearFilters() {
    Object.keys(filterValues).forEach((key) => { filterValues[key] = ""; });
    loadReport(activeType.value, false);
}

async function exportXlsx() {
    exportingXlsx.value = true;
    try {
        const url = isForm.value ? "/forms/iirup/xlsx" : `/reports/${activeType.value}/xlsx`;
        await downloadFile(url, appliedParams.value, `${activeType.value}.xlsx`);
    } catch (error) {
        notify.error(error.message, "Export failed");
    } finally {
        exportingXlsx.value = false;
    }
}

onMounted(async () => {
    try {
        const { data } = await api.get("/departments/list");
        departments.value = data.data ?? data;
    } catch {
        departments.value = [];
    }
});

const activeType = ref("");
const activeView = ref("pdf");
const reportRows = ref([]);
const loading = ref(false);

const activeLabel = computed(
    () => reports.find((report) => report.type === activeType.value)?.label,
);

async function loadReport(type, resetFilters = true) {
    if (resetFilters && type !== activeType.value) {
        Object.keys(filterValues).forEach((key) => { filterValues[key] = ""; });
    }
    activeType.value = type;
    if (resetFilters) {
        activeView.value = "pdf";
    }
    appliedParams.value = currentParams();
    reportRows.value = [];

    if (type === "iirup") {
        loading.value = false;
        numbering.load(true);
        loadIirupDocuments();
        return;
    }

    loading.value = true;

    try {
        const { data } = await api.get(`/reports/${type}`, { params: appliedParams.value });
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
    const base = isForm.value ? "/forms/iirup/pdf" : `/reports/${activeType.value}/pdf`;
    const query = new URLSearchParams(appliedParams.value).toString();
    window.open(query ? `${base}?${query}` : base, "_blank");
}
</script>

<style scoped>
.report-type-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0;
    border: 1px solid #a8b8d4;
    background: transparent;
    padding: 0.875rem 1rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 500;
    color: #00164d;
    transition: all 0.15s ease;
}

.report-type-btn:hover {
    border-color: #001f6b;
    background: #e2e8f2;
}

.report-type-btn-active {
    border-color: #001f6b;
    border-left-width: 4px;
    background: #001f6b;
    color: white;
}

.report-placeholder {
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
