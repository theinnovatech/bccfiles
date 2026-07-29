<template>
    <UiCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">Employee Permissions</h3>
                    <p class="shadcn-card-description">
                        Choose which pages employee accounts can open after they log in. Dashboard is always available.
                    </p>
                </div>
                <UiButton :loading="saving" @click="save">
                    Save Permissions
                </UiButton>
            </div>
        </template>

        <div v-if="loading" class="py-10 text-center text-sm text-[#4a6490]">
            Loading permissions...
        </div>

        <div v-else class="space-y-6">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                These settings apply to all employees with a login account
                (<strong>(department users)</strong>. Admin and supply officer access is unchanged.
            </div>

            <div
                v-for="group in groupedPages"
                :key="group.label"
                class="overflow-hidden rounded-lg border border-[#a8b8d4]"
            >
                <div class="border-b border-[#a8b8d4] bg-[#f4f7fb] px-4 py-2.5">
                    <h4 class="text-sm font-semibold text-[#00164d]">{{ group.label }}</h4>
                </div>
                <div class="divide-y divide-[#d7e0ef]">
                    <label
                        v-for="page in group.pages"
                        :key="page.key"
                        class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-[#f8fafc]"
                        :class="{ 'cursor-not-allowed opacity-80': page.always_on }"
                    >
                        <Checkbox
                            v-model="selected"
                            :inputId="`perm-${page.key}`"
                            :value="page.key"
                            :disabled="page.always_on"
                            class="mt-0.5"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-[#00164d]">{{ page.label }}</span>
                            <span class="mt-0.5 block text-xs text-[#4a6490]">{{ page.description }}</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import Checkbox from 'primevue/checkbox';
import UiCard from '../components/ui/UiCard.vue';
import UiButton from '../components/ui/UiButton.vue';
import { useNotify } from '../composables/useNotify';
import api from '../services/api';

const notify = useNotify();
const loading = ref(true);
const saving = ref(false);
const pages = ref([]);
const selected = ref([]);

const groupedPages = computed(() => {
    const groups = [];
    const byLabel = new Map();

    for (const page of pages.value) {
        if (!byLabel.has(page.group)) {
            const group = { label: page.group, pages: [] };
            byLabel.set(page.group, group);
            groups.push(group);
        }
        byLabel.get(page.group).pages.push(page);
    }

    return groups;
});

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/permissions');
        pages.value = data.pages || [];
        selected.value = [...(data.allowed || [])];
        if (!selected.value.includes('dashboard')) {
            selected.value.push('dashboard');
        }
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load permissions.');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const pagesToSave = selected.value.includes('dashboard')
            ? selected.value
            : ['dashboard', ...selected.value];

        const { data } = await api.put('/permissions', { pages: pagesToSave });
        selected.value = [...(data.allowed || pagesToSave)];
        notify.success(data.message || 'Employee page permissions updated.');
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save permissions.');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
