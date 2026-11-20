<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">Unit Employee Directory</h3>
                        <p class="shadcn-card-description">
                            Employees from other units who request or receive stock.
                            Issuances can only be recorded to active people in this
                            directory. Entries do not need a login account.
                        </p>
                    </div>
                    <UiButton @click="openCreate">Add Employee</UiButton>
                </div>
            </template>

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
                    :rows="15"
                    data-key="id"
                    class="rounded-md border border-[#a8b8d4]"
                >
                    <Column field="employee_number" header="Employee ID" />
                    <Column field="name" header="Name" />
                    <Column header="Unit">
                        <template #body="{ data }">{{ data.unit || '—' }}</template>
                    </Column>
                    <Column header="Position">
                        <template #body="{ data }">{{ data.position || '—' }}</template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <UiBadge :variant="data.is_active ? 'default' : 'outline'">
                                {{ data.is_active ? 'Active' : 'Inactive' }}
                            </UiBadge>
                        </template>
                    </Column>
                    <Column header="Account">
                        <template #body="{ data }">
                            <span class="text-xs text-[#4a6490]">{{ data.has_account ? 'Has login' : 'Directory only' }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <UiButton variant="ghost" size="sm" @click="openEdit(data)">Edit</UiButton>
                                <UiButton variant="ghost" size="sm" :disabled="saving" @click="toggleActive(data)">
                                    {{ data.is_active ? 'Deactivate' : 'Activate' }}
                                </UiButton>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="editingId ? 'Edit Employee' : 'Add Employee'"
            :style="{ width: '520px' }"
        >
            <form class="grid gap-4 pt-2" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Employee ID</label>
                    <InputText v-model="form.employee_number" class="w-full" placeholder="Leave blank to auto-generate" />
                    <p class="mt-1 text-xs text-[#4a6490]">Use the official employee number when available.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Name <span class="text-[#ce1126]">*</span></label>
                    <InputText v-model="form.name" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Unit <span class="text-[#ce1126]">*</span></label>
                    <Select
                        v-model="form.department_id"
                        :options="departments"
                        optionLabel="name"
                        optionValue="id"
                        filter
                        placeholder="Select unit"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Position</label>
                    <InputText v-model="form.position" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_active" binary inputId="directory-active" />
                    <label for="directory-active" class="text-sm text-[#00164d]">Active (can receive stock)</label>
                </div>
            </form>
            <template #footer>
                <UiButton variant="outline" @click="dialogVisible = false">Cancel</UiButton>
                <UiButton :loading="saving" @click="save">Save</UiButton>
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
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import UiCard from '../../components/ui/UiCard.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import TableFilters from '../../components/TableFilters.vue';
import { useNotify } from '../../composables/useNotify';
import { useTableFilters } from '../../composables/useTableFilters';
import api from '../../services/api';

const notify = useNotify();
const rows = ref([]);
const departments = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);

const form = reactive({
    employee_number: '',
    name: '',
    department_id: null,
    position: '',
    is_active: true,
});

const unitOptions = computed(() => [
    { label: 'All units', value: '' },
    ...departments.value.map((department) => ({ label: department.name, value: department.name })),
]);

const filterConfig = computed(() => [
    { key: 'search', type: 'search', label: 'Search', placeholder: 'Name, employee ID, position...', fields: ['name', 'employee_number', 'position', 'unit'] },
    { key: 'unit', type: 'select', label: 'Unit', field: 'unit', options: unitOptions.value },
    {
        key: 'active',
        type: 'custom',
        label: 'Status',
        default: 'active',
        options: [
            { label: 'Active only', value: 'active' },
            { label: 'Inactive only', value: 'inactive' },
            { label: 'All', value: 'all' },
        ],
        predicate: (row, value) => (value === 'all' ? true : value === 'active' ? row.is_active : !row.is_active),
    },
]);

const { filters, filteredItems: filteredRows, hasActiveFilters, resetFilters } = useTableFilters(rows, filterConfig);

function openCreate() {
    editingId.value = null;
    Object.assign(form, { employee_number: '', name: '', department_id: null, position: '', is_active: true });
    dialogVisible.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    Object.assign(form, {
        employee_number: row.employee_number || '',
        name: row.name || '',
        department_id: row.department_id,
        position: row.position || '',
        is_active: Boolean(row.is_active),
    });
    dialogVisible.value = true;
}

async function save() {
    if (!form.name.trim() || !form.department_id) {
        notify.warn('Enter the name and select the unit.');
        return;
    }

    saving.value = true;
    try {
        const payload = { ...form, employee_number: form.employee_number.trim() || null };
        if (editingId.value) {
            await api.put(`/employee-directory/${editingId.value}`, payload);
            notify.success('Directory entry updated. Past issuances keep the name and unit recorded at the time.');
        } else {
            await api.post('/employee-directory', payload);
            notify.success('Employee added to the directory.');
        }
        dialogVisible.value = false;
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save the directory entry.');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(row) {
    saving.value = true;
    try {
        await api.put(`/employee-directory/${row.id}/active`, { is_active: !row.is_active });
        notify.success(row.is_active ? `${row.name} deactivated.` : `${row.name} activated.`);
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to update status.');
    } finally {
        saving.value = false;
    }
}

async function load() {
    loading.value = true;
    try {
        const [directory, departmentRes] = await Promise.all([
            api.get('/employee-directory/list'),
            api.get('/departments/list'),
        ]);
        rows.value = directory.data;
        departments.value = departmentRes.data.data ?? departmentRes.data;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the employee directory.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
