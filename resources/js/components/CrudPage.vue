<template>
    <UiCard v-if="!embedded" :title="title">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">{{ title }}</h3>
                    <p class="shadcn-card-description">Manage {{ title.toLowerCase() }} records.</p>
                </div>
                <UiButton v-if="canCreate" @click="openDialog()">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add New
                </UiButton>
            </div>
        </template>

        <CrudPageBody
            :columns="columns"
            :fields="fields"
            :can-create="canCreate"
            :filter-config="filterConfig"
            :filters="filters"
            :filtered-rows="filteredRows"
            :has-active-filters="hasActiveFilters"
            :loading="loading"
            :dialog-visible="dialogVisible"
            :dialog-title="dialogTitle"
            :form="form"
            :saving="saving"
            @update:filters="updateFilters"
            @reset-filters="resetFilters"
            @open-dialog="openDialog"
            @remove="remove"
            @save="save"
            @update:dialog-visible="(value) => (dialogVisible = value)"
        />
    </UiCard>

    <div v-else class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#eef2fa] pb-4">
            <div>
                <h4 class="stock-op-form-title">{{ title }}</h4>
                <p class="stock-op-form-desc">Manage {{ title.toLowerCase() }} records.</p>
            </div>
            <UiButton v-if="canCreate" @click="openDialog()">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add New
            </UiButton>
        </div>

        <CrudPageBody
            :columns="columns"
            :fields="fields"
            :can-create="canCreate"
            :filter-config="filterConfig"
            :filters="filters"
            :filtered-rows="filteredRows"
            :has-active-filters="hasActiveFilters"
            :loading="loading"
            :dialog-visible="dialogVisible"
            :dialog-title="dialogTitle"
            :form="form"
            :saving="saving"
            @update:filters="updateFilters"
            @reset-filters="resetFilters"
            @open-dialog="openDialog"
            @remove="remove"
            @save="save"
            @update:dialog-visible="(value) => (dialogVisible = value)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import UiCard from './ui/UiCard.vue';
import UiButton from './ui/UiButton.vue';
import CrudPageBody from './CrudPageBody.vue';
import { confirmDelete } from '../composables/confirm';
import { useNotify } from '../composables/useNotify';
import { useTableFilters } from '../composables/useTableFilters';
import api from '../services/api';

const props = defineProps({
    title: String,
    endpoint: String,
    columns: Array,
    fields: Array,
    canCreate: { type: Boolean, default: true },
    extraFilters: { type: Array, default: () => [] },
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const rows = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const form = reactive({});

const hasActiveField = computed(() => props.fields.some((field) => field.key === 'is_active'));

const filterConfig = computed(() => {
    const config = [
        {
            key: 'search',
            type: 'search',
            label: 'Search',
            placeholder: `Search ${props.title.toLowerCase()}s...`,
            fields: props.columns.map((column) => column.field).filter(Boolean),
        },
    ];

    if (hasActiveField.value) {
        config.push({
            key: 'status',
            type: 'boolean',
            label: 'Status',
            field: 'is_active',
            options: [
                { label: 'All statuses', value: '' },
                { label: 'Active only', value: 'true' },
                { label: 'Inactive only', value: 'false' },
            ],
        });
    }

    return [...config, ...props.extraFilters];
});

const { filters, filteredItems: filteredRows, hasActiveFilters, resetFilters } = useTableFilters(rows, filterConfig);

const dialogTitle = computed(() => (editingId.value ? `Edit ${props.title}` : `Add ${props.title}`));

function updateFilters(value) {
    filters.value = value;
}

function recordLabel(data) {
    return data.name || data.code || data.item_name || `#${data.id}`;
}

function resetForm(data = null) {
    props.fields.forEach((field) => {
        form[field.key] = data?.[field.key] ?? (field.type === 'checkbox' ? true : '');
    });
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get(`${props.endpoint}/list`);
        rows.value = data.data ?? data;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load records.');
    } finally {
        loading.value = false;
    }
}

function openDialog(data = null) {
    editingId.value = data?.id ?? null;
    resetForm(data);
    dialogVisible.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editingId.value) {
            await api.put(`${props.endpoint}/${editingId.value}`, form);
            notify.success(`${props.title} updated successfully.`);
        } else {
            await api.post(props.endpoint, form);
            notify.success(`${props.title} created successfully.`);
        }
        dialogVisible.value = false;
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save this record.');
    } finally {
        saving.value = false;
    }
}

function remove(data) {
    confirmDelete({
        title: `Delete ${props.title.toLowerCase()}?`,
        message: `Remove "${recordLabel(data)}" from the system?`,
        detail: 'The record will be moved to Deleted Data and can be restored later.',
        onAccept: async () => {
            try {
                await api.delete(`${props.endpoint}/${data.id}`);
                await load();
                notify.success(`${props.title} deleted successfully.`);
            } catch (error) {
                notify.error(error.response?.data?.message || 'Unable to delete this record.');
                throw error;
            }
        },
    });
}

onMounted(load);
</script>
