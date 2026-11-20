<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label class="block text-sm font-medium text-[#00164d]">
                Received By <span class="text-[#ce1126]">*</span>
            </label>
            <div class="flex flex-wrap items-center gap-2">
                <label v-if="selfEmployee" class="inline-flex items-center gap-1.5 text-xs text-[#4a6490]">
                    <Checkbox v-model="useSelf" binary inputId="recipient-self" :disabled="disabled" />
                    <span>I am the recipient</span>
                </label>
                <button
                    v-if="canManageDirectory && !useSelf"
                    type="button"
                    class="text-xs font-medium text-[#001f6b] underline-offset-2 hover:underline"
                    :disabled="disabled"
                    @click="openQuickAdd"
                >
                    + Add to directory
                </button>
            </div>
        </div>

        <AutoComplete
            v-if="!useSelf"
            v-model="query"
            :suggestions="suggestions"
            optionLabel="label"
            forceSelection
            dropdown
            completeOnFocus
            placeholder="Search the employee directory (name, employee ID, unit)"
            class="w-full"
            inputClass="w-full"
            :disabled="disabled"
            @complete="search"
            @option-select="select($event.value)"
            @clear="clear"
        />

        <div
            v-if="resolved"
            class="grid gap-2 rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3 text-sm sm:grid-cols-4"
            aria-live="polite"
        >
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]">Name</p>
                <p class="font-medium text-[#00164d]">{{ resolved.name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]">Unit</p>
                <p class="font-medium text-[#00164d]">{{ resolved.unit || '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]">Designation</p>
                <p class="font-medium text-[#00164d]">{{ resolved.position || '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]">Employee ID</p>
                <p class="font-medium text-[#00164d]">{{ resolved.employee_number || '—' }}</p>
            </div>
        </div>
        <p class="text-xs text-[#4a6490]">
            Name, unit and designation come from the employee directory (read-only here) and are checked again by
            the server when you save. They are kept on the issuance as recorded, so later directory changes do not
            alter issued documents.
        </p>

        <Dialog v-model:visible="quickAddVisible" modal header="Add to Employee Directory" :style="{ width: '440px' }">
            <div class="grid gap-3 pt-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Name <span class="text-[#ce1126]">*</span></label>
                    <InputText v-model="quickAdd.name" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Unit <span class="text-[#ce1126]">*</span></label>
                    <Select v-model="quickAdd.department_id" :options="departments" optionLabel="name" optionValue="id" filter class="w-full" placeholder="Select unit" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Employee ID</label>
                    <InputText v-model="quickAdd.employee_number" class="w-full" placeholder="Leave blank to auto-generate" />
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="quickAddVisible = false">Cancel</UiButton>
                <UiButton :loading="savingQuickAdd" @click="saveQuickAdd">Add and select</UiButton>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AutoComplete from 'primevue/autocomplete';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import UiButton from './ui/UiButton.vue';
import { useAuthStore } from '../stores/auth';
import { useNotify } from '../composables/useNotify';
import api from '../services/api';

const props = defineProps({
    modelValue: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const auth = useAuthStore();
const notify = useNotify();
const employees = ref([]);
const departments = ref([]);
const suggestions = ref([]);
const query = ref(null);
const useSelf = ref(false);
const quickAddVisible = ref(false);
const savingQuickAdd = ref(false);
const quickAdd = reactive({ name: '', department_id: null, employee_number: '' });

const canManageDirectory = computed(
    () => auth.isAdmin || auth.isSupplyOfficer || (auth.user?.allowed_pages || []).includes('employee-directory'),
);

const selfEmployee = computed(() => {
    const id = auth.user?.employee_id;
    return id ? employees.value.find((employee) => employee.id === id) ?? null : null;
});

const resolved = computed(() => {
    if (useSelf.value) {
        return selfEmployee.value;
    }

    return props.modelValue?.employee ?? null;
});

watch(useSelf, (value) => {
    if (value && selfEmployee.value) {
        emit('update:modelValue', { source: 'self', employee: selfEmployee.value });
    } else {
        emit('update:modelValue', null);
        query.value = null;
    }
});

watch(query, (value) => {
    // Typing over a selection invalidates it until a directory entry is picked again.
    if (typeof value === 'string' && props.modelValue?.source === 'directory') {
        emit('update:modelValue', null);
    }
});

watch(
    () => props.modelValue,
    (value) => {
        if (!value) {
            if (!useSelf.value) {
                query.value = null;
            }
            return;
        }
        if (value.source === 'directory' && value.employee) {
            query.value = withLabel(value.employee);
        }
    },
);

function withLabel(employee) {
    return {
        ...employee,
        label: `${employee.name} · ${employee.unit || 'No unit'}${employee.employee_number ? ` · ${employee.employee_number}` : ''}`,
    };
}

function search(event) {
    const term = String(event.query || '').trim().toLowerCase();
    const options = employees.value.map(withLabel);
    suggestions.value = term
        ? options.filter((employee) => employee.label.toLowerCase().includes(term))
        : options;
}

function select(employee) {
    if (!employee?.id) {
        return;
    }
    emit('update:modelValue', { source: 'directory', employee });
}

function clear() {
    emit('update:modelValue', null);
}

function openQuickAdd() {
    Object.assign(quickAdd, { name: '', department_id: null, employee_number: '' });
    quickAddVisible.value = true;
}

async function saveQuickAdd() {
    if (!quickAdd.name.trim() || !quickAdd.department_id) {
        notify.warn('Enter the name and select the unit.');
        return;
    }

    savingQuickAdd.value = true;
    try {
        const { data } = await api.post('/employee-directory', {
            name: quickAdd.name.trim(),
            department_id: quickAdd.department_id,
            employee_number: quickAdd.employee_number.trim() || null,
            is_active: true,
        });
        employees.value = [...employees.value, data].sort((a, b) => a.name.localeCompare(b.name));
        const employee = withLabel(data);
        query.value = employee;
        emit('update:modelValue', { source: 'directory', employee });
        quickAddVisible.value = false;
        notify.success(`${data.name} was added to the employee directory.`);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to add the employee.');
    } finally {
        savingQuickAdd.value = false;
    }
}

async function reload() {
    const [directory, departmentRes] = await Promise.all([
        api.get('/employee-directory/list', { params: { active: 1 } }),
        canManageDirectory.value ? api.get('/departments/list') : Promise.resolve({ data: [] }),
    ]);
    employees.value = directory.data;
    departments.value = departmentRes.data.data ?? departmentRes.data;
}

function reset() {
    useSelf.value = false;
    query.value = null;
}

defineExpose({ reload, reset });

onMounted(async () => {
    try {
        await reload();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the employee directory.');
    }
});
</script>
