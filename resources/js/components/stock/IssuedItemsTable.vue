<template>
    <UiCard title="Issued Stock Items" description="Items issued from approved supply requests.">
        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredRows.length"
            @reset="resetFilters"
        />

        <div class="obims-table-wrap">
            <DataTable
                :value="filteredRows"
                :loading="loading"
                paginator
                :rows="10"
                class="rounded-md border border-[#c8d6ef]"
            >
                <Column field="item_name" header="Item" />
                <Column header="Department">
                    <template #body="{ data }">{{ data.department }}</template>
                </Column>
                <Column field="barcode" header="Barcode" />
                <Column field="quantity" header="Qty Issued" />
                <Column field="issuance_number" header="Issuance No." />
                <Column field="issued_by" header="Issued By" />
                <Column field="received_by" header="Received By" />
                <Column header="Date">
                    <template #body="{ data }">{{ formatDate(data.issued_date) }}</template>
                </Column>
            </DataTable>
        </div>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import UiCard from '../ui/UiCard.vue';
import TableFilters from '../TableFilters.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import api from '../../services/api';

const notify = useNotify();
const issuances = ref([]);
const loading = ref(false);

const issuedItems = computed(() => {
    const rows = [];

    for (const issuance of issuances.value) {
        for (const detail of issuance.details ?? []) {
            if (! detail.item_id && ! detail.item) {
                continue;
            }

            rows.push({
                id: `${issuance.id}-${detail.id}`,
                item_name: detail.item?.item_name ?? '—',
                barcode: detail.barcode ?? detail.item?.barcode ?? '—',
                quantity: detail.quantity,
                issuance_number: issuance.issuance_number,
                department: issuance.request?.department?.name
                    ?? issuance.receiver?.department?.name
                    ?? '—',
                received_by: issuance.receiver?.name ?? '—',
                issued_by: issuance.issuer?.name ?? '—',
                issued_date: issuance.issued_date,
            });
        }
    }

    return rows;
});

const departmentOptions = computed(() => {
    const departments = [...new Set(issuedItems.value.map((row) => row.department).filter((name) => name && name !== '—'))].sort();

    return [{ label: 'All departments', value: '' }, ...departments.map((name) => ({ label: name, value: name }))];
});

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Item, barcode, issuance no., department...',
        fields: ['item_name', 'barcode', 'issuance_number', 'department', 'received_by', 'issued_by'],
    },
    {
        key: 'department',
        type: 'select',
        label: 'Department',
        field: 'department',
        options: departmentOptions.value,
    },
]);

const { filters, filteredItems: filteredRows, hasActiveFilters, resetFilters } = useTableFilters(issuedItems, filterConfig);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}

async function loadIssuances() {
    loading.value = true;

    try {
        const { data } = await api.get('/issuances/list');
        issuances.value = data.data ?? data;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load issued items.');
    } finally {
        loading.value = false;
    }
}

onMounted(loadIssuances);
</script>
