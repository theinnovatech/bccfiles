<template>
    <UiCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">Supply Requests</h3>
                    <p class="shadcn-card-description">
                        Review and track department supply requests.
                    </p>
                </div>
                <UiButton
                    v-if="canCreate"
                    class="w-full sm:w-auto"
                    @click="goToCreate"
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
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    New Request
                </UiButton>
            </div>
        </template>

        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredRequests.length"
            @reset="resetFilters"
        />

        <div class="obims-table-wrap">
            <DataTable
                :value="filteredRequests"
                :loading="loading"
                paginator
                :rows="10"
                class="rounded-md border border-[#c8d6ef]"
            >
                <Column field="request_number" header="Request No." />
                <Column header="Type">
                    <template #body="{ data }">
                        <UiBadge variant="outline">{{
                            requestTypeLabel(data.request_type)
                        }}</UiBadge>
                    </template>
                </Column>
                <Column header="Department"
                    ><template #body="{ data }">{{
                        data.department?.name
                    }}</template></Column
                >
                <Column header="Requested By"
                    ><template #body="{ data }">{{
                        data.requester?.name
                    }}</template></Column
                >
                <Column header="Status">
                    <template #body="{ data }">
                        <UiBadge :variant="statusVariant(data.status)">{{
                            formatStatus(data.status)
                        }}</UiBadge>
                    </template>
                </Column>
                <Column header="Date"
                    ><template #body="{ data }">{{
                        formatDate(data.request_date)
                    }}</template></Column
                >
                <Column header="Actions">
                    <template #body="{ data }">
                        <UiButton
                            variant="ghost"
                            size="icon"
                            @click="viewRequest(data.id)"
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
                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </UiButton>
                    </template>
                </Column>
            </DataTable>
        </div>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import UiBadge from "../../components/ui/UiBadge.vue";
import TableFilters from "../../components/TableFilters.vue";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";
import { useAuthStore } from "../../stores/auth";

const notify = useNotify();
const auth = useAuthStore();
const requests = ref([]);
const loading = ref(false);
const canCreate = computed(() => auth.isDepartmentUser || auth.isAdmin);

const statusOptions = [
    { label: "All statuses", value: "" },
    { label: "Pending", value: "pending" },
    { label: "Approved", value: "approved" },
    { label: "Rejected", value: "rejected" },
    { label: "Issued", value: "issued" },
    { label: "Partially Issued", value: "partially_issued" },
];

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Request no., department, requester...",
        fields: ["request_number", "department.name", "requester.name"],
    },
    {
        key: "status",
        type: "select",
        label: "Status",
        field: "status",
        options: statusOptions,
    },
]);

const {
    filters,
    filteredItems: filteredRequests,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(requests, filterConfig);

function formatStatus(status) {
    return (
        statusOptions.find((option) => option.value === status)?.label || status
    );
}

function requestTypeLabel(type) {
    return type === "equipments" ? "Equipments" : "Items";
}

function statusVariant(status) {
    return (
        {
            pending: "gold",
            approved: "default",
            rejected: "destructive",
            issued: "secondary",
            partially_issued: "outline",
        }[status] || "outline"
    );
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : "";
}

function goToCreate() {
    window.location.href = "/requests/create";
}

function viewRequest(id) {
    window.location.href = `/requests/${id}`;
}

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await api.get("/requests/list");
        requests.value = data.data ?? data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load supply requests.",
        );
    } finally {
        loading.value = false;
    }
});
</script>
