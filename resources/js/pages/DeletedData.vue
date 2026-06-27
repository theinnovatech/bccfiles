<template>
    <UiCard title="Deleted Data" description="View and restore soft-deleted records across the system.">
        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredRecords.length"
            @reset="resetFilters"
        />

        <DataTable
            :value="filteredRecords"
            :loading="loading"
            paginator
            :rows="15"
            class="rounded-md border border-[#c8d6ef]"
        >
            <Column field="module" header="Module" />
            <Column field="name" header="Record" />
            <Column header="Deleted At">
                <template #body="{ data }">{{ formatDate(data.deleted_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 12rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-1">
                        <UiButton variant="ghost" size="sm" :disabled="actionLoading" @click="restoreRecord(data)">
                            Restore
                        </UiButton>
                        <UiButton variant="ghost" size="sm" :disabled="actionLoading" @click="permanentlyDelete(data)">
                            Delete
                        </UiButton>
                    </div>
                </template>
            </Column>
        </DataTable>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import UiCard from '../components/ui/UiCard.vue';
import UiButton from '../components/ui/UiButton.vue';
import TableFilters from '../components/TableFilters.vue';
import { confirmDelete, openConfirm } from '../composables/confirm';
import { useNotify } from '../composables/useNotify';
import { useTableFilters } from '../composables/useTableFilters';
import api from '../services/api';

const notify = useNotify();
const records = ref([]);
const typeOptions = ref([{ label: 'All modules', value: '' }]);
const loading = ref(false);
const actionLoading = ref(false);

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Module or record name...',
        fields: ['module', 'name'],
    },
    {
        key: 'type',
        type: 'select',
        label: 'Module',
        field: 'type',
        options: typeOptions.value,
    },
]);

const { filters, filteredItems: filteredRecords, hasActiveFilters, resetFilters } = useTableFilters(records, filterConfig);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/deleted-data/list');
        records.value = data.data ?? [];
        typeOptions.value = [
            { label: 'All modules', value: '' },
            ...(data.types ?? []),
        ];
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load deleted data.');
    } finally {
        loading.value = false;
    }
}

function restoreRecord(record) {
    openConfirm({
        title: 'Restore record?',
        message: `Restore "${record.name}" in ${record.module}?`,
        detail: 'The record will be available again in the system.',
        acceptLabel: 'Restore',
        onAccept: async () => {
            actionLoading.value = true;
            try {
                await api.post(`/deleted-data/${record.type}/${record.id}/restore`);
                await load();
                notify.success('Record restored successfully.');
            } catch (error) {
                notify.error(error.response?.data?.message || 'Unable to restore this record.');
                throw error;
            } finally {
                actionLoading.value = false;
            }
        },
    });
}

function permanentlyDelete(record) {
    confirmDelete({
        title: 'Permanently delete?',
        message: `Permanently remove "${record.name}" from ${record.module}?`,
        detail: 'This action cannot be undone. The record will be removed forever.',
        acceptLabel: 'Delete permanently',
        onAccept: async () => {
            actionLoading.value = true;
            try {
                await api.delete(`/deleted-data/${record.type}/${record.id}/force`);
                await load();
                notify.success('Record permanently deleted.');
            } catch (error) {
                notify.error(error.response?.data?.message || 'Unable to permanently delete this record.');
                throw error;
            } finally {
                actionLoading.value = false;
            }
        },
    });
}

onMounted(load);
</script>
