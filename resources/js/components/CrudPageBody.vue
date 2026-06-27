<template>
    <TableFilters
        :model-value="filters"
        :filters="filterConfig"
        :has-active-filters="hasActiveFilters"
        :result-count="filteredRows.length"
        @update:model-value="emit('update:filters', $event)"
        @reset="emit('reset-filters')"
    />

    <div class="obims-table-wrap">
        <DataTable :value="filteredRows" :loading="loading" paginator :rows="10" class="rounded-md border border-[#c8d6ef]">
            <Column v-for="col in columns" :key="col.field" :field="col.field" :header="col.header">
                <template v-if="col.field === 'is_active'" #body="{ data }">
                    <UiBadge :variant="data.is_active ? 'default' : 'outline'">{{ data.is_active ? 'Active' : 'Inactive' }}</UiBadge>
                </template>
            </Column>
            <Column v-if="canCreate" header="Actions" style="width: 8rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <UiButton variant="ghost" size="icon" @click="emit('open-dialog', data)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                        </UiButton>
                        <UiButton variant="ghost" size="icon" @click="emit('remove', data)">
                            <svg class="h-4 w-4 text-[#ce1126]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </UiButton>
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        :visible="dialogVisible"
        modal
        :header="dialogTitle"
        :style="{ width: '480px' }"
        @update:visible="emit('update:dialog-visible', $event)"
    >
        <div class="space-y-4">
            <div v-for="field in fields" :key="field.key" class="space-y-2">
                <label class="text-sm font-medium text-[#002a7a]">{{ field.label }}</label>
                <InputText v-if="field.type !== 'textarea' && field.type !== 'checkbox'" v-model="form[field.key]" class="w-full" />
                <Textarea v-else-if="field.type === 'textarea'" v-model="form[field.key]" class="w-full" rows="3" />
                <div v-else class="flex items-center gap-2">
                    <Checkbox v-model="form[field.key]" binary :inputId="field.key" />
                    <label :for="field.key" class="text-sm text-[#5b7fbf]">Enabled</label>
                </div>
            </div>
        </div>
        <template #footer>
            <UiButton variant="outline" @click="emit('update:dialog-visible', false)">Cancel</UiButton>
            <UiButton :loading="saving" @click="emit('save')">Save</UiButton>
        </template>
    </Dialog>
</template>

<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Checkbox from 'primevue/checkbox';
import UiButton from './ui/UiButton.vue';
import UiBadge from './ui/UiBadge.vue';
import TableFilters from './TableFilters.vue';

defineProps({
    columns: Array,
    fields: Array,
    canCreate: Boolean,
    filterConfig: Array,
    filters: Object,
    filteredRows: Array,
    hasActiveFilters: Boolean,
    loading: Boolean,
    dialogVisible: Boolean,
    dialogTitle: String,
    form: Object,
    saving: Boolean,
});

const emit = defineEmits([
    'update:filters',
    'reset-filters',
    'open-dialog',
    'remove',
    'save',
    'update:dialog-visible',
]);
</script>
