<template>
    <section class="space-y-4">
        <div>
            <h4 class="text-sm font-semibold text-[#00164d]">Document numbering</h4>
            <p class="mt-0.5 text-xs text-[#4a6490]">
                Official numbers are either typed by staff (manual) or generated from your
                organization's format (automatic). Numbers already assigned never change when
                these settings change. Tokens:
                <code v-for="(label, token) in tokens" :key="token" class="mr-2 whitespace-nowrap" :title="label">{{ token }}</code>
            </p>
            <p v-if="!canEdit" class="mt-1 text-xs font-medium text-[#ce1126]">
                Only administrators can change numbering.
            </p>
        </div>

        <div v-if="loading" class="text-sm text-[#4a6490]">Loading numbering settings…</div>

        <div v-else class="grid gap-4 lg:grid-cols-2">
            <div
                v-for="row in rows"
                :key="row.document_type"
                class="stock-op-form-panel space-y-3 p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-[#00164d]">{{ row.label }}</p>
                        <p class="text-xs text-[#4a6490]">
                            {{ row.required ? "Required on every record." : "Optional; leave blank when none is assigned." }}
                        </p>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-medium"
                        :class="row.saved.mode === 'auto' ? 'bg-[#e2e8f2] text-[#00164d]' : 'bg-[#fff7e6] text-[#7a4b00]'"
                    >
                        {{ row.saved.mode === "auto" ? "Automatic" : "Manual" }}
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#00164d]">Mode</label>
                        <Select
                            v-model="row.mode"
                            :options="modeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            :disabled="!canEdit"
                            @update:model-value="schedulePreview(row)"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#00164d]">Reset sequence</label>
                        <Select
                            v-model="row.reset_rule"
                            :options="resetOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            :disabled="!canEdit || row.mode !== 'auto'"
                            @update:model-value="schedulePreview(row)"
                        />
                    </div>
                    <div class="sm:col-span-2" :class="{ 'opacity-60': row.mode !== 'auto' }">
                        <label class="mb-1 block text-xs font-medium text-[#00164d]">Format</label>
                        <InputText
                            v-model="row.format"
                            class="w-full font-mono"
                            :disabled="!canEdit || row.mode !== 'auto'"
                            placeholder="e.g. PROP-{YYYY}-{SEQ}"
                            @input="schedulePreview(row)"
                        />
                    </div>
                    <div :class="{ 'opacity-60': row.mode !== 'auto' }">
                        <label class="mb-1 block text-xs font-medium text-[#00164d]">Sequence digits</label>
                        <InputNumber
                            v-model="row.padding"
                            class="w-full"
                            :min="1"
                            :max="10"
                            :disabled="!canEdit || row.mode !== 'auto'"
                            @update:model-value="schedulePreview(row)"
                        />
                    </div>
                </div>

                <div class="rounded-md border border-[#d7e0ef] bg-[#f4f7fb] px-3 py-2 text-xs text-[#00164d]">
                    <template v-if="row.error">
                        <span class="font-medium text-[#ce1126]">{{ row.error }}</span>
                    </template>
                    <template v-else-if="row.mode !== 'auto'">
                        Staff type the number the organization assigned. Leading zeros, letters and
                        separators are kept exactly as typed (surrounding spaces are trimmed).
                    </template>
                    <template v-else-if="row.preview">
                        <p>
                            Preview: <span class="font-mono font-semibold">{{ row.preview.first_today }}</span>,
                            <span class="font-mono">{{ row.preview.second_today }}</span>, …
                        </p>
                        <p class="mt-0.5">
                            Next number if saved: <span class="font-mono font-semibold">{{ row.preview.next_if_saved }}</span>
                        </p>
                        <p v-if="row.preview.after_reset" class="mt-0.5">
                            After the reset on {{ row.preview.after_reset_date }}:
                            <span class="font-mono">{{ row.preview.after_reset }}</span>
                        </p>
                    </template>
                    <template v-else>Checking format…</template>
                </div>

                <div v-if="canEdit" class="flex justify-end gap-2">
                    <UiButton
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!isDirty(row) || row.saving"
                        @click="revert(row)"
                    >
                        Revert
                    </UiButton>
                    <UiButton
                        type="button"
                        size="sm"
                        :loading="row.saving"
                        :disabled="!isDirty(row) || Boolean(row.error)"
                        @click="saveRow(row)"
                    >
                        Save
                    </UiButton>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Select from "primevue/select";
import UiButton from "./ui/UiButton.vue";
import api from "../services/api";
import { useNotify } from "../composables/useNotify";
import { useAuthStore } from "../stores/auth";
import { useDocumentNumbering } from "../composables/useDocumentNumbering";

const notify = useNotify();
const auth = useAuthStore();
const numbering = useDocumentNumbering();
const canEdit = computed(() => auth.isAdmin);
const loading = ref(false);
const rows = ref([]);
const tokens = ref({});

const modeOptions = [
    { label: "Manual (typed by staff)", value: "manual" },
    { label: "Automatic (from format)", value: "auto" },
];
const resetOptions = [
    { label: "Never", value: "never" },
    { label: "Every year", value: "yearly" },
    { label: "Every month", value: "monthly" },
];

const timers = new Map();

function editable(row) {
    return {
        mode: row.mode,
        format: row.format,
        padding: row.padding,
        reset_rule: row.reset_rule,
    };
}

function isDirty(row) {
    return JSON.stringify(editable(row)) !== JSON.stringify(editable(row.saved));
}

function revert(row) {
    Object.assign(row, editable(row.saved));
    row.error = "";
    schedulePreview(row, 0);
}

function firstError(error) {
    const errors = error.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors)[0];
        return Array.isArray(first) ? first[0] : first;
    }

    return error.response?.data?.message || "Invalid numbering settings.";
}

function schedulePreview(row, delay = 300) {
    clearTimeout(timers.get(row.document_type));
    timers.set(
        row.document_type,
        setTimeout(() => preview(row), delay),
    );
}

async function preview(row) {
    row.error = "";
    if (row.mode !== "auto") {
        row.preview = null;
        return;
    }
    row.preview = null;
    try {
        const { data } = await api.post("/document-numbering/preview", {
            document_type: row.document_type,
            ...editable(row),
        });
        row.preview = data.examples;
    } catch (error) {
        row.error = firstError(error);
    }
}

async function saveRow(row) {
    row.saving = true;
    try {
        const { data } = await api.put(`/document-numbering/${row.document_type}`, editable(row));
        row.saved = editable(data);
        Object.assign(row, editable(data));
        notify.success(`${row.label}: numbering saved.`);
        numbering.load(true);
        schedulePreview(row, 0);
    } catch (error) {
        row.error = firstError(error);
        notify.error(row.error);
    } finally {
        row.saving = false;
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get("/document-numbering");
        tokens.value = data.tokens || {};
        rows.value = (data.types || []).map((row) => ({
            ...row,
            example: row.preview,
            saved: editable(row),
            preview: null,
            error: "",
            saving: false,
        }));
        rows.value.forEach((row) => {
            if (row.mode === "auto" && canEdit.value) {
                schedulePreview(row, 0);
            } else if (row.mode === "auto") {
                row.preview = {
                    first_today: row.example,
                    second_today: "",
                    next_if_saved: row.next_preview,
                };
            }
        });
    } catch {
        notify.error("Unable to load numbering settings.");
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
