<template>
    <div :class="embedded ? '' : 'shadcn-card overflow-hidden'">
        <div
            v-if="!embedded"
            class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
        >
            <h3 class="text-lg font-semibold text-[#00164d]">System Settings</h3>
            <p class="mt-1 text-sm text-[#4a6490]">
                Configure organization details and inventory rules for the whole system.
            </p>
        </div>

        <div v-if="loadingPage" class="stock-op-empty">
            <svg class="h-8 w-8 animate-spin text-[#001f6b]" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="mt-3 text-sm text-[#4a6490]">Loading settings...</p>
        </div>

        <form
            v-else
            class="space-y-6"
            :class="embedded ? '' : 'stock-op-content p-4 sm:p-6'"
            @submit.prevent="save"
        >
            <section v-for="group in settingGroups" :key="group.key" class="space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-[#00164d]">{{ group.label }}</h4>
                    <p class="mt-0.5 text-xs text-[#4a6490]">{{ group.description }}</p>
                </div>

                <div class="stock-op-form-panel space-y-5 p-4 sm:p-5">
                    <div v-for="setting in group.items" :key="setting.key" class="space-y-1.5">
                        <div
                            v-if="setting.type === 'boolean'"
                            class="flex items-start justify-between gap-4 border border-[#a8b8d4] bg-transparent px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#00164d]">{{ setting.label }}</p>
                                <p v-if="setting.description" class="mt-1 text-xs leading-relaxed text-[#4a6490]">
                                    {{ setting.description }}
                                </p>
                            </div>
                            <Checkbox
                                v-model="setting.boolValue"
                                binary
                                :input-id="`setting-${setting.key}`"
                            />
                        </div>

                        <div v-else-if="setting.type === 'page_list'" class="space-y-3">
                            <div>
                                <p class="text-sm font-medium text-[#00164d]">{{ setting.label }}</p>
                                <p v-if="setting.description" class="mt-1 text-xs leading-relaxed text-[#4a6490]">
                                    {{ setting.description }}
                                </p>
                            </div>

                            <div class="rounded-md border border-[#d7e0ef] bg-[#f4f7fb] px-3 py-2 text-xs text-[#4a6490]">
                                Applies to <span class="font-medium text-[#00164d]">department user</span> accounts only.
                                Admin and supply officer access is unchanged.
                            </div>

                            <div
                                v-for="pageGroup in pageGroupsFor(setting)"
                                :key="`${setting.key}-${pageGroup.label}`"
                                class="overflow-hidden border border-[#a8b8d4]"
                            >
                                <div class="border-b border-[#a8b8d4] bg-[#f4f7fb] px-3 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#00164d]">
                                        {{ pageGroup.label }}
                                    </p>
                                </div>
                                <div class="divide-y divide-[#d7e0ef] bg-white">
                                    <label
                                        v-for="page in pageGroup.pages"
                                        :key="page.key"
                                        class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-[#f8fafc]"
                                        :class="{ 'cursor-not-allowed opacity-80': page.always_on }"
                                    >
                                        <Checkbox
                                            v-model="setting.selectedPages"
                                            :input-id="`setting-${setting.key}-${page.key}`"
                                            :value="page.key"
                                            :disabled="page.always_on"
                                            class="mt-0.5"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-[#00164d]">
                                                {{ page.label }}
                                            </span>
                                            <span class="mt-0.5 block text-xs text-[#4a6490]">
                                                {{ page.description }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <template v-else>
                            <label :for="`setting-${setting.key}`" class="block text-sm font-medium text-[#00164d]">
                                {{ setting.label }}
                            </label>
                            <p v-if="setting.description" class="text-xs leading-relaxed text-[#4a6490]">
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

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-[#e2e8f2] pt-4">
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

defineProps({
    embedded: { type: Boolean, default: false },
});

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
    permissions: {
        label: 'Employee access',
        description: 'Choose which pages department users can open in OBIMS.',
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

function parseSelectedPages(value, options = []) {
    let selected = [];

    try {
        const parsed = typeof value === 'string' ? JSON.parse(value || '[]') : value;
        selected = Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
        selected = [];
    }

    if (!selected.includes('dashboard')) {
        selected = ['dashboard', ...selected];
    }

    const allowedKeys = new Set((options || []).map((page) => page.key));
    if (allowedKeys.size) {
        selected = selected.filter((key) => allowedKeys.has(key));
        if (!selected.includes('dashboard') && allowedKeys.has('dashboard')) {
            selected = ['dashboard', ...selected];
        }
    }

    return selected;
}

function pageGroupsFor(setting) {
    const groups = [];
    const byLabel = new Map();

    for (const page of setting.options || []) {
        if (!byLabel.has(page.group)) {
            const group = { label: page.group, pages: [] };
            byLabel.set(page.group, group);
            groups.push(group);
        }
        byLabel.get(page.group).pages.push(page);
    }

    return groups;
}

function hydrateSettings(rows) {
    settings.value = rows.map((row) => {
        const options = Array.isArray(row.options) ? row.options : [];

        return {
            ...row,
            boolValue: row.type === 'boolean' ? row.value === 'true' : false,
            selectedPages: row.type === 'page_list'
                ? parseSelectedPages(row.value, options)
                : [],
            options,
        };
    });
}

function payloadFromSettings() {
    return settings.value.map((setting) => {
        let value = setting.value ?? '';

        if (setting.type === 'boolean') {
            value = setting.boolValue ? 'true' : 'false';
        } else if (setting.type === 'page_list') {
            const pages = Array.isArray(setting.selectedPages)
                ? [...setting.selectedPages]
                : [];
            if (!pages.includes('dashboard')) {
                pages.unshift('dashboard');
            }
            value = JSON.stringify(pages);
        }

        return {
            key: setting.key,
            group: setting.group,
            value,
        };
    });
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
