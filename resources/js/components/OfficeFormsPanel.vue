<template>
    <div v-if="forms.length" class="space-y-2">
        <p class="text-xs uppercase tracking-wide text-[#4a6490]">Office forms</p>
        <div
            v-for="entry in forms"
            :key="entry.form"
            class="flex flex-wrap items-center justify-between gap-2 border border-[#a8b8d4] p-3 text-sm"
        >
            <div class="min-w-0">
                <p class="font-semibold text-[#00164d]">{{ entry.label }}</p>
                <p v-if="entry.loading" class="text-xs text-[#4a6490]">Checking…</p>
                <p v-else-if="entry.status?.issued" class="text-xs text-[#4a6490]">
                    {{ entry.status.document_number }} · issued {{ formatDate(entry.status.issued_at) }}
                    <template v-if="entry.status.issued_by"> by {{ entry.status.issued_by }}</template>
                    · reprints use the saved copy
                </p>
                <p v-else-if="entry.status && !entry.status.available" class="text-xs font-medium text-[#ce1126]">
                    {{ entry.status.message }}
                </p>
                <p v-else class="text-xs text-[#4a6490]">Draft — not issued yet. Issuing assigns the number and locks this copy.</p>
            </div>
            <div v-if="entry.status && entry.status.available !== false" class="flex flex-wrap gap-2">
                <UiButton variant="outline" size="sm" @click="openPdf(entry.form)">
                    {{ entry.status.issued ? 'PDF' : 'Preview draft' }}
                </UiButton>
                <UiButton variant="outline" size="sm" @click="downloadXlsx(entry.form)">Excel</UiButton>
                <UiButton v-if="!entry.status.issued && canIssue" size="sm" :loading="issuing === entry.form" @click="startIssue(entry)">
                    Issue {{ entry.short }}
                </UiButton>
            </div>
        </div>

        <Dialog v-model:visible="icsDialog" modal header="Issue Inventory Custodian Slip" :style="{ width: '640px' }" :closable="issuing !== 'ics'">
            <div class="space-y-3 pt-2 text-sm text-[#00164d]">
                <p class="text-xs text-[#4a6490]">
                    Confirm the details printed on the ICS. Once issued, the ICS number is assigned and reprints keep
                    these values even if records or settings change later.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block font-medium">
                            ICS No.
                            <span v-if="!numbering.isAuto('ics_number')" class="text-[#ce1126]">*</span>
                        </label>
                        <InputText v-model="icsForm.number" class="w-full" :placeholder="numbering.placeholder('ics_number')" />
                        <p class="mt-1 text-xs text-[#4a6490]">{{ numbering.hint('ics_number') }} All lines share this number.</p>
                    </div>
                    <div>
                        <label class="mb-1 block font-medium">Entity name <span class="text-[#ce1126]">*</span></label>
                        <InputText v-model="icsForm.entity_name" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block font-medium">Fund cluster</label>
                        <InputText v-model="icsForm.fund_cluster" class="w-full" />
                    </div>
                </div>
                <div v-for="line in icsForm.lines" :key="line.key" class="grid gap-3 border border-[#d7e0ef] p-2 sm:grid-cols-[minmax(0,1fr)_8rem_9rem]">
                    <div>
                        <p class="font-medium">{{ line.quantity }} × {{ line.description }}</p>
                        <p class="text-xs text-[#4a6490]">Unit cost: {{ line.unit_cost === null ? 'not recorded (prints blank)' : formatMoney(line.unit_cost) }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">Unit <span class="text-[#ce1126]">*</span></label>
                        <InputText v-model="line.unit" class="w-full" placeholder="piece, set, unit" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">Estimated useful life</label>
                        <InputText v-model="line.useful_life" class="w-full" placeholder="e.g. 2 years" />
                    </div>
                </div>
                <p v-if="icsExcluded.length" class="text-xs text-[#8a6a00]">
                    Not on the ICS (not semi-expendable): {{ icsExcluded.join(', ') }}
                </p>
            </div>
            <template #footer>
                <UiButton variant="outline" :disabled="issuing === 'ics'" @click="icsDialog = false">Cancel</UiButton>
                <UiButton :loading="issuing === 'ics'" @click="issueIcs">Issue ICS</UiButton>
            </template>
        </Dialog>

        <Dialog v-model:visible="rivDialog" modal header="Issue Local RIV" :style="{ width: '480px' }" :closable="issuing !== 'riv'">
            <div class="space-y-3 pt-2 text-sm text-[#00164d]">
                <p class="text-xs text-[#4a6490]">
                    Assign a RIV number to {{ issuance?.issuance_number }} and save this copy. Reprints keep the
                    current names, costs and signatories. Issuing does not change stock.
                </p>
                <div>
                    <label class="mb-1 block font-medium">
                        RIV No.
                        <span v-if="!numbering.isAuto('riv_number')" class="text-[#ce1126]">*</span>
                    </label>
                    <InputText v-model="rivNumber" class="w-full" :placeholder="numbering.placeholder('riv_number')" />
                    <p class="mt-1 text-xs text-[#4a6490]">{{ numbering.hint('riv_number') }} All lines share this number.</p>
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" :disabled="issuing === 'riv'" @click="rivDialog = false">Cancel</UiButton>
                <UiButton :loading="issuing === 'riv'" @click="issueRiv">Issue RIV</UiButton>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import UiButton from './ui/UiButton.vue';
import { useNotify } from '../composables/useNotify';
import { useDocumentNumbering } from '../composables/useDocumentNumbering';
import { useAuthStore } from '../stores/auth';
import { downloadFile, openInNewTab } from '../utils/downloads';
import api from '../services/api';

const props = defineProps({
    issuance: { type: Object, default: null },
});

const notify = useNotify();
const auth = useAuthStore();
const forms = ref([]);
const issuing = ref('');
const icsDialog = ref(false);
const icsExcluded = ref([]);
const icsForm = reactive({ number: '', entity_name: '', fund_cluster: '', lines: [] });
const rivDialog = ref(false);
const rivNumber = ref('');
const numbering = useDocumentNumbering();

const canIssue = computed(() => {
    const pages = auth.user?.allowed_pages || [];
    return auth.isAdmin || auth.isSupplyOfficer || pages.includes('issuance') || pages.includes('scan-issue');
});

function hasLines(type) {
    return (props.issuance?.details ?? []).some((detail) => (type === 'items' ? detail.item_id : detail.equipment_id));
}

async function load() {
    if (!props.issuance?.id) {
        forms.value = [];
        return;
    }

    forms.value = [
        hasLines('items') ? { form: 'riv', label: 'Local RIV', short: 'RIV', loading: true, status: null } : null,
        hasLines('equipments') ? { form: 'ics', label: 'Inventory Custodian Slip (ICS)', short: 'ICS', loading: true, status: null } : null,
    ].filter(Boolean);

    await Promise.all(forms.value.map(async (entry) => {
        try {
            const { data } = await api.get(`/forms/${entry.form}/${props.issuance.id}`);
            entry.status = data;
        } catch (error) {
            entry.status = { available: false, message: error.response?.data?.message || 'Unable to load this form.' };
        } finally {
            entry.loading = false;
        }
    }));
}

function openPdf(form) {
    openInNewTab(`/forms/${form}/${props.issuance.id}/pdf`, { preview: 1 });
}

async function downloadXlsx(form) {
    try {
        await downloadFile(`/forms/${form}/${props.issuance.id}/xlsx`, {}, `${form}.xlsx`);
    } catch (error) {
        notify.error(error.message, 'Download failed');
    }
}

function startIssue(entry) {
    numbering.load(true);
    if (entry.form === 'ics') {
        const draft = entry.status?.draft || {};
        icsForm.number = '';
        icsForm.entity_name = draft.entity_name || '';
        icsForm.fund_cluster = draft.fund_cluster || '';
        icsForm.lines = (draft.lines || []).map((line) => ({ ...line, unit: line.unit || '', useful_life: line.useful_life || '' }));
        icsExcluded.value = draft.excluded || [];
        icsDialog.value = true;
        return;
    }

    rivNumber.value = '';
    rivDialog.value = true;
}

async function issueRiv() {
    if (!numbering.isAuto('riv_number') && !rivNumber.value.trim()) {
        notify.warn('Enter the RIV No. assigned by the organization (numbering is set to manual).');
        return;
    }
    try {
        await submitIssue('riv', { number: rivNumber.value.trim() || null });
        rivDialog.value = false;
    } catch {
        // message already shown
    }
}

async function issueIcs() {
    if (!icsForm.entity_name.trim()) {
        notify.warn('Confirm the entity name.');
        return;
    }
    if (icsForm.lines.some((line) => !String(line.unit || '').trim())) {
        notify.warn('Enter the unit of measure for every line.');
        return;
    }

    if (!numbering.isAuto('ics_number') && !icsForm.number.trim()) {
        notify.warn('Enter the ICS No. assigned by the organization (numbering is set to manual).');
        return;
    }

    await submitIssue('ics', {
        number: icsForm.number.trim() || null,
        entity_name: icsForm.entity_name.trim(),
        fund_cluster: icsForm.fund_cluster.trim() || null,
        lines: icsForm.lines.map((line) => ({ key: line.key, unit: line.unit.trim(), useful_life: String(line.useful_life || '').trim() || null })),
    });
    icsDialog.value = false;
}

async function submitIssue(form, payload) {
    issuing.value = form;
    try {
        const { data } = await api.post(`/forms/${form}/${props.issuance.id}/issue`, payload);
        notify.success(data.message, data.already_issued ? 'Already issued' : 'Form issued');
        await load();
    } catch (error) {
        const errors = error.response?.data?.errors;
        const first = errors ? Object.values(errors)[0] : null;
        notify.error((Array.isArray(first) ? first[0] : first) || error.response?.data?.message || 'Unable to issue the form.');
        throw error;
    } finally {
        issuing.value = '';
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}

function formatMoney(value) {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

watch(() => props.issuance?.id, load, { immediate: true });
</script>
