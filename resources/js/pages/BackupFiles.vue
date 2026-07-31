<template>
    <UiCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">Backup Files</h3>
                    <p class="shadcn-card-description">
                        Create and download SQL backups of the OBIMS database.
                    </p>
                </div>
                <UiButton :loading="creating" @click="createBackup">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Create Backup
                </UiButton>
            </div>
        </template>

        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
            Backups include all database tables and are stored securely on the server.
            Download a file to keep an offline copy.
        </div>

        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredBackups.length"
            @reset="resetFilters"
        />

        <DataTable
            :value="filteredBackups"
            :loading="loading"
            paginator
            :rows="10"
            class="rounded-md border border-[#a8b8d4]"
        >
            <Column field="filename" header="File Name" />
            <Column header="Size">
                <template #body="{ data }">{{ formatSize(data.size) }}</template>
            </Column>
            <Column header="Created At">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 12rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-1">
                        <UiButton
                            variant="ghost"
                            size="sm"
                            :disabled="actionLoading"
                            @click="downloadBackup(data)"
                        >
                            Download
                        </UiButton>
                        <UiButton
                            variant="ghost"
                            size="sm"
                            :disabled="actionLoading"
                            @click="deleteBackup(data)"
                        >
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
import { confirmDelete } from '../composables/confirm';
import { useNotify } from '../composables/useNotify';
import { useTableFilters } from '../composables/useTableFilters';
import api from '../services/api';

const notify = useNotify();
const backups = ref([]);
const loading = ref(false);
const creating = ref(false);
const actionLoading = ref(false);

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'File name...',
        fields: ['filename'],
    },
]);

const { filters, filteredItems: filteredBackups, hasActiveFilters, resetFilters } = useTableFilters(
    backups,
    filterConfig,
);

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function formatSize(bytes) {
    const size = Number(bytes) || 0;

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/backups/list');
        backups.value = data.backups || [];
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load backup files.');
    } finally {
        loading.value = false;
    }
}

async function createBackup() {
    creating.value = true;
    try {
        const { data } = await api.post('/backups');
        notify.success(data.message || 'Database backup created successfully.');
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to create database backup.');
    } finally {
        creating.value = false;
    }
}

async function downloadBackup(backup) {
    actionLoading.value = true;
    try {
        const { data } = await api.get(`/backups/${encodeURIComponent(backup.filename)}/download`, {
            responseType: 'blob',
        });

        const url = window.URL.createObjectURL(new Blob([data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = backup.filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to download backup file.');
    } finally {
        actionLoading.value = false;
    }
}

function deleteBackup(backup) {
    confirmDelete({
        title: 'Delete backup?',
        message: `Delete backup file "${backup.filename}"?`,
        detail: 'This action cannot be undone.',
        onAccept: async () => {
            actionLoading.value = true;
            try {
                await api.delete(`/backups/${encodeURIComponent(backup.filename)}`);
                notify.success('Backup file deleted.');
                await load();
            } catch (error) {
                notify.error(error.response?.data?.message || 'Unable to delete backup file.');
                throw error;
            } finally {
                actionLoading.value = false;
            }
        },
    });
}

onMounted(load);
</script>
