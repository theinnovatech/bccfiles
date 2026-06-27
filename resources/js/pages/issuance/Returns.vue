<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6">
                <h3 class="text-lg font-semibold text-[#002a7a]">Return Unused Items</h3>
                <p class="mt-1 text-sm text-[#5b7fbf]">
                    Scan returned items and add them back to inventory when departments return unused supplies.
                </p>
            </div>

            <div class="stock-op-content p-4 sm:p-6 space-y-6">
                <StockOpScanner
                    v-model="barcode"
                    title="Scan to return item"
                    hint="Scan a registered item barcode and enter the quantity being returned."
                    @scan="lookupItem"
                />

                <div v-if="item" class="stock-op-workspace">
                    <StockOpItemSummary
                        :item="item"
                        :fields="[
                            { label: 'Barcode', value: item.barcode },
                            { label: 'Current stock', value: item.current_stock },
                            { label: 'Category', value: item.category?.name || '—' },
                            { label: 'Location', value: item.location?.name || '—' },
                        ]"
                    />

                    <div class="stock-op-form-panel">
                        <div class="stock-op-form-header">
                            <h4 class="stock-op-form-title">Return details</h4>
                            <p class="stock-op-form-desc">Returned quantity will be added back to on-hand stock.</p>
                        </div>

                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#002a7a]">Returned quantity</label>
                                <InputNumber
                                    v-model="quantity"
                                    class="w-full"
                                    :min="0"
                                    :use-grouping="false"
                                    input-class="w-full"
                                    @input="onQuantityInput"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#002a7a]">Reason</label>
                                <Textarea
                                    v-model="reason"
                                    class="w-full"
                                    rows="2"
                                    placeholder="Optional reason for the return"
                                />
                            </div>
                            <div class="stock-op-highlight stock-op-highlight-positive">
                                <span class="stock-op-highlight-label">New stock after return</span>
                                <strong class="stock-op-highlight-value">{{ newStockAfterReturn }}</strong>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <UiButton type="submit" :loading="loading">Process Return</UiButton>
                                <UiButton type="button" variant="outline" :disabled="loading" @click="clearItem">
                                    Cancel
                                </UiButton>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-else class="stock-op-empty">
                    <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-[#002a7a]">No item selected</p>
                    <p class="mt-1 text-xs text-[#5b7fbf]">Scan an item barcode to process a return.</p>
                </div>
            </div>
        </div>

        <UiCard title="Recent Returns" description="Latest processed item returns added back to inventory.">
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredReturns.length"
                @reset="resetFilters"
            />

            <div v-if="loadingList" class="stock-op-empty">
                <svg class="h-8 w-8 animate-spin text-[#0038a8]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <p class="mt-3 text-sm text-[#5b7fbf]">Loading returns...</p>
            </div>

            <div v-else-if="!filteredReturns.length" class="stock-op-empty">
                <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
                <p class="mt-3 text-sm font-medium text-[#002a7a]">No returns recorded yet</p>
                <p class="mt-1 text-xs text-[#5b7fbf]">Processed returns will appear here.</p>
            </div>

            <div v-else class="obims-table-wrap">
                <DataTable
                    :value="filteredReturns"
                    striped-rows
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#c8d6ef]"
                >
                    <Column header="Date">
                        <template #body="{ data }">{{ formatDate(data.date_returned) }}</template>
                    </Column>
                    <Column header="Item">
                        <template #body="{ data }">
                            <div>
                                <p class="font-medium text-[#002a7a]">{{ data.item?.item_name }}</p>
                                <p class="text-xs text-[#5b7fbf]">{{ data.item?.barcode }}</p>
                            </div>
                        </template>
                    </Column>
                    <Column field="quantity" header="Qty" />
                    <Column header="Returned By">
                        <template #body="{ data }">{{ data.returner?.name || '—' }}</template>
                    </Column>
                    <Column header="Reason">
                        <template #body="{ data }">
                            <span class="text-sm text-[#5b7fbf]">{{ data.reason || '—' }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import UiButton from '../../components/ui/UiButton.vue';
import UiCard from '../../components/ui/UiCard.vue';
import TableFilters from '../../components/TableFilters.vue';
import StockOpScanner from '../../components/stock/StockOpScanner.vue';
import StockOpItemSummary from '../../components/stock/StockOpItemSummary.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import api from '../../services/api';

const notify = useNotify();
const barcode = ref('');
const item = ref(null);
const quantity = ref(0);
const reason = ref('');
const loading = ref(false);
const loadingList = ref(false);
const returns = ref([]);

const newStockAfterReturn = computed(() => {
    if (!item.value) {
        return 0;
    }

    const returnedQty = Number(quantity.value) || 0;

    return item.value.current_stock + returnedQty;
});

function onQuantityInput(event) {
    quantity.value = event.value ?? 0;
}

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Item name, barcode, or returned by...',
        fields: ['item.item_name', 'item.barcode', 'returner.name', 'reason'],
    },
]);

const { filters, filteredItems: filteredReturns, hasActiveFilters, resetFilters } = useTableFilters(returns, filterConfig);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function clearItem() {
    item.value = null;
    barcode.value = '';
    quantity.value = 0;
    reason.value = '';
}

async function lookupItem(code) {
    try {
        const { data } = await api.get(`/items/barcode/${encodeURIComponent(code)}`);

        if (!data.item) {
            notify.warn('No registered item was found for that barcode.', 'Not found');
            clearItem();
            return;
        }

        item.value = data.item;
        quantity.value = 0;
        reason.value = '';
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to look up item.');
        clearItem();
    }
}

async function submit() {
    if (!item.value) {
        return;
    }

    const returnedQty = Number(quantity.value) || 0;

    if (returnedQty <= 0) {
        notify.warn('Enter a quantity greater than zero.', 'Invalid quantity');
        return;
    }

    loading.value = true;
    try {
        await api.post('/returns', {
            barcode: item.value.barcode,
            quantity: returnedQty,
            reason: reason.value || null,
        });
        notify.success('Return processed successfully.', 'Return completed');
        clearItem();
        await loadReturns();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to process return.');
    } finally {
        loading.value = false;
    }
}

async function loadReturns() {
    loadingList.value = true;
    try {
        const { data } = await api.get('/returns/list');
        returns.value = data.data ?? data;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load returns.');
    } finally {
        loadingList.value = false;
    }
}

onMounted(loadReturns);
</script>
