<template>
    <div class="space-y-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <UiButton variant="outline" size="sm" @click="goBack">
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
                        d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
                    />
                </svg>
                Back to Physical Inventory
            </UiButton>
        </div>

        <UiCard
            title="Counted Items"
            description="Review physical count records by session."
        >
            <div
                class="mb-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Count session</label
                    >
                    <Select
                        v-model="selectedSessionId"
                        :options="sessionOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select a session"
                        class="w-full"
                        :loading="loadingSessions"
                        @update:model-value="loadSession"
                    />
                </div>
                <div v-if="session" class="flex flex-wrap items-center gap-2">
                    <span class="stock-count-badge" :class="statusBadgeClass">{{
                        statusLabel
                    }}</span>
                    <span class="stock-count-badge stock-count-badge-muted">
                        {{ countedItems.length }} item{{
                            countedItems.length === 1 ? "" : "s"
                        }}
                    </span>
                    <span
                        v-if="session.starter?.name"
                        class="text-xs text-[#5b7fbf]"
                    >
                        By {{ session.starter.name }}
                    </span>
                </div>
            </div>

            <TableFilters
                v-if="countedItems.length"
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredItems.length"
                @reset="resetFilters"
            />

            <div v-if="loadingSession" class="stock-op-empty">
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
                <p class="mt-3 text-sm text-[#5b7fbf]">Loading records...</p>
            </div>

            <div v-else-if="!session" class="stock-op-empty">
                <svg
                    class="h-10 w-10 text-[#c8d6ef]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#002a7a]">
                    No count sessions found
                </p>
                <p class="mt-1 text-xs text-[#5b7fbf]">
                    Start a physical inventory session to record counts.
                </p>
            </div>

            <div v-else-if="!countedItems.length" class="stock-op-empty">
                <svg
                    class="h-10 w-10 text-[#c8d6ef]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                    />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#002a7a]">
                    No items counted yet
                </p>
                <p class="mt-1 text-xs text-[#5b7fbf]">
                    This session has no recorded counts.
                </p>
            </div>

            <div v-else class="obims-table-wrap">
                <DataTable
                    :value="filteredItems"
                    striped-rows
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#c8d6ef]"
                >
                    <Column header="Item">
                        <template #body="{ data }">
                            <div>
                                <p class="font-medium text-[#002a7a]">
                                    {{ data.item?.item_name }}
                                </p>
                                <p class="text-xs text-[#5b7fbf]">
                                    {{ data.item?.barcode }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column field="expected_quantity" header="Expected" />
                    <Column field="physical_quantity" header="Physical" />
                    <Column header="Variance">
                        <template #body="{ data }">
                            <span
                                class="stock-count-variance"
                                :class="varianceRowClass(data.variance)"
                            >
                                {{ data.variance }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Adjusted">
                        <template #body="{ data }">
                            <span
                                class="stock-count-badge"
                                :class="
                                    data.adjustment_created
                                        ? 'stock-count-badge-success'
                                        : 'stock-count-badge-muted'
                                "
                            >
                                {{ data.adjustment_created ? "Yes" : "No" }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import Select from "primevue/select";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiButton from "../../components/ui/UiButton.vue";
import UiCard from "../../components/ui/UiCard.vue";
import TableFilters from "../../components/TableFilters.vue";
import api from "../../services/api";

const notify = useNotify();
const sessions = ref([]);
const session = ref(null);
const selectedSessionId = ref(null);
const loadingSessions = ref(false);
const loadingSession = ref(false);

const countedItems = computed(() => session.value?.items ?? []);

const sessionOptions = computed(() =>
    sessions.value.map((row) => ({
        label: `${row.session_number} · ${row.status === "in_progress" ? "In progress" : "Completed"} · ${row.items_count ?? 0} items`,
        value: row.id,
    })),
);

const isInProgress = computed(() => session.value?.status === "in_progress");

const statusLabel = computed(() =>
    isInProgress.value ? "In progress" : "Completed",
);

const statusBadgeClass = computed(() =>
    isInProgress.value
        ? "stock-count-badge-active"
        : "stock-count-badge-complete",
);

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Item name or barcode...",
        fields: ["item.item_name", "item.barcode"],
    },
]);

const { filters, filteredItems, hasActiveFilters, resetFilters } =
    useTableFilters(countedItems, filterConfig);

function varianceRowClass(variance) {
    if (variance > 0) {
        return "stock-count-variance-positive";
    }

    if (variance < 0) {
        return "stock-count-variance-negative";
    }

    return "stock-count-variance-zero";
}

function goBack() {
    window.location.href = "/stock/count";
}

async function loadSessions(preferredId = null) {
    loadingSessions.value = true;
    try {
        const { data } = await api.get("/stock-count/sessions/list");
        sessions.value = data.data ?? data;

        if (!sessions.value.length) {
            selectedSessionId.value = null;
            session.value = null;
            return;
        }

        const queryId =
            preferredId ??
            Number(new URLSearchParams(window.location.search).get("session"));
        const preferred = sessions.value.find((row) => row.id === queryId);
        const inProgress = sessions.value.find(
            (row) => row.status === "in_progress",
        );

        selectedSessionId.value =
            preferred?.id ?? inProgress?.id ?? sessions.value[0].id;
        await loadSession();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load count sessions.",
        );
    } finally {
        loadingSessions.value = false;
    }
}

async function loadSession() {
    if (!selectedSessionId.value) {
        session.value = null;
        return;
    }

    loadingSession.value = true;
    try {
        const { data } = await api.get(
            `/stock-count/sessions/${selectedSessionId.value}`,
        );
        session.value = data;

        const url = new URL(window.location.href);
        url.searchParams.set("session", String(selectedSessionId.value));
        window.history.replaceState({}, "", url);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load session records.",
        );
        session.value = null;
    } finally {
        loadingSession.value = false;
    }
}

onMounted(() => loadSessions());
</script>
