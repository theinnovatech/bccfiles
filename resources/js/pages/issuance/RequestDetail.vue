<template>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <UiButton variant="outline" size="sm" @click="goBack">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Back to Requests
            </UiButton>
        </div>

        <div v-if="loading" class="shadcn-card flex min-h-[240px] items-center justify-center p-6">
            <div class="text-center">
                <svg class="mx-auto h-8 w-8 animate-spin text-[#0038a8]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <p class="mt-3 text-sm text-[#5b7fbf]">Loading supply request...</p>
            </div>
        </div>

        <div v-else-if="error" class="shadcn-card p-6">
            <p class="text-sm font-medium text-[#ce1126]">{{ error }}</p>
            <UiButton class="mt-4" variant="outline" @click="goBack">Return to Supply Requests</UiButton>
        </div>

        <template v-else-if="request">
            <div class="shadcn-card p-4 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="shadcn-card-title">{{ request.request_number }}</h3>
                        <p class="mt-1 text-sm text-[#5b7fbf]">{{ request.department?.name }} · {{ request.requester?.name }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <UiBadge :variant="statusVariant(request.status)">{{ formatStatus(request.status) }}</UiBadge>
                            <UiBadge variant="outline">{{ requestTypeLabel(request.request_type) }}</UiBadge>
                        </div>
                    </div>
                    <div v-if="canApprove && request.status === 'pending'" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <UiButton class="w-full sm:w-auto" @click="approve" :loading="acting">Approve</UiButton>
                        <UiButton class="w-full sm:w-auto" variant="destructive" @click="showReject = true" :loading="acting">Reject</UiButton>
                    </div>
                </div>
                <p v-if="request.remarks" class="mt-4 text-sm text-[#5b7fbf]">{{ request.remarks }}</p>
            </div>

            <UiCard :title="detailsTitle" :description="detailsDescription">
                <div class="obims-table-wrap">
                    <DataTable :value="requestDetails" paginator :rows="10" class="rounded-md border border-[#c8d6ef]">
                    <Column :header="detailsColumnHeader">
                        <template #body="{ data }">{{ detailName(data) }}</template>
                    </Column>
                    <Column v-if="isEquipmentRequest" header="Property No.">
                        <template #body="{ data }">{{ data.equipment?.property_number || '—' }}</template>
                    </Column>
                    <Column field="quantity_requested" header="Requested" />
                    <Column field="quantity_issued" header="Issued" />
                </DataTable>
                </div>
            </UiCard>

            <Dialog v-model:visible="showReject" modal header="Reject Request" class="obims-dialog" :style="{ width: 'min(420px, calc(100vw - 2rem))' }">
                <Textarea v-model="rejectRemarks" class="w-full" rows="4" placeholder="Reason for rejection" />
                <template #footer>
                    <UiButton variant="outline" @click="showReject = false">Cancel</UiButton>
                    <UiButton variant="destructive" @click="reject" :loading="acting">Reject</UiButton>
                </template>
            </Dialog>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import UiCard from '../../components/ui/UiCard.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import { useNotify } from '../../composables/useNotify';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    requestId: { type: [String, Number], required: true },
});

const notify = useNotify();
const auth = useAuthStore();
const request = ref(null);
const loading = ref(true);
const error = ref('');
const acting = ref(false);
const showReject = ref(false);
const rejectRemarks = ref('');
const canApprove = computed(() => auth.isAdmin || auth.isSupplyOfficer);
const requestDetails = computed(() => request.value?.details ?? []);
const isEquipmentRequest = computed(() => request.value?.request_type === 'equipments');
const detailsTitle = computed(() => (isEquipmentRequest.value ? 'Requested Equipments' : 'Requested Items'));
const detailsDescription = computed(() => (
    isEquipmentRequest.value
        ? 'Equipments included in this supply request.'
        : 'Items included in this supply request.'
));
const detailsColumnHeader = computed(() => (isEquipmentRequest.value ? 'Equipment' : 'Item'));

function requestTypeLabel(type) {
    return type === 'equipments' ? 'Equipments' : 'Items';
}

function detailName(detail) {
    if (detail.equipment) {
        return detail.equipment.name;
    }

    return detail.item?.item_name || '—';
}

function formatStatus(status) {
    return String(status || '').replaceAll('_', ' ');
}

function statusVariant(status) {
    return {
        pending: 'gold',
        approved: 'default',
        rejected: 'destructive',
        issued: 'secondary',
        partially_issued: 'outline',
    }[status] || 'outline';
}

function goBack() {
    window.location.href = '/requests';
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get(`/requests/${props.requestId}/detail`);
        request.value = data;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load this supply request.';
        request.value = null;
    } finally {
        loading.value = false;
    }
}

async function approve() {
    acting.value = true;
    try {
        await api.post(`/requests/${props.requestId}/approve`);
        notify.success('Supply request approved successfully.');
        await load();
    } catch (err) {
        notify.error(err.response?.data?.message || 'Unable to approve this request.');
    } finally {
        acting.value = false;
    }
}

async function reject() {
    acting.value = true;
    try {
        await api.post(`/requests/${props.requestId}/reject`, { remarks: rejectRemarks.value });
        showReject.value = false;
        rejectRemarks.value = '';
        notify.success('Supply request rejected.');
        await load();
    } catch (err) {
        notify.error(err.response?.data?.message || 'Unable to reject this request.');
    } finally {
        acting.value = false;
    }
}

onMounted(load);
</script>
