<template>
    <div class="shadcn-card overflow-hidden">
        <div class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6">
            <h3 class="text-lg font-semibold text-[#002a7a]">System Settings</h3>
            <p class="mt-1 text-sm text-[#5b7fbf]">
                Configure organization details and inventory rules for the whole system.
            </p>
        </div>

        <div v-if="loadingPage" class="stock-op-empty">
            <svg class="h-8 w-8 animate-spin text-[#0038a8]" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-3 text-sm text-[#5b7fbf]">Loading settings...</p>
        </div>

        <form v-else class="stock-op-content space-y-6 p-4 sm:p-6" @submit.prevent="save">
            <section v-for="group in settingGroups" :key="group.key" class="space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-[#002a7a]">{{ group.label }}</h4>
                    <p class="mt-0.5 text-xs text-[#5b7fbf]">{{ group.description }}</p>
                </div>

                <div class="stock-op-form-panel space-y-5 p-4 sm:p-5">
                    <div v-for="setting in group.items" :key="setting.key" class="space-y-1.5">
                        <div v-if="setting.type === 'boolean'" class="flex items-start justify-between gap-4 rounded-lg border border-[#c8d6ef] bg-white px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-[#002a7a]">{{ setting.label }}</p>
                                <p v-if="setting.description" class="mt-1 text-xs leading-relaxed text-[#5b7fbf]">
                                    {{ setting.description }}
                                </p>
                            </div>
                            <Checkbox
                                v-model="setting.boolValue"
                                binary
                                :input-id="`setting-${setting.key}`"
                            />
                        </div>

                        <template v-else>
                            <label :for="`setting-${setting.key}`" class="block text-sm font-medium text-[#002a7a]">
                                {{ setting.label }}
                            </label>
                            <p v-if="setting.description" class="text-xs leading-relaxed text-[#5b7fbf]">
                                {{ setting.description }}
                            </p>
                            <InputText
                                :id="`setting-${setting.key}`"
                                v-model="setting.value"
                                class="w-full"
                                :placeholder="setting.placeholder || ''"
                            />
                        </template>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-[#eef2fa] pt-4">
                <UiButton variant="outline" type="button" :disabled="saving" @click="load">
                    Reset changes
                </UiButton>
                <UiButton type="submit" :loading="saving">
                    Save settings
                </UiButton>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import InputText from 'primevue/inputtext';
import Checkbox from 'primevue/checkbox';
import UiButton from '../components/ui/UiButton.vue';
import { useNotify } from '../composables/useNotify';
import api from '../services/api';
import { updateOrganizationNameDisplay } from '../utils/organizationDisplay';

const notify = useNotify();
const settings = ref([]);
const loadingPage = ref(false);
const saving = ref(false);

const GROUP_META = {
    general: {
        label: 'General',
        description: 'Basic information about your organization.',
    },
    inventory: {
        label: 'Inventory',
        description: 'Rules for stock levels and item movements.',
    },
};

const settingGroups = computed(() => {
    const grouped = {};

    for (const setting of settings.value) {
        const groupKey = setting.group || 'general';

        if (!grouped[groupKey]) {
            grouped[groupKey] = {
                key: groupKey,
                label: GROUP_META[groupKey]?.label ?? groupKey,
                description: GROUP_META[groupKey]?.description ?? '',
                items: [],
            };
        }

        grouped[groupKey].items.push(setting);
    }

    return Object.values(grouped);
});

function hydrateSettings(rows) {
    settings.value = rows.map((row) => ({
        ...row,
        boolValue: row.type === 'boolean' ? row.value === 'true' : false,
    }));
}

function payloadFromSettings() {
    return settings.value.map((setting) => ({
        key: setting.key,
        group: setting.group,
        value: setting.type === 'boolean'
            ? (setting.boolValue ? 'true' : 'false')
            : setting.value ?? '',
    }));
}

async function load() {
    loadingPage.value = true;
    try {
        const { data } = await api.get('/settings/list');
        hydrateSettings(data ?? []);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load settings.');
    } finally {
        loadingPage.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const { data } = await api.put('/settings', { settings: payloadFromSettings() });
        hydrateSettings(data ?? []);
        const organizationName = settings.value.find((setting) => setting.key === 'organization_name');
        if (organizationName?.value) {
            updateOrganizationNameDisplay(organizationName.value);
        }
        notify.success('Settings saved successfully.');
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save settings.');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
