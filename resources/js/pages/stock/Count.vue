<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-[#002a7a]">Physical Inventory</h3>
                        <p class="mt-1 text-sm text-[#5b7fbf]">
                            Count items on hand, record physical quantities, and auto-apply adjustments for variances.
                        </p>

                        <div v-if="session" class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="stock-count-badge">{{ session.session_number }}</span>
                            <span class="stock-count-badge" :class="statusBadgeClass">{{ statusLabel }}</span>
                            <span class="stock-count-badge stock-count-badge-muted">
                                {{ countedItems.length }} item{{ countedItems.length === 1 ? '' : 's' }} counted
                            </span>
                            <span v-if="session.starter?.name" class="text-xs text-[#5b7fbf]">
                                Started by {{ session.starter.name }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <UiButton variant="outline" @click="viewRecords">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            View Records
                        </UiButton>
                        <UiButton v-if="!session" :loading="starting" @click="startSession">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653v15.694a2.25 2.25 0 01-2.25 2.25h-1.5a2.25 2.25 0 01-2.25-2.25V5.653zM16.5 3.75h-1.5a2.25 2.25 0 00-2.25 2.25v16.5a2.25 2.25 0 002.25 2.25h1.5a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25z" />
                            </svg>
                            Start Count Session
                        </UiButton>
                        <UiButton
                            v-else-if="isInProgress"
                            variant="outline"
                            :disabled="completing"
                            @click="clearSelection"
                        >
                            Clear selection
                        </UiButton>
                        <UiButton v-if="isInProgress" :loading="completing" @click="completeSession">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Complete Session
                        </UiButton>
                        <UiButton v-if="session && !isInProgress" :loading="starting" @click="startSession">
                            Start New Session
                        </UiButton>
                    </div>
                </div>
            </div>

            <div class="stock-op-content p-4 sm:p-6">
                <div v-if="loadingSession" class="stock-op-empty">
                    <svg class="h-8 w-8 animate-spin text-[#0038a8]" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <p class="mt-3 text-sm text-[#5b7fbf]">Loading session...</p>
                </div>

                <div v-else-if="!session" class="stock-op-empty">
                    <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-[#002a7a]">No active count session</p>
                    <p class="mt-1 text-xs text-[#5b7fbf]">Start a session to scan items and record physical quantities.</p>
                    <UiButton class="mt-4" :loading="starting" @click="startSession">Start Count Session</UiButton>
                </div>

                <template v-else>
                    <div v-if="isInProgress" class="space-y-6">
                        <StockOpScanner
                            v-model="barcode"
                            title="Scan to count item"
                            hint="Scan each item and enter the quantity found during the physical count."
                            @scan="lookupItem"
                        />

                        <div v-if="selectedItem" class="stock-op-workspace">
                            <StockOpItemSummary
                                :item="selectedItem"
                                :fields="[
                                    { label: 'Barcode', value: selectedItem.barcode },
                                    { label: 'System stock', value: selectedItem.current_stock },
                                    { label: 'Category', value: selectedItem.category?.name || '—' },
                                    { label: 'Location', value: selectedItem.location?.name || '—' },
                                ]"
                            />

                            <div class="stock-op-form-panel">
                                <div class="stock-op-form-header">
                                    <h4 class="stock-op-form-title">Physical count</h4>
                                    <p class="stock-op-form-desc">Enter the actual quantity on hand for this item.</p>
                                </div>

                                <form class="space-y-4" @submit.prevent="recordCount">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#002a7a]">Physical quantity</label>
                                        <InputNumber v-model="physicalQty" class="w-full" :min="0" required />
                                    </div>
                                    <div class="stock-op-highlight" :class="varianceClass">
                                        <span class="stock-op-highlight-label">Variance vs system</span>
                                        <strong class="stock-op-highlight-value">{{ variancePreview }}</strong>
                                    </div>
                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <UiButton type="submit" :loading="recording">Record Count</UiButton>
                                        <UiButton type="button" variant="outline" :disabled="recording" @click="clearSelection">
                                            Cancel
                                        </UiButton>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div v-else class="stock-op-empty">
                            <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h2l2-3h10l2 3h2a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V9a2 2 0 012-2z" />
                            </svg>
                            <p class="mt-3 text-sm font-medium text-[#002a7a]">No item selected</p>
                            <p class="mt-1 text-xs text-[#5b7fbf]">Scan an item barcode to record its physical count.</p>
                        </div>
                    </div>

                    <div v-else class="stock-op-alert stock-op-alert-success">
                        <div class="stock-op-alert-icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="stock-op-alert-title">Session completed</p>
                            <p class="stock-op-alert-text">This count session is closed. Use View Records to review results or start a new session.</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useNotify } from '../../composables/useNotify';
import InputNumber from 'primevue/inputnumber';
import UiButton from '../../components/ui/UiButton.vue';
import StockOpScanner from '../../components/stock/StockOpScanner.vue';
import StockOpItemSummary from '../../components/stock/StockOpItemSummary.vue';
import api from '../../services/api';

const notify = useNotify();
const session = ref(null);
const selectedItem = ref(null);
const barcode = ref('');
const physicalQty = ref(0);
const loadingSession = ref(true);
const starting = ref(false);
const recording = ref(false);
const completing = ref(false);

const countedItems = computed(() => session.value?.items ?? []);
const isInProgress = computed(() => session.value?.status === 'in_progress');

const statusLabel = computed(() => {
    if (!session.value) {
        return '';
    }

    return session.value.status === 'in_progress' ? 'In progress' : 'Completed';
});

const statusBadgeClass = computed(() =>
    session.value?.status === 'in_progress' ? 'stock-count-badge-active' : 'stock-count-badge-complete',
);

const variancePreview = computed(() => {
    if (!selectedItem.value) {
        return 0;
    }

    return (physicalQty.value ?? 0) - selectedItem.value.current_stock;
});

const varianceClass = computed(() => {
    if (variancePreview.value > 0) {
        return 'stock-op-highlight-positive';
    }

    if (variancePreview.value < 0) {
        return 'stock-op-highlight-negative';
    }

    return '';
});

function viewRecords() {
    const url = session.value?.id
        ? `/stock/count/records?session=${session.value.id}`
        : '/stock/count/records';

    window.location.href = url;
}

function clearSelection() {
    selectedItem.value = null;
    barcode.value = '';
    physicalQty.value = 0;
}

async function loadActiveSession() {
    loadingSession.value = true;
    try {
        const { data } = await api.get('/stock-count/sessions/list');
        const sessions = data.data ?? data;
        const active = sessions.find((row) => row.status === 'in_progress');

        if (!active) {
            session.value = null;
            return;
        }

        const { data: fullSession } = await api.get(`/stock-count/sessions/${active.id}`);
        session.value = fullSession;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load count session.');
    } finally {
        loadingSession.value = false;
    }
}

async function startSession() {
    starting.value = true;
    try {
        const { data } = await api.post('/stock-count/sessions');
        const { data: fullSession } = await api.get(`/stock-count/sessions/${data.id}`);
        session.value = fullSession;
        clearSelection();
        notify.success('Count session started.', 'Session started');
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to start session.');
    } finally {
        starting.value = false;
    }
}

async function lookupItem(code) {
    if (!isInProgress.value || !code) {
        return;
    }

    try {
        const { data } = await api.get(`/items/barcode/${encodeURIComponent(code)}`);

        if (!data.item) {
            notify.warn('No registered item was found for that barcode.');
            selectedItem.value = null;
            return;
        }

        selectedItem.value = data.item;
        physicalQty.value = data.item.current_stock;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to look up item.');
    }
}

async function recordCount() {
    if (!session.value || !selectedItem.value) {
        return;
    }

    recording.value = true;
    try {
        const { data } = await api.post(`/stock-count/sessions/${session.value.id}/items`, {
            barcode: selectedItem.value.barcode,
            physical_quantity: physicalQty.value,
        });

        session.value.items = session.value.items || [];
        const index = session.value.items.findIndex((row) => row.id === data.count_item.id);

        if (index >= 0) {
            session.value.items[index] = data.count_item;
        } else {
            session.value.items.unshift(data.count_item);
        }

        if (data.item) {
            selectedItem.value = data.item;
        }

        notify.success('Physical count recorded.', 'Count saved');
        clearSelection();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to record count.');
    } finally {
        recording.value = false;
    }
}

async function completeSession() {
    completing.value = true;
    try {
        const { data } = await api.post(`/stock-count/sessions/${session.value.id}/complete`);
        session.value = data;
        clearSelection();
        notify.success('Count session completed.', 'Session completed');
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to complete session.');
    } finally {
        completing.value = false;
    }
}

onMounted(loadActiveSession);
</script>
