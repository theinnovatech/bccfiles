<template>
    <UiCard title="Activity Logs" description="Track user actions across the system.">
        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredLogs.length"
            @reset="resetFilters"
        />

        <DataTable :value="filteredLogs" :loading="loading" paginator :rows="15" class="rounded-md border border-[#c8d6ef]">
            <Column header="Time"><template #body="{ data }">{{ formatDate(data.created_at) }}</template></Column>
            <Column header="User"><template #body="{ data }">{{ data.user?.name || 'System' }}</template></Column>
            <Column field="action" header="Action" />
            <Column field="module" header="Module" />
            <Column field="description" header="Description" />
        </DataTable>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import UiCard from '../components/ui/UiCard.vue';
import TableFilters from '../components/TableFilters.vue';
import { useNotify } from '../composables/useNotify';
import { useTableFilters } from '../composables/useTableFilters';
import api from '../services/api';

const notify = useNotify();
const logs = ref([]);
const loading = ref(false);

const moduleOptions = computed(() => {
    const modules = [...new Set(logs.value.map((log) => log.module).filter(Boolean))].sort();
    return [{ label: 'All modules', value: '' }, ...modules.map((module) => ({ label: module, value: module }))];
});

const actionOptions = computed(() => {
    const actions = [...new Set(logs.value.map((log) => log.action).filter(Boolean))].sort();
    return [{ label: 'All actions', value: '' }, ...actions.map((action) => ({ label: action, value: action }))];
});

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'User, action, module, description...',
        fields: ['user.name', 'action', 'module', 'description'],
    },
    {
        key: 'module',
        type: 'select',
        label: 'Module',
        field: 'module',
        options: moduleOptions.value,
    },
    {
        key: 'action',
        type: 'select',
        label: 'Action',
        field: 'action',
        options: actionOptions.value,
    },
]);

const { filters, filteredItems: filteredLogs, hasActiveFilters, resetFilters } = useTableFilters(logs, filterConfig);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await api.get('/activity-logs/list');
        logs.value = data.data ?? data;

        const module = new URLSearchParams(window.location.search).get('module');
        if (module) {
            filters.value.module = module;
        }
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load activity logs.');
    } finally {
        loading.value = false;
    }
});
</script>
