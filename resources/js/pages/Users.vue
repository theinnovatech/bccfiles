<template>
    <UiCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">Admin Accounts</h3>
                    <p class="shadcn-card-description">
                        Create and manage administrator logins. Only admin and supply officer accounts can access OBIMS.
                    </p>
                </div>
                <UiButton @click="openDialog()">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Admin
                </UiButton>
            </div>
        </template>

        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredUsers.length"
            @reset="resetFilters"
        />

        <DataTable :value="filteredUsers" :loading="loading" paginator :rows="10" class="rounded-md border border-[#a8b8d4]">
            <Column field="name" header="Name" />
            <Column field="email" header="Email" />
            <Column header="Role">
                <template #body>
                    <UiBadge variant="default">Admin</UiBadge>
                </template>
            </Column>
            <Column header="Active">
                <template #body="{ data }">
                    <UiBadge :variant="data.is_active ? 'default' : 'outline'">{{ data.is_active ? 'Active' : 'Inactive' }}</UiBadge>
                </template>
            </Column>
            <Column header="Actions" style="width: 6rem">
                <template #body="{ data }">
                    <UiButton variant="ghost" size="icon" @click="openDialog(data)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                    </UiButton>
                </template>
            </Column>
        </DataTable>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="editingId ? 'Edit Admin Account' : 'Add Admin Account'"
            :style="{ width: '520px' }"
        >
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Full Name <span class="text-[#ce1126]">*</span></label>
                    <InputText v-model="form.name" placeholder="Administrator name" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Email <span class="text-[#ce1126]">*</span></label>
                    <div class="admin-email-field">
                        <InputText
                            v-model="form.emailLocal"
                            type="text"
                            placeholder="username"
                            class="admin-email-local"
                            autocomplete="off"
                            @input="sanitizeEmailLocal"
                        />
                        <span class="admin-email-domain">@obims.admin.com</span>
                    </div>
                    <p class="mt-1.5 text-xs text-[#4a6490]">
                        Login email:
                        <span class="font-medium text-[#00164d]">{{ fullAdminEmail || 'username@obims.admin.com' }}</span>
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">
                        Password
                        <span v-if="!editingId" class="text-[#ce1126]">*</span>
                        <span v-else class="text-xs font-normal text-[#4a6490]">(leave blank to keep current)</span>
                    </label>
                    <Password v-model="form.password" :feedback="false" toggleMask placeholder="Password" class="w-full" inputClass="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_active" binary inputId="active" />
                    <label for="active" class="text-sm text-[#4a6490]">Active account</label>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                    This page is for <strong>admin accounts only</strong>. Supply officer accounts are managed separately in seeding or by an administrator.
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="dialogVisible = false">Cancel</UiButton>
                <UiButton :loading="saving" @click="save">
                    {{ editingId ? 'Save Changes' : 'Create Admin' }}
                </UiButton>
            </template>
        </Dialog>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import UiCard from '../components/ui/UiCard.vue';
import UiButton from '../components/ui/UiButton.vue';
import UiBadge from '../components/ui/UiBadge.vue';
import TableFilters from '../components/TableFilters.vue';
import { useNotify } from '../composables/useNotify';
import { useTableFilters } from '../composables/useTableFilters';
import api from '../services/api';

const notify = useNotify();
const users = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const ADMIN_EMAIL_DOMAIN = '@obims.admin.com';

const form = reactive({
    name: '',
    emailLocal: '',
    password: '',
    is_active: true,
});

const fullAdminEmail = computed(() => {
    const local = form.emailLocal.trim().toLowerCase();
    return local ? `${local}${ADMIN_EMAIL_DOMAIN}` : '';
});

function extractEmailLocal(email) {
    if (!email) {
        return '';
    }

    const value = String(email).trim().toLowerCase();
    const atIndex = value.indexOf('@');

    if (atIndex === -1) {
        return value;
    }

    return value.slice(0, atIndex);
}

function sanitizeEmailLocal() {
    form.emailLocal = form.emailLocal
        .replace(/@.*$/, '')
        .replace(/[^a-zA-Z0-9._+-]/g, '')
        .toLowerCase();
}

const filterConfig = computed(() => [
    {
        key: 'search',
        type: 'search',
        label: 'Search',
        placeholder: 'Name or email...',
        fields: ['name', 'email'],
    },
    {
        key: 'status',
        type: 'boolean',
        label: 'Status',
        field: 'is_active',
        options: [
            { label: 'All statuses', value: '' },
            { label: 'Active only', value: 'true' },
            { label: 'Inactive only', value: 'false' },
        ],
    },
]);

const { filters, filteredItems: filteredUsers, hasActiveFilters, resetFilters } = useTableFilters(users, filterConfig);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/users/list');
        users.value = data;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load admin accounts.');
    } finally {
        loading.value = false;
    }
}

function openDialog(user = null) {
    editingId.value = user?.id ?? null;
    form.name = user?.name || '';
    form.emailLocal = extractEmailLocal(user?.email);
    form.password = '';
    form.is_active = user?.is_active ?? true;
    dialogVisible.value = true;
}

async function save() {
    sanitizeEmailLocal();

    if (!form.name || !form.emailLocal || (!editingId.value && !form.password)) {
        notify.warn('Please fill in all required fields.');
        return;
    }

    saving.value = true;
    try {
        const payload = {
            name: form.name,
            email: fullAdminEmail.value,
            is_active: form.is_active,
        };

        if (form.password) {
            payload.password = form.password;
        }

        if (editingId.value) {
            await api.put(`/users/${editingId.value}`, payload);
            notify.success('Admin account updated successfully.');
        } else {
            await api.post('/users', payload);
            notify.success('Admin account created successfully.');
        }
        dialogVisible.value = false;
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save admin account.');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
.admin-email-field {
    display: flex;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid #a8b8d4;
    border-radius: 0.375rem;
    background: #fff;
}

.admin-email-field:focus-within {
    outline: 2px solid #001f6b;
    outline-offset: 2px;
}

.admin-email-local {
    flex: 1 1 auto;
    min-width: 0;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

.admin-email-local:focus {
    outline: none !important;
    box-shadow: none !important;
}

.admin-email-domain {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    padding: 0 0.75rem;
    border-left: 1px solid #a8b8d4;
    background: #e2e8f2;
    color: #00164d;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
}
</style>
