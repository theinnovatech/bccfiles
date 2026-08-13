<template>
    <div class="profile-page mx-auto max-w-3xl">
        <div class="shadcn-card overflow-hidden">
            <div class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="profile-avatar" aria-hidden="true">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4a6490]">
                                Account
                            </p>
                            <h3 class="truncate text-lg font-semibold text-[#00164d]">
                                {{ displayName }}
                            </h3>
                            <p class="mt-0.5 truncate text-sm text-[#4a6490]">
                                Manage your display name and sign-in password.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="profile-chip">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            {{ roleLabel || '—' }}
                        </span>
                        <span v-if="departmentName" class="profile-chip">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            {{ departmentName }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="px-4 py-16 text-center text-sm text-[#4a6490] sm:px-6">
                Loading profile...
            </div>

            <div v-else class="profile-body space-y-6 p-4 sm:p-6">
                <section class="profile-panel">
                    <div class="profile-panel-head">
                        <div class="profile-panel-icon" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-[#00164d]">Profile details</h4>
                            <p class="mt-0.5 text-xs text-[#4a6490]">
                                Your name is shown in the top bar. Email and role are set by an administrator.
                            </p>
                        </div>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="saveProfile">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[#00164d]">
                                Full Name <span class="text-[#ce1126]">*</span>
                            </label>
                            <InputText
                                v-model="profileForm.name"
                                class="w-full"
                                placeholder="Your name"
                                autocomplete="name"
                                required
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[#00164d]">Email</label>
                                <div class="profile-readonly">
                                    <svg class="h-4 w-4 shrink-0 text-[#4a6490]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    <span class="truncate">{{ email || '—' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[#00164d]">Role</label>
                                <div class="profile-readonly">
                                    <svg class="h-4 w-4 shrink-0 text-[#4a6490]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                    <span class="truncate">{{ roleLabel || '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-[#e2e8f2] pt-4">
                            <UiButton type="submit" :loading="savingProfile">
                                Save Profile
                            </UiButton>
                        </div>
                    </form>
                </section>

                <section class="profile-panel">
                    <div class="profile-panel-head">
                        <div class="profile-panel-icon" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-[#00164d]">Change password</h4>
                            <p class="mt-0.5 text-xs text-[#4a6490]">
                                Use your current password, then choose a new one you’ll remember.
                            </p>
                        </div>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="savePassword">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-[#00164d]">
                                Current Password <span class="text-[#ce1126]">*</span>
                            </label>
                            <Password
                                v-model="passwordForm.current_password"
                                class="w-full"
                                inputClass="w-full"
                                :feedback="false"
                                toggleMask
                                autocomplete="current-password"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[#00164d]">
                                    New Password <span class="text-[#ce1126]">*</span>
                                </label>
                                <Password
                                    v-model="passwordForm.password"
                                    class="w-full"
                                    inputClass="w-full"
                                    toggleMask
                                    autocomplete="new-password"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-[#00164d]">
                                    Confirm New Password <span class="text-[#ce1126]">*</span>
                                </label>
                                <Password
                                    v-model="passwordForm.password_confirmation"
                                    class="w-full"
                                    inputClass="w-full"
                                    :feedback="false"
                                    toggleMask
                                    autocomplete="new-password"
                                />
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-[#e2e8f2] pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-[#4a6490]">
                                Choose a strong password with letters and numbers.
                            </p>
                            <UiButton type="submit" :loading="savingPassword">
                                Update Password
                            </UiButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import UiButton from '../components/ui/UiButton.vue';
import { useNotify } from '../composables/useNotify';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const notify = useNotify();
const auth = useAuthStore();

const loading = ref(true);
const savingProfile = ref(false);
const savingPassword = ref(false);

const profileForm = reactive({
    name: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const email = computed(() => auth.user?.email || '');
const roleLabel = computed(() => auth.user?.role_label || '');
const departmentName = computed(() => auth.user?.department?.name || '');
const displayName = computed(() => profileForm.name.trim() || auth.user?.name || 'My Profile');

const initials = computed(() => {
    const parts = String(displayName.value || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase();
});

async function loadProfile() {
    loading.value = true;
    try {
        await auth.fetchUser();
        profileForm.name = auth.user?.name || '';
    } catch {
        notify.error('Unable to load your profile.');
    } finally {
        loading.value = false;
    }
}

async function saveProfile() {
    const name = profileForm.name.trim();
    if (!name) {
        notify.warn('Name is required.');
        return;
    }

    savingProfile.value = true;
    try {
        const { data } = await api.put('/profile', { name });
        auth.user = data.user;
        profileForm.name = data.user?.name || name;
        notify.success(data.message || 'Profile updated successfully.');
        window.setTimeout(() => window.location.reload(), 400);
    } catch (error) {
        notify.error(
            error.response?.data?.message
                || error.response?.data?.errors?.name?.[0]
                || 'Unable to update profile.',
        );
    } finally {
        savingProfile.value = false;
    }
}

async function savePassword() {
    if (!passwordForm.current_password || !passwordForm.password) {
        notify.warn('Current and new passwords are required.');
        return;
    }

    if (passwordForm.password !== passwordForm.password_confirmation) {
        notify.warn('New password confirmation does not match.');
        return;
    }

    savingPassword.value = true;
    try {
        const { data } = await api.put('/profile/password', {
            current_password: passwordForm.current_password,
            password: passwordForm.password,
            password_confirmation: passwordForm.password_confirmation,
        });
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        notify.success(data.message || 'Password updated successfully.');
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        notify.error(
            errors.current_password?.[0]
                || errors.password?.[0]
                || error.response?.data?.message
                || 'Unable to update password.',
        );
    } finally {
        savingPassword.value = false;
    }
}

onMounted(loadProfile);
</script>

<style scoped>
.profile-avatar {
    display: flex;
    height: 3.5rem;
    width: 3.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 2px solid #a8b8d4;
    background: linear-gradient(160deg, #001f6b 0%, #00164d 100%);
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #ffffff;
}

.profile-chip {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.375rem;
    border: 1px solid #a8b8d4;
    background: #f4f7fb;
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: #00164d;
}

.profile-panel {
    border: 1px solid #a8b8d4;
    border-left: 4px solid #001f6b;
    background: linear-gradient(180deg, #f7f9fc 0%, #ffffff 32%);
    padding: 1.25rem;
}

@media (min-width: 640px) {
    .profile-panel {
        padding: 1.35rem 1.5rem;
    }
}

.profile-panel-head {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.profile-panel-icon {
    display: flex;
    height: 2.25rem;
    width: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid #a8b8d4;
    background: #ffffff;
    color: #001f6b;
}

.profile-readonly {
    display: flex;
    min-height: 2.25rem;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #d7e0ef;
    background: #f4f7fb;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: #4a6490;
}

:deep(.p-password) {
    width: 100%;
}

:deep(.p-password-input) {
    width: 100%;
}
</style>
