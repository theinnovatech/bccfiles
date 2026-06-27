<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-visible">
            <div class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-xl">
                        <h3 class="text-lg font-semibold text-[#002a7a]">Estimated Stock Life</h3>
                        <p class="mt-1 text-sm text-[#5b7fbf]">
                            See how long each item might last before it runs out, using records of what was issued to requesters.
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5">
                            <label class="text-sm font-medium text-[#002a7a]">Look at records from</label>
                            <InfoHoverHint
                                aria-label="How the time period works"
                                title="Time period for the estimate"
                            >
                                <p>
                                    Choose how far back to look at issued items. The system adds up what was taken out during that time and uses it to guess how long today’s stock may last.
                                </p>
                                <p class="mt-2">
                                    Shorter period = follows recent demand. Longer period = smoother average.
                                </p>
                            </InfoHoverHint>
                        </div>
                        <Select
                            v-model="windowDays"
                            :options="windowOptions"
                            option-label="label"
                            option-value="value"
                            class="w-52"
                            @update:model-value="load"
                        />
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="stock-op-form-panel !p-4">
                        <p class="text-xs font-medium text-[#5b7fbf]">May run out within a week</p>
                        <p class="mt-1 text-2xl font-bold text-[#ce1126]">{{ summary.critical }}</p>
                    </div>
                    <div class="stock-op-form-panel !p-4">
                        <p class="text-xs font-medium text-[#5b7fbf]">May run out within a month</p>
                        <p class="mt-1 text-2xl font-bold text-[#e5bc00]">{{ summary.warning }}</p>
                    </div>
                    <div class="stock-op-form-panel !p-4">
                        <p class="text-xs font-medium text-[#5b7fbf]">Out of stock</p>
                        <p class="mt-1 text-2xl font-bold text-[#ce1126]">{{ summary.out_of_stock }}</p>
                    </div>
                    <div class="stock-op-form-panel !p-4">
                        <p class="text-xs font-medium text-[#5b7fbf]">Enough stock for now</p>
                        <p class="mt-1 text-2xl font-bold text-[#0038a8]">{{ summary.healthy + summary.stable }}</p>
                    </div>
                </div>
            </div>

            <div class="stock-op-content p-4 sm:p-6">
                <TableFilters
                    v-model="filters"
                    :filters="filterConfig"
                    :has-active-filters="hasActiveFilters"
                    :result-count="filteredRows.length"
                    @reset="resetFilters"
                />

                <div class="mb-4 flex items-center gap-1.5 text-sm text-[#002a7a]">
                    <span class="font-medium">How these estimates work</span>
                    <InfoHoverHint
                        aria-label="How stock estimates are calculated"
                        title="How we calculate these numbers"
                        :width="320"
                    >
                        <p class="font-medium text-[#002a7a]">Data we use:</p>
                        <ul class="mt-1 list-inside list-disc space-y-1">
                            <li><strong>Stock on hand</strong> — quantity in inventory today</li>
                            <li><strong>Issuance records</strong> — what was given to requesters in the time period you selected above</li>
                            <li><strong>Returns</strong> — items brought back (reduces total usage)</li>
                        </ul>
                        <p class="mt-3">
                            We total what went out, work out an average daily use, then divide current stock by that average.
                            That gives <strong>May last about</strong>. <strong>May run out by</strong> is today’s date plus those days.
                        </p>
                        <p class="mt-2">
                            If an item was never issued in that period, we show <strong>Not sure yet</strong>.
                        </p>
                    </InfoHoverHint>
                </div>

                <div class="obims-table-wrap">
                    <DataTable
                        :value="filteredRows"
                        :loading="loading"
                        paginator
                        :rows="10"
                        class="rounded-md border border-[#c8d6ef]"
                    >
                        <Column header="Item">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-[#002a7a]">{{ data.item.item_name }}</p>
                                    <p class="text-xs text-[#5b7fbf]">{{ data.item.barcode }}</p>
                                </div>
                            </template>
                        </Column>
                        <Column header="Stock on hand">
                            <template #body="{ data }">
                                <span class="font-semibold text-[#002a7a]">{{ data.item.current_stock }}</span>
                                <span class="text-xs text-[#5b7fbf]"> {{ data.item.unit }}</span>
                            </template>
                        </Column>
                        <Column>
                            <template #header>
                                <span class="inline-flex items-center gap-1">
                                    May last about
                                    <InfoHoverHint
                                        aria-label="What May last about means"
                                        title="May last about"
                                    >
                                        <p>
                                            A rough count of how many days the current stock might last if requesters keep taking about the same amount as in your selected time period.
                                        </p>
                                    </InfoHoverHint>
                                </span>
                            </template>
                            <template #body="{ data }">
                                <span class="font-semibold" :class="daysLeftClass(data)">
                                    {{ formatDaysLeft(data.estimated_days_left) }}
                                </span>
                            </template>
                        </Column>
                        <Column>
                            <template #header>
                                <span class="inline-flex items-center gap-1">
                                    May run out by
                                    <InfoHoverHint
                                        aria-label="What May run out by means"
                                        title="May run out by"
                                    >
                                        <p>
                                            The calendar date when stock might reach zero, based on the “May last about” estimate. This is only a guide—not a guaranteed date.
                                        </p>
                                    </InfoHoverHint>
                                </span>
                            </template>
                            <template #body="{ data }">
                                {{ data.projected_stockout_date || '—' }}
                            </template>
                        </Column>
                        <Column header="Most requests from">
                            <template #body="{ data }">
                                <span v-if="data.top_requesters?.length">{{ data.top_requesters[0].name }}</span>
                                <span v-else class="text-[#5b7fbf]">—</span>
                            </template>
                        </Column>
                        <Column header="Status">
                            <template #body="{ data }">
                                <span class="stock-count-badge" :class="statusBadgeClass(data.status)">
                                    {{ data.status_label }}
                                </span>
                            </template>
                        </Column>
                        <Column header="" style="width: 5rem">
                            <template #body="{ data }">
                                <UiButton variant="ghost" size="sm" @click="openDetail(data)">Details</UiButton>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="detailVisible"
            modal
            :header="selected?.item?.item_name || 'Item details'"
            :style="{ width: '520px' }"
        >
            <div v-if="selected" class="space-y-4 pt-1">
                <p class="text-sm leading-relaxed text-[#001e5a]">{{ selected.message }}</p>

                <div class="rounded-lg border border-[#c8d6ef] bg-[#eef2fa] p-4 text-sm">
                    <p>
                        <span class="text-[#5b7fbf]">Stock on hand:</span>
                        <strong class="text-[#002a7a]">{{ selected.item.current_stock }} {{ selected.item.unit }}</strong>
                    </p>
                    <p class="mt-2">
                        <span class="text-[#5b7fbf]">May last about:</span>
                        <strong class="text-[#002a7a]">{{ formatDaysLeft(selected.estimated_days_left) }}</strong>
                    </p>
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-semibold text-[#002a7a]">Who requested this item</h4>
                    <div v-if="selected.all_requesters?.length" class="space-y-2">
                        <div
                            v-for="person in selected.all_requesters"
                            :key="person.id"
                            class="flex items-center justify-between rounded-md border border-[#c8d6ef] px-3 py-2 text-sm"
                        >
                            <span class="font-medium text-[#002a7a]">{{ person.name }}</span>
                            <span class="text-[#5b7fbf]">{{ person.quantity_issued }} taken · {{ person.request_count }} request(s)</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-[#5b7fbf]">No request records for this period.</p>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import UiButton from '../../components/ui/UiButton.vue';
import InfoHoverHint from '../../components/InfoHoverHint.vue';
import TableFilters from '../../components/TableFilters.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import api from '../../services/api';

const notify = useNotify();
const loading = ref(false);
const rows = ref([]);
const windowDays = ref(90);
const summary = ref({
    total: 0,
    critical: 0,
    warning: 0,
    out_of_stock: 0,
    stable: 0,
    no_data: 0,
    healthy: 0,
});
const detailVisible = ref(false);
const selected = ref(null);

const windowOptions = [
    { label: 'Past 1 month', value: 30 },
    { label: 'Past 2 months', value: 60 },
    { label: 'Past 3 months', value: 90 },
];

const statusOptions = [
    { label: 'All items', value: '' },
    { label: 'May run out within a week', value: 'critical' },
    { label: 'May run out within a month', value: 'warning' },
    { label: 'Out of stock', value: 'out_of_stock' },
    { label: 'Enough stock for now', value: 'healthy' },
    { label: 'No records yet', value: 'no_data' },
];

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Item name or barcode...',
        fields: ['item.item_name', 'item.barcode'],
    },
    {
        key: 'status',
        type: 'select',
        label: 'Status',
        field: 'status',
        options: statusOptions,
    },
]);

const { filters, filteredItems: filteredRows, hasActiveFilters, resetFilters } = useTableFilters(rows, filterConfig);

function formatDaysLeft(value) {
    if (value === null || value === undefined) return 'Not sure yet';
    if (value === 0) return 'None left';
    return `About ${value} day${value === 1 ? '' : 's'}`;
}

function daysLeftClass(row) {
    if (row.status === 'critical' || row.status === 'out_of_stock') return 'text-[#ce1126]';
    if (row.status === 'warning') return 'text-[#e5bc00]';
    return 'text-[#002a7a]';
}

function statusBadgeClass(status) {
    if (status === 'critical' || status === 'out_of_stock') return 'stock-count-badge-danger';
    if (status === 'warning') return 'stock-count-badge-warning';
    if (status === 'healthy' || status === 'stable') return 'stock-count-badge-success';
    return 'stock-count-badge-muted';
}

function openDetail(row) {
    selected.value = row;
    detailVisible.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/inventory/predictions/list', { params: { window_days: windowDays.value } });
        rows.value = data.data ?? [];
        summary.value = data.summary ?? summary.value;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load stock estimates.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
