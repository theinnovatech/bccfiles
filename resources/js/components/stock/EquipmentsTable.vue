<template>
    <UiCard>
        <template #header>
            <div>
                <h3 class="shadcn-card-title">Registered Equipments</h3>
                <p class="shadcn-card-description">
                    {{ equipments.length }} equipment{{ equipments.length === 1 ? '' : 's' }} on record
                </p>
            </div>
        </template>

        <div v-if="loading" class="px-4 py-10 text-center text-sm text-[#5b7fbf]">
            Loading equipments...
        </div>

        <div v-else-if="!equipments.length" class="flex min-h-[220px] flex-col items-center justify-center px-4 py-10 text-center">
            <svg class="h-10 w-10 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-4.162a2.25 2.25 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75V5.25A2.25 2.25 0 0011.25 7.5h1.5A2.25 2.25 0 0015 5.25V3.75M9 3.75h6" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">No equipments yet</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">Register equipment from Stock Operations.</p>
        </div>

        <template v-else>
            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredEquipments.length"
                @reset="resetFilters"
            />

            <div class="obims-table-wrap">
                <DataTable
                    :value="filteredEquipments"
                    paginator
                    :rows="10"
                    striped-rows
                    class="rounded-md border border-[#c8d6ef] equipment-table"
                >
                    <Column header="Barcode">
                        <template #body="{ data }">
                            {{ data.barcode || '—' }}
                        </template>
                    </Column>
                    <Column field="property_number" header="Property No." />
                    <Column field="name" header="Name" />
                    <Column header="Category">
                        <template #body="{ data }">
                            {{ data.category?.name || '—' }}
                        </template>
                    </Column>
                    <Column field="type" header="Type" />
                    <Column field="quantity" header="Qty" />
                    <Column header="Description">
                        <template #body="{ data }">
                            <span class="line-clamp-2">{{ data.description || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Specs">
                        <template #body="{ data }">
                            <span class="line-clamp-2">{{ data.specs || '—' }}</span>
                        </template>
                    </Column>
                    <Column v-if="canManage" header="Actions" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="equipment-actions">
                                <UiButton
                                    variant="ghost"
                                    size="icon"
                                    title="Edit equipment"
                                    @click="editEquipment(data)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                </UiButton>
                                <UiButton
                                    variant="ghost"
                                    size="icon"
                                    title="Delete equipment"
                                    @click="confirmRemove(data)"
                                >
                                    <svg class="h-4 w-4 text-[#ce1126]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </UiButton>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </template>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import TableFilters from '../TableFilters.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import { confirmDelete } from '../../composables/confirm';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';

const notify = useNotify();
const auth = useAuthStore();
const equipments = ref([]);
const loading = ref(false);

const canManage = computed(() => auth.isAdmin || auth.isSupplyOfficer);

const categoryOptions = computed(() => {
    const names = [...new Set(equipments.value.map((item) => item.category?.name).filter(Boolean))].sort();

    return [
        { label: 'All categories', value: '' },
        ...names.map((name) => ({ label: name, value: name })),
    ];
});

const typeOptions = computed(() => {
    const types = [...new Set(equipments.value.map((item) => item.type).filter(Boolean))].sort();

    return [
        { label: 'All types', value: '' },
        ...types.map((type) => ({ label: type, value: type })),
    ];
});

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Property no., name, category, type...',
        fields: ['barcode', 'property_number', 'name', 'category.name', 'type', 'description', 'specs'],
    },
    {
        key: 'category',
        type: 'select',
        label: 'Category',
        field: 'category.name',
        match: (row) => row.category?.name,
        options: categoryOptions.value,
    },
    {
        key: 'type',
        type: 'select',
        label: 'Type',
        field: 'type',
        options: typeOptions.value,
    },
]);

const { filters, filteredItems: filteredEquipments, hasActiveFilters, resetFilters } = useTableFilters(
    equipments,
    filterConfig,
);

async function loadEquipments() {
    loading.value = true;

    try {
        const { data } = await api.get('/equipments/list');
        equipments.value = data;
    } catch {
        notify.error('Unable to load equipments.');
    } finally {
        loading.value = false;
    }
}

function editEquipment(equipment) {
    window.location.href = `/stock/operations?tab=equipments&edit=${equipment.id}`;
}

function confirmRemove(equipment) {
    confirmDelete({
        title: 'Delete equipment?',
        message: `Remove "${equipment.name}" from the system?`,
        detail: 'The equipment will be moved to Deleted Data and can be restored later.',
        onAccept: async () => {
            try {
                await api.delete(`/equipments/${equipment.id}`);
                notify.success('Equipment deleted.');
                await loadEquipments();
            } catch (error) {
                notify.error(error.response?.data?.message || 'Unable to delete equipment.');
                throw error;
            }
        },
    });
}

onMounted(loadEquipments);
</script>

<style scoped>
.equipment-table :deep(.p-datatable-thead > tr > th) {
    background: #eef2fa;
    color: #002a7a;
    font-size: 0.8125rem;
}

.equipment-table :deep(.p-datatable-tbody > tr > td) {
    font-size: 0.875rem;
    color: #002a7a;
}

.equipment-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
</style>
