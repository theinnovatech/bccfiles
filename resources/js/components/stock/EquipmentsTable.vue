<template>
    <div class="space-y-6">
        <div v-if="loading" class="rounded-md border border-[#a8b8d4] bg-white px-4 py-10 text-center text-sm text-[#4a6490]">
            Loading equipments...
        </div>

        <div
            v-else-if="!equipments.length"
            class="flex min-h-[220px] flex-col items-center justify-center rounded-md border border-[#a8b8d4] bg-white px-4 py-10 text-center"
        >
            <svg class="h-10 w-10 text-[#a8b8d4]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-4.162a2.25 2.25 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75V5.25A2.25 2.25 0 0011.25 7.5h1.5A2.25 2.25 0 0015 5.25V3.75M9 3.75h6" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#00164d]">No equipments yet</p>
            <p class="mt-1 text-xs text-[#4a6490]">Register equipment from the Registration page.</p>
        </div>

        <template v-else>
            <UiCard>
                <template #header>
                    <div>
                        <h3 class="shadcn-card-title">Registered Equipments</h3>
                        <p class="shadcn-card-description">
                            {{ equipments.length }} equipment{{ equipments.length === 1 ? '' : 's' }} on record, grouped by origin and remaining life span
                        </p>
                    </div>
                </template>

                <TableFilters
                    v-model="filters"
                    :filters="filterConfig"
                    :has-active-filters="hasActiveFilters"
                    :result-count="filteredEquipments.length"
                    @reset="resetFilters"
                />
            </UiCard>

            <UiCard
                v-for="table in equipmentTables"
                :key="table.key"
            >
                <template #header>
                    <div>
                        <h3 class="shadcn-card-title">{{ table.title }}</h3>
                        <p class="shadcn-card-description">
                            {{ table.rows.length }} listing{{ table.rows.length === 1 ? '' : 's' }}. {{ table.description }}
                        </p>
                    </div>
                </template>

                <div v-if="!table.rows.length" class="px-1 py-8 text-center">
                    <p class="text-sm font-medium text-[#00164d]">{{ table.emptyTitle }}</p>
                    <p class="mt-1 text-xs text-[#4a6490]">{{ table.emptyText }}</p>
                </div>

                <div v-else class="obims-table-wrap">
                    <DataTable
                        :value="table.rows"
                        paginator
                        :rows="10"
                        striped-rows
                        data-key="id"
                        class="rounded-md border border-[#a8b8d4] equipment-table table-row-clickable"
                        @row-click="onEquipmentRowClick"
                    >
                        <Column header="Barcode">
                            <template #body="{ data }">
                                {{ data.barcode || '—' }}
                            </template>
                        </Column>
                        <Column field="inventory_number" header="Inventory No.">
                            <template #body="{ data }">
                                {{ data.inventory_number || '—' }}
                            </template>
                        </Column>
                        <Column field="property_number" header="Property No." />
                        <Column field="name" header="Name" />
                        <Column v-if="table.showOrigin" header="Origin">
                            <template #body="{ data }">
                                <span
                                    class="equipment-origin"
                                    :class="
                                        equipmentOriginLabel(data) === 'Returned'
                                            ? 'equipment-origin-returned'
                                            : 'equipment-origin-fresh'
                                    "
                                >
                                    {{ equipmentOriginLabel(data) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Category">
                            <template #body="{ data }">
                                {{ data.category?.name || '—' }}
                            </template>
                        </Column>
                        <Column field="type" header="Type" />
                        <Column field="quantity" header="Qty" />
                        <Column header="Life Span">
                            <template #body="{ data }">
                                {{
                                    table.key === 'unusable'
                                        ? '0 yrs remaining'
                                        : formatEquipmentLifeSpan(data)
                                }}
                            </template>
                        </Column>
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
                        <Column header="Actions" :style="{ width: canManage ? '8.5rem' : '3.5rem' }">
                            <template #body="{ data }">
                                <div class="equipment-actions table-row-actions" @click.stop>
                                    <UiButton
                                        variant="ghost"
                                        size="icon"
                                        title="View equipment history"
                                        @click="viewEquipment(data)"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </UiButton>
                                    <UiButton
                                        v-if="canManage"
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
                                        v-if="canManage"
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
            </UiCard>
        </template>

    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Edit Equipment"
        :style="{ width: '640px' }"
    >
        <form class="grid gap-4 pt-2 md:grid-cols-2" @submit.prevent="saveEquipment">
            <div class="md:col-span-2">
                <p class="text-xs uppercase tracking-wide text-[#4a6490]">Origin</p>
                <span
                    class="equipment-origin mt-1"
                    :class="
                        equipmentOriginLabel(editingEquipment) === 'Returned'
                            ? 'equipment-origin-returned'
                            : 'equipment-origin-fresh'
                    "
                >
                    {{ equipmentOriginLabel(editingEquipment) }}
                </span>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Inventory No.</label>
                <InputText :model-value="form.inventory_number || '—'" class="w-full" readonly />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Property No.</label>
                <InputText :model-value="form.property_number || '—'" class="w-full" readonly />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Barcode</label>
                <InputText :model-value="form.barcode || 'No barcode'" class="w-full" readonly />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Life span remaining
                </label>
                <InputText
                    :model-value="formatEquipmentLifeSpan(editingEquipment)"
                    class="w-full"
                    readonly
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Name <span class="text-[#ce1126]">*</span>
                </label>
                <InputText v-model="form.name" class="w-full" required />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Category <span class="text-[#ce1126]">*</span>
                </label>
                <Select
                    v-model="form.equipment_category_id"
                    :options="categories"
                    option-label="name"
                    option-value="id"
                    class="w-full"
                    placeholder="Select category"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Type <span class="text-[#ce1126]">*</span>
                </label>
                <InputText v-model="form.type" class="w-full" required />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Qty <span class="text-[#ce1126]">*</span>
                </label>
                <InputNumber v-model="form.quantity" class="w-full" :min="1" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-[#00164d]">
                    Life Span (Years) <span class="text-[#ce1126]">*</span>
                </label>
                <InputNumber
                    v-model="form.life_span_years"
                    class="w-full"
                    :min="0"
                    :max="100"
                />
                <p class="mt-1 text-xs text-[#4a6490]">
                    For Returned equipment this is remaining years, not the original span.
                </p>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Description</label>
                <Textarea
                    v-model="form.description"
                    class="w-full"
                    rows="3"
                    placeholder="Brief description of the equipment"
                />
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Specs</label>
                <Textarea
                    v-model="form.specs"
                    class="w-full"
                    rows="3"
                    placeholder="Specifications, model, serial number, etc."
                />
            </div>
        </form>
        <template #footer>
            <UiButton variant="outline" @click="dialogVisible = false">Cancel</UiButton>
            <UiButton :loading="saving" @click="saveEquipment">Save Changes</UiButton>
        </template>
    </Dialog>

    <Dialog
        v-model:visible="viewDialogVisible"
        modal
        header="Equipment History"
        :style="{ width: '720px' }"
    >
        <div v-if="viewLoading" class="py-8 text-center text-sm text-[#4a6490]">
            Loading equipment history...
        </div>
        <div v-else-if="viewRecord" class="space-y-4 pt-2 text-sm text-[#00164d]">
            <div class="border border-[#a8b8d4] bg-[#f4f7fb] p-4">
                <div class="flex items-center gap-2">
                    <p class="text-xs uppercase tracking-wide text-[#4a6490]">Current listing</p>
                    <span
                        class="equipment-origin"
                        :class="
                            equipmentOriginLabel(viewRecord.equipment) === 'Returned'
                                ? 'equipment-origin-returned'
                                : 'equipment-origin-fresh'
                        "
                    >
                        {{ equipmentOriginLabel(viewRecord.equipment) }}
                    </span>
                </div>
                <p class="mt-1 text-base font-semibold">{{ viewRecord.equipment?.name || '—' }}</p>
                <p class="mt-0.5 text-xs text-[#4a6490]">
                    {{ viewRecord.equipment?.property_number || '—' }}
                    · {{ viewRecord.equipment?.inventory_number || '—' }}
                    · {{ formatEquipmentLifeSpan(viewRecord.equipment) }}
                </p>
            </div>

            <div v-if="!viewRecord.events?.length" class="py-6 text-center text-sm text-[#4a6490]">
                No registration, issuance, or return history yet.
            </div>

            <ol v-else class="equipment-history">
                <li
                    v-for="(event, index) in viewRecord.events"
                    :key="`${event.type}-${index}`"
                    class="equipment-history-item"
                >
                    <span
                        class="equipment-history-dot"
                        :class="`equipment-history-dot-${event.type}`"
                    />
                    <div class="equipment-history-card">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-semibold text-[#00164d]">{{ event.title }}</p>
                            <p class="text-xs text-[#4a6490]">{{ formatDateTime(event.date) }}</p>
                        </div>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div v-for="field in event.fields" :key="field.label">
                                <dt class="text-xs uppercase tracking-wide text-[#4a6490]">
                                    {{ field.label }}
                                </dt>
                                <dd class="mt-0.5 whitespace-pre-line font-medium">
                                    {{ formatHistoryValue(field) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ol>
        </div>
        <template #footer>
            <UiButton variant="outline" @click="viewDialogVisible = false">Close</UiButton>
        </template>
    </Dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import UiCard from '../ui/UiCard.vue';
import UiButton from '../ui/UiButton.vue';
import TableFilters from '../TableFilters.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import { confirmDelete } from '../../composables/confirm';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';
import {
    formatEquipmentLifeSpan,
    equipmentOriginLabel,
    hasNoRemainingLifeSpan,
} from '../../utils/equipmentLifeSpan';

const notify = useNotify();
const auth = useAuthStore();
const equipments = ref([]);
const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const viewDialogVisible = ref(false);
const viewLoading = ref(false);
const viewRecord = ref(null);
const editingEquipment = ref(null);

const canManage = computed(() => auth.isAdmin || auth.isSupplyOfficer);

const form = reactive({
    inventory_number: '',
    property_number: '',
    barcode: '',
    name: '',
    equipment_category_id: null,
    description: '',
    type: '',
    quantity: 1,
    life_span_years: null,
    specs: '',
});

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
        placeholder: 'Inventory no., property no., name, category, type...',
        fields: ['barcode', 'inventory_number', 'property_number', 'name', 'category.name', 'type', 'description', 'specs'],
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

const usableEquipments = computed(() =>
    filteredEquipments.value.filter((row) => !hasNoRemainingLifeSpan(row)),
);

const freshEquipments = computed(() =>
    usableEquipments.value.filter((row) => equipmentOriginLabel(row) !== 'Returned'),
);

const returnedEquipments = computed(() =>
    usableEquipments.value.filter((row) => equipmentOriginLabel(row) === 'Returned'),
);

const unusableEquipments = computed(() =>
    filteredEquipments.value.filter((row) => hasNoRemainingLifeSpan(row)),
);

const equipmentTables = computed(() => [
    {
        key: 'fresh',
        title: 'Fresh Equipments',
        description: 'Newly registered stock still within remaining life span.',
        rows: freshEquipments.value,
        showOrigin: false,
        emptyTitle: 'No fresh equipments',
        emptyText: 'Fresh listings with remaining life span will appear here.',
    },
    {
        key: 'returned',
        title: 'Returned Equipments',
        description: 'Used stock restocked from returns, still within remaining life span as of the date returned.',
        rows: returnedEquipments.value,
        showOrigin: false,
        emptyTitle: 'No returned equipments',
        emptyText: 'Returned listings with remaining life span will appear here.',
    },
    {
        key: 'unusable',
        title: 'Unusable Equipments (0 life span)',
        description: 'Remaining life span is 0 years, 0 months, and 0 days. These listings are not available for issue.',
        rows: unusableEquipments.value,
        showOrigin: true,
        emptyTitle: 'No unusable equipments',
        emptyText: 'Equipment whose remaining life span is 0 yrs 0 mos 0 days will appear here.',
    },
]);

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

function isTableActionClick(event) {
    const target = event?.originalEvent?.target;
    return Boolean(
        target?.closest?.('button, a, input, textarea, select, .table-row-actions'),
    );
}

function onEquipmentRowClick(event) {
    if (isTableActionClick(event) || !event?.data) {
        return;
    }

    viewEquipment(event.data);
}

async function viewEquipment(equipment) {
    viewDialogVisible.value = true;
    viewLoading.value = true;
    viewRecord.value = null;

    try {
        const { data } = await api.get(`/equipments/${equipment.id}/history`);
        viewRecord.value = data;
    } catch (error) {
        viewDialogVisible.value = false;
        notify.error(error.response?.data?.message || 'Unable to load equipment history.');
    } finally {
        viewLoading.value = false;
    }
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function formatHistoryValue(field) {
    const value = field?.value;
    if (value == null || value === '') {
        return '—';
    }

    const label = String(field.label || '').toLowerCase();
    if (!label.includes('date')) {
        return String(value);
    }

    const raw = String(value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw.slice(0, 10)) && raw.length <= 10) {
        const [year, month, day] = raw.split('-');
        return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString();
    }

    return formatDateTime(value);
}

function editEquipment(equipment) {
    editingEquipment.value = equipment;
    form.inventory_number = equipment.inventory_number || '';
    form.property_number = equipment.property_number || '';
    form.barcode = equipment.barcode || '';
    form.name = equipment.name || '';
    form.equipment_category_id = equipment.equipment_category_id;
    form.description = equipment.description || '';
    form.type = equipment.type || '';
    form.quantity = equipment.quantity ?? 1;
    form.life_span_years = equipment.life_span_years ?? null;
    form.specs = equipment.specs || '';
    dialogVisible.value = true;
}

async function saveEquipment() {
    if (!editingEquipment.value) {
        return;
    }

    if (!form.name?.trim()) {
        notify.warn('Please enter the equipment name.');
        return;
    }

    if (!form.equipment_category_id) {
        notify.warn('Please select an equipment category.');
        return;
    }

    if (!form.type?.trim()) {
        notify.warn('Please enter the equipment type.');
        return;
    }

    if (!form.quantity || form.quantity < 1) {
        notify.warn('Please enter a quantity of at least 1.');
        return;
    }

    if (form.life_span_years == null || form.life_span_years < 0) {
        notify.warn('Please enter the equipment life span in years.');
        return;
    }

    saving.value = true;

    try {
        await api.put(`/equipments/${editingEquipment.value.id}`, {
            barcode: form.barcode || null,
            name: form.name,
            equipment_category_id: form.equipment_category_id,
            description: form.description || null,
            type: form.type,
            quantity: form.quantity,
            life_span_years: form.life_span_years,
            specs: form.specs || null,
        });
        notify.success('Equipment updated.');
        dialogVisible.value = false;
        await loadEquipments();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to update equipment.');
    } finally {
        saving.value = false;
    }
}

async function loadCategories() {
    try {
        const { data } = await api.get('/equipment-categories/list');
        categories.value = (Array.isArray(data) ? data : data?.data ?? []).filter(
            (category) => category.is_active !== false,
        );
    } catch {
        notify.error('Unable to load equipment categories.');
    }
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

onMounted(async () => {
    await Promise.all([loadEquipments(), loadCategories()]);
});
</script>

<style scoped>
.equipment-table :deep(.p-datatable-tbody > tr > td) {
    font-size: 0.875rem;
    color: #00164d;
}

.equipment-origin {
    display: inline-flex;
    align-items: center;
    border: 1px solid #a8b8d4;
    padding: 0.125rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.equipment-origin-fresh {
    background: #f4f7fb;
    color: #00164d;
}

.equipment-origin-returned {
    background: #fff8e8;
    color: #8a5a00;
    border-color: #e0c48a;
}

.equipment-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.equipment-history {
    margin: 0;
    padding: 0;
    list-style: none;
}

.equipment-history-item {
    position: relative;
    padding-bottom: 1rem;
    padding-left: 1.5rem;
}

.equipment-history-item:last-child {
    padding-bottom: 0;
}

.equipment-history-item::before {
    content: '';
    position: absolute;
    top: 0.7rem;
    bottom: 0;
    left: 0.35rem;
    width: 1px;
    background: #d7e0ef;
}

.equipment-history-item:last-child::before {
    display: none;
}

.equipment-history-dot {
    position: absolute;
    top: 0.45rem;
    left: 0;
    width: 0.7rem;
    height: 0.7rem;
    border: 2px solid #00164d;
    border-radius: 999px;
    background: #fff;
}

.equipment-history-dot-registered {
    border-color: #0f5132;
    background: #e8f6ee;
}

.equipment-history-dot-issued {
    border-color: #00164d;
    background: #e8eef8;
}

.equipment-history-dot-returned {
    border-color: #8a5a00;
    background: #fff8e8;
}

.equipment-history-dot-restocked {
    border-color: #8a5a00;
    background: #fff8e8;
}

.equipment-history-card {
    border: 1px solid #a8b8d4;
    padding: 0.85rem 1rem;
    background: #fff;
}
</style>
