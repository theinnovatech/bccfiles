<template>
    <div class="space-y-6">
    <div
        v-if="restoreLock"
        class="rounded-lg border p-3 text-sm"
        :class="restoreLock.state === 'failed' ? 'border-[#ce1126] bg-[#fff5f5] text-[#00164d]' : 'border-amber-300 bg-amber-50 text-amber-900'"
        role="alert"
    >
        <template v-if="restoreLock.state === 'failed'">
            <strong>Restore failed and needs recovery.</strong> Inventory writes are blocked.
            Restore the recovery backup <strong>{{ restoreLock.recovery_backup }}</strong> below, or run
            <code>php artisan obims:restore {{ restoreLock.recovery_backup }} --force</code> on the server.
        </template>
        <template v-else>
            <strong>A restore is in progress</strong> ({{ restoreLock.backup }}, started by {{ restoreLock.user }}). Inventory writes are blocked until it finishes.
        </template>
    </div>
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
            Download a file to keep an offline copy. <strong>Restore</strong> replaces the current data
            with a backup after validating it; a fresh recovery backup is always created first.
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
            <Column header="File Name">
                <template #body="{ data }">
                    <span>{{ data.filename }}</span>
                    <UiBadge v-if="data.is_recovery" variant="outline" class="ml-2">Recovery</UiBadge>
                </template>
            </Column>
            <Column header="Size">
                <template #body="{ data }">{{ formatSize(data.size) }}</template>
            </Column>
            <Column header="Created At">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 16rem">
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
                            :disabled="actionLoading || restoring"
                            @click="startRestore(data)"
                        >
                            Restore
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

    <UiCard title="Restore history" description="Who initiated each restore and whether it succeeded. Kept outside the database so it survives the restore.">
        <DataTable :value="restoreLog" paginator :rows="5" class="rounded-md border border-[#a8b8d4]">
            <Column header="Started">
                <template #body="{ data }">{{ formatDate(data.started_at) }}</template>
            </Column>
            <Column field="backup" header="Backup" />
            <Column header="Initiated by">
                <template #body="{ data }">{{ data.initiated_by?.name }} <span class="text-xs text-[#4a6490]">{{ data.initiated_by?.email }}</span></template>
            </Column>
            <Column header="Outcome">
                <template #body="{ data }">
                    <UiBadge :variant="data.status === 'succeeded' ? 'default' : data.status === 'running' ? 'secondary' : 'destructive'">
                        {{ outcomeLabel(data.status) }}
                    </UiBadge>
                    <p v-if="data.error" class="mt-1 max-w-md text-xs text-[#ce1126]">{{ data.error }}</p>
                </template>
            </Column>
            <Column field="recovery_backup" header="Recovery backup" />
        </DataTable>
    </UiCard>

    <Dialog v-model:visible="restoreVisible" modal header="Restore database backup" :style="{ width: '640px' }" :closable="!restoring">
        <div v-if="inspecting" class="py-8 text-center text-sm text-[#4a6490]">Validating backup…</div>
        <div v-else-if="inspection" class="space-y-4 pt-2 text-sm text-[#00164d]">
            <div class="grid gap-3 rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3 sm:grid-cols-2">
                <div><p class="restore-label">Backup</p><p class="font-medium">{{ inspection.filename }}</p></div>
                <div><p class="restore-label">Backup timestamp</p><p class="font-medium">{{ inspection.backup_timestamp_label || 'Unknown' }}</p></div>
                <div><p class="restore-label">Format</p><p>{{ inspection.format || 'Unknown' }} · {{ formatSize(inspection.size) }}</p></div>
                <div><p class="restore-label">Validation</p>
                    <UiBadge :variant="inspection.valid ? 'default' : 'destructive'">{{ inspection.valid ? 'Valid OBIMS backup' : 'Not restorable' }}</UiBadge>
                </div>
            </div>

            <ul v-if="inspection.errors?.length" class="list-disc space-y-1 pl-5 text-[#ce1126]">
                <li v-for="error in inspection.errors" :key="error">{{ error }}</li>
            </ul>

            <template v-if="inspection.valid">
                <div>
                    <p class="restore-label">Restore scope</p>
                    <p>{{ inspection.scope.description }}</p>
                    <p class="mt-2"><strong>{{ inspection.scope.restore_table_count }}</strong> tables replaced ({{ inspection.scope.tables_with_data }} with data).</p>
                    <p class="text-xs text-[#4a6490]">Kept as-is: {{ inspection.scope.preserved_tables.join(', ') }}</p>
                    <p v-if="inspection.scope.tables_removed.length" class="text-xs text-[#8a6a00]">
                        Not in this backup (removed, then re-created empty by migrations): {{ inspection.scope.tables_removed.join(', ') }}
                    </p>
                </div>
                <div class="rounded-md border border-[#ce1126] bg-[#fff5f5] p-3">
                    <p class="font-semibold text-[#ce1126]">All data entered after {{ inspection.backup_timestamp_label || 'this backup' }} will be replaced.</p>
                    <p class="mt-1 text-xs">A recovery backup of the current database is created first. Inventory writes are blocked while the restore runs. If the restore fails part-way, the recovery backup is restored automatically. You may need to sign in again afterwards.</p>
                </div>
                <label class="flex items-start gap-2">
                    <Checkbox v-model="restoreForm.acknowledge" binary inputId="restore-ack" />
                    <span>I understand the current data will be replaced by this backup.</span>
                </label>
                <div>
                    <label class="mb-1 block font-medium">Type the file name to confirm</label>
                    <InputText v-model="restoreForm.confirm_filename" class="w-full" :placeholder="inspection.filename" autocomplete="off" />
                </div>
            </template>
        </div>
        <template #footer>
            <UiButton variant="outline" :disabled="restoring" @click="restoreVisible = false">Cancel</UiButton>
            <UiButton
                variant="destructive"
                :loading="restoring"
                :disabled="!canRestore"
                @click="runRestore"
            >
                Restore now
            </UiButton>
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
import Checkbox from 'primevue/checkbox';
import UiBadge from '../components/ui/UiBadge.vue';
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
const restoreLock = ref(null);
const restoreLog = ref([]);
const restoreVisible = ref(false);
const inspecting = ref(false);
const restoring = ref(false);
const inspection = ref(null);
const restoreForm = reactive({ acknowledge: false, confirm_filename: '' });

const canRestore = computed(() =>
    inspection.value?.valid
    && restoreForm.acknowledge
    && restoreForm.confirm_filename.trim() === inspection.value.filename,
);

function outcomeLabel(status) {
    return {
        succeeded: 'Succeeded',
        running: 'Running',
        failed_rolled_back: 'Failed — rolled back',
        failed_before_restore: 'Failed — nothing changed',
        failed_recovery_required: 'Failed — recovery required',
    }[status] || status;
}

async function loadRestoreLog() {
    try {
        const { data } = await api.get('/backups/restore-log');
        restoreLog.value = data.entries || [];
        restoreLock.value = data.restore_lock;
    } catch {
        restoreLog.value = [];
    }
}

async function startRestore(backup) {
    inspection.value = null;
    restoreForm.acknowledge = false;
    restoreForm.confirm_filename = '';
    restoreVisible.value = true;
    inspecting.value = true;
    try {
        const { data } = await api.get(`/backups/${encodeURIComponent(backup.filename)}/inspect`);
        inspection.value = data;
    } catch (error) {
        restoreVisible.value = false;
        notify.error(error.response?.data?.message || 'Unable to validate this backup.');
    } finally {
        inspecting.value = false;
    }
}

async function runRestore() {
    if (!canRestore.value) return;
    restoring.value = true;
    try {
        const { data } = await api.post(`/backups/${encodeURIComponent(inspection.value.filename)}/restore`, { ...restoreForm });
        restoreVisible.value = false;
        notify.success(data.message, 'Restore complete');
    } catch (error) {
        notify.error(error.response?.data?.message || 'Restore failed.', 'Restore failed');
    } finally {
        restoring.value = false;
        await Promise.all([load(), loadRestoreLog()]);
    }
}

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
        restoreLock.value = data.restore_lock;
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

onMounted(() => Promise.all([load(), loadRestoreLog()]));
</script>

<style scoped>
.restore-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4a6490;
}
</style>
