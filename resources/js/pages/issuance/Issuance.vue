<template>
    <div class="space-y-6">
        <div class="shadcn-card p-6">
            <h3 class="mb-4 text-lg font-semibold">Supply Issuance</h3>
            <form
                class="grid gap-4 md:grid-cols-2"
                @submit.prevent="loadRequest"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >Approved Request</label
                    >
                    <Select
                        v-model="requestId"
                        :options="approvedRequests"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select request"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >Received By</label
                    >
                    <Select
                        v-model="receivedBy"
                        :options="requesterOptions"
                        optionLabel="name"
                        optionValue="id"
                        :disabled="!!requestId"
                        placeholder="Select approved request first"
                        class="w-full"
                    />
                </div>
                <div class="md:col-span-2">
                    <Button
                        type="button"
                        label="Load Request"
                        @click="loadRequest"
                    />
                </div>
            </form>
        </div>

        <div v-if="selectedRequest" class="shadcn-card p-4 sm:p-6">
            <p class="mb-4 text-sm text-[#5b7fbf]">
                {{ selectedRequest.request_number }} •
                {{ selectedRequest.department?.name }} •
                {{ isEquipmentRequest ? "Equipments" : "Items" }}
            </p>
            <div class="space-y-4">
                <div
                    v-for="(line, index) in issueLines"
                    :key="index"
                    class="space-y-3 rounded-lg border border-[#eef2fa] p-4"
                >
                    <div>
                        <p class="font-medium text-[#002a7a]">
                            {{ line.display_name }}
                        </p>
                        <p
                            v-if="line.property_number"
                            class="text-sm text-[#5b7fbf]"
                        >
                            Property No.: {{ line.property_number }}
                        </p>
                        <p class="text-sm text-[#5b7fbf]">
                            Remaining: {{ line.remaining }}
                        </p>
                    </div>
                    <div
                        class="grid gap-3 md:grid-cols-[minmax(0,1fr)_140px] md:items-end"
                    >
                        <div class="min-w-0">
                            <label class="mb-1 block text-sm font-medium">{{
                                isEquipmentRequest
                                    ? "Barcode / Property No."
                                    : "Barcode"
                            }}</label>
                            <input
                                type="text"
                                class="shadcn-input cursor-default bg-[#eef2fa] text-[#002a7a]"
                                :value="line.barcode"
                                readonly
                                tabindex="-1"
                            />
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1 block text-sm font-medium"
                                >Issue Qty</label
                            >
                            <InputNumber
                                v-model="line.quantity"
                                :min="1"
                                :max="line.remaining"
                                class="w-full"
                                inputClass="w-full"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <Button
                    class="w-full sm:w-auto"
                    label="Process Issuance"
                    :loading="loading"
                    @click="submit"
                />
                <UiButton
                    class="w-full sm:w-auto"
                    variant="outline"
                    :disabled="loading"
                    @click="cancel"
                    >Cancel</UiButton
                >
            </div>
        </div>

        <UiCard
            title="Issuance History"
            description="Recent issuances processed from approved supply requests."
        >
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredIssuances.length"
                @reset="resetFilters"
            />

            <div class="obims-table-wrap">
                <DataTable
                    :value="filteredIssuances"
                    :loading="loadingList"
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#c8d6ef]"
                >
                    <Column field="issuance_number" header="Issuance No." />
                    <Column header="Request No.">
                        <template #body="{ data }">{{
                            data.request?.request_number
                        }}</template>
                    </Column>
                    <Column header="Department">
                        <template #body="{ data }">{{
                            data.request?.department?.name
                        }}</template>
                    </Column>
                    <Column header="Issued By">
                        <template #body="{ data }">{{
                            data.issuer?.name
                        }}</template>
                    </Column>
                    <Column header="Received By">
                        <template #body="{ data }">{{
                            data.receiver?.name
                        }}</template>
                    </Column>
                    <Column header="Issued">
                        <template #body="{ data }">
                            <span class="text-sm text-[#002a7a]">{{
                                formatIssuedLines(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Total Qty">
                        <template #body="{ data }">{{
                            totalQuantity(data)
                        }}</template>
                    </Column>
                    <Column header="Date">
                        <template #body="{ data }">{{
                            formatDate(data.issued_date)
                        }}</template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useNotify } from "../../composables/useNotify";
import Select from "primevue/select";
import InputNumber from "primevue/inputnumber";
import Button from "primevue/button";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import TableFilters from "../../components/TableFilters.vue";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";

const notify = useNotify();
const requestId = ref(null);
const receivedBy = ref(null);
const approvedRequests = ref([]);
const approvedRequestRows = ref([]);
const requesterOptions = ref([]);
const selectedRequest = ref(null);
const issueLines = ref([]);
const issuances = ref([]);
const loading = ref(false);
const loadingList = ref(false);

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Issuance no., request no., department...",
        fields: [
            "issuance_number",
            "request.request_number",
            "request.department.name",
            "issuer.name",
            "receiver.name",
        ],
    },
]);

const {
    filters,
    filteredItems: filteredIssuances,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(issuances, filterConfig);

const isEquipmentRequest = computed(
    () => selectedRequest.value?.request_type === "equipments",
);

function requestTypeLabel(type) {
    return type === "equipments" ? "Equipments" : "Items";
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : "";
}

function formatIssuedLines(issuance) {
    return (issuance.details ?? [])
        .map((detail) => {
            const name =
                detail.equipment?.name ?? detail.item?.item_name ?? "Line item";
            return `${name} (${detail.quantity})`;
        })
        .join(", ");
}

function totalQuantity(issuance) {
    return (issuance.details ?? []).reduce(
        (sum, detail) => sum + detail.quantity,
        0,
    );
}

async function loadApprovedRequests() {
    const { data } = await api.get("/requests/list");
    const rows = (data.data ?? data).filter((row) =>
        ["approved", "partially_issued"].includes(row.status),
    );
    approvedRequestRows.value = rows;
    approvedRequests.value = rows.map((row) => ({
        label: `${row.request_number} - ${row.department?.name} (${requestTypeLabel(row.request_type)})`,
        value: row.id,
    }));

    const requesters = new Map();

    for (const row of rows) {
        const requester = row.requester;

        if (!requester?.employee_id) {
            continue;
        }

        if (!requesters.has(requester.employee_id)) {
            requesters.set(requester.employee_id, {
                id: requester.employee_id,
                name: requester.name,
            });
        }
    }

    requesterOptions.value = Array.from(requesters.values());
}

function syncReceivedByFromRequest(requestRow) {
    if (requestRow?.requester?.employee_id) {
        receivedBy.value = requestRow.requester.employee_id;
        return;
    }

    receivedBy.value = null;
}

watch(requestId, (id) => {
    if (!id) {
        receivedBy.value = null;
        return;
    }

    const row = approvedRequestRows.value.find((request) => request.id === id);
    syncReceivedByFromRequest(row);
});

async function loadRequest() {
    if (!requestId.value) return;
    const { data } = await api.get(`/requests/${requestId.value}/detail`);
    selectedRequest.value = data;
    syncReceivedByFromRequest(data);

    const equipmentRequest = data.request_type === "equipments";

    issueLines.value = data.details
        .filter(
            (detail) => detail.quantity_requested - detail.quantity_issued > 0,
        )
        .map((detail) => {
            const remaining =
                detail.quantity_requested - detail.quantity_issued;

            if (equipmentRequest) {
                return {
                    equipment_id: detail.equipment_id,
                    display_name: detail.equipment?.name ?? "Equipment",
                    property_number: detail.equipment?.property_number ?? "",
                    barcode:
                        detail.equipment?.barcode ||
                        detail.equipment?.property_number ||
                        "",
                    remaining,
                    quantity: Math.max(remaining, 1),
                };
            }

            return {
                item_id: detail.item_id,
                display_name: detail.item?.item_name ?? "Item",
                property_number: "",
                barcode: detail.item?.barcode || "",
                remaining,
                quantity: Math.max(remaining, 1),
            };
        });
}

async function loadIssuances() {
    loadingList.value = true;
    try {
        const { data } = await api.get("/issuances/list");
        issuances.value = data.data ?? data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load issuance history.",
        );
    } finally {
        loadingList.value = false;
    }
}

function cancel() {
    selectedRequest.value = null;
    issueLines.value = [];
    requestId.value = null;
    receivedBy.value = null;
}

async function submit() {
    loading.value = true;
    try {
        const payloadItems = issueLines.value
            .filter((line) => line.quantity > 0 && line.remaining > 0)
            .map((line) => {
                if (isEquipmentRequest.value) {
                    return {
                        equipment_id: line.equipment_id,
                        quantity: line.quantity,
                    };
                }

                return {
                    barcode: line.barcode,
                    quantity: line.quantity,
                };
            });

        await api.post("/issuances", {
            request_id: requestId.value,
            received_by: receivedBy.value,
            items: payloadItems,
        });
        notify.success(
            "Issuance was processed successfully.",
            "Issuance completed",
        );
        selectedRequest.value = null;
        issueLines.value = [];
        requestId.value = null;
        receivedBy.value = null;
        await loadApprovedRequests();
        await loadIssuances();
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                error.message ||
                "Unable to process issuance.",
            "Issuance failed",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await loadApprovedRequests();
    await loadIssuances();
});
</script>
