<template>
    <div class="space-y-6">
        <div class="shadcn-page-header">
            <h1 class="shadcn-page-title">Delivery Inspection</h1>
            <p class="shadcn-page-description">
                Inspect each delivery against its delivery receipt or purchase order. Only accepted
                quantities are posted to inventory, once, after you confirm.
            </p>
        </div>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">{{ form.id ? `Inspection ${form.inspection_number}` : 'New inspection' }}</h3>
                        <p class="shadcn-card-description">
                            Partial deliveries: record what arrived now; record the rest in a new inspection under the same reference.
                        </p>
                    </div>
                    <UiButton v-if="form.id" variant="outline" size="sm" @click="newInspection">Start new</UiButton>
                </div>
            </template>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Delivery Receipt No.</label>
                    <InputText v-model="form.delivery_reference" class="w-full" :disabled="readOnly" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Purchase Order No.</label>
                    <InputText v-model="form.purchase_reference" class="w-full" :disabled="readOnly" />
                    <p class="mt-1 text-xs text-[#4a6490]">Enter at least one reference.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Supplier</label>
                    <InputText v-model="form.supplier" class="w-full" :disabled="readOnly" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Delivery date</label>
                    <InputText v-model="form.delivery_date" type="date" :max="today" class="w-full" :disabled="readOnly" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Inspection date <span class="text-[#ce1126]">*</span></label>
                    <InputText v-model="form.inspection_date" type="date" :max="today" class="w-full" :disabled="readOnly" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]">Inspector <span class="text-[#ce1126]">*</span></label>
                    <InputText v-model="form.inspector_name" class="w-full" :disabled="readOnly" />
                </div>
            </div>

            <div v-if="priorForReference.length" class="mt-4 rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3 text-xs text-[#00164d]">
                <p class="font-semibold">Already accepted under this reference</p>
                <p v-for="row in priorForReference" :key="row.key">{{ row.label }}: {{ row.accepted }}</p>
            </div>

            <div class="mt-5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#4a6490]">Lines</p>
                    <UiButton v-if="!readOnly" variant="outline" size="sm" @click="addLine">Add line</UiButton>
                </div>

                <div v-for="(line, index) in form.lines" :key="index" class="space-y-3 border border-[#a8b8d4] p-3">
                    <div class="grid gap-3 md:grid-cols-[8rem_minmax(0,1fr)_repeat(4,6.5rem)_auto]">
                        <div>
                            <label class="line-label">Type</label>
                            <Select v-model="line.line_type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" :disabled="readOnly" @update:model-value="line.item_id = null; line.equipment_id = null" />
                        </div>
                        <div class="min-w-0">
                            <label class="line-label">{{ line.line_type === 'item' ? 'Supply item' : 'Equipment listing' }} <span class="text-[#ce1126]">*</span></label>
                            <Select
                                v-if="line.line_type === 'item'"
                                v-model="line.item_id"
                                :options="items"
                                optionLabel="label"
                                optionValue="id"
                                filter
                                class="w-full"
                                placeholder="Select item"
                                :disabled="readOnly"
                            />
                            <Select
                                v-else
                                v-model="line.equipment_id"
                                :options="equipments"
                                optionLabel="label"
                                optionValue="id"
                                filter
                                class="w-full"
                                placeholder="Select equipment"
                                :disabled="readOnly"
                            />
                        </div>
                        <div>
                            <label class="line-label">Expected</label>
                            <InputNumber v-model="line.expected_quantity" :min="0" class="w-full" inputClass="w-full" :disabled="readOnly" />
                        </div>
                        <div>
                            <label class="line-label">Delivered</label>
                            <InputNumber v-model="line.delivered_quantity" :min="0" class="w-full" inputClass="w-full" :disabled="readOnly" @update:model-value="syncRejected(line)" />
                        </div>
                        <div>
                            <label class="line-label">Accepted</label>
                            <InputNumber v-model="line.accepted_quantity" :min="0" class="w-full" inputClass="w-full" :disabled="readOnly" @update:model-value="syncRejected(line)" />
                        </div>
                        <div>
                            <label class="line-label">Rejected</label>
                            <InputNumber v-model="line.rejected_quantity" :min="0" class="w-full" inputClass="w-full" :disabled="readOnly" />
                        </div>
                        <div class="flex items-end">
                            <UiButton v-if="!readOnly" variant="ghost" size="sm" :disabled="form.lines.length === 1" @click="form.lines.splice(index, 1)">Remove</UiButton>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)]">
                        <div>
                            <label class="line-label">Condition</label>
                            <Select v-model="line.condition" :options="conditionOptions" optionLabel="label" optionValue="value" class="w-full" :disabled="readOnly" />
                        </div>
                        <div>
                            <label class="line-label">
                                Discrepancy remarks
                                <span v-if="hasDiscrepancy(line)" class="text-[#ce1126]">* required</span>
                            </label>
                            <InputText v-model="line.discrepancy_remarks" class="w-full" :disabled="readOnly" placeholder="Short / over delivery, damaged, wrong item..." />
                        </div>
                    </div>

                    <div v-if="line.line_type === 'equipment' && tracksSerials(line) && line.accepted_quantity > 0">
                        <label class="line-label">Serial numbers of accepted units ({{ line.accepted_quantity }}) <span class="text-[#ce1126]">*</span></label>
                        <Textarea v-model="line.serials_text" rows="2" class="w-full" :disabled="readOnly" placeholder="One serial number per line" />
                    </div>

                    <p class="text-xs" :class="lineError(line) ? 'font-medium text-[#ce1126]' : 'text-[#4a6490]'">
                        {{ lineError(line) || lineSummary(line) }}
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1 block text-sm font-medium text-[#00164d]">Remarks</label>
                <Textarea v-model="form.remarks" rows="2" class="w-full" :disabled="readOnly" />
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <template v-if="!readOnly">
                    <UiButton variant="outline" :loading="saving" @click="save">Save draft</UiButton>
                    <UiButton :loading="saving" @click="saveAndConfirm">Save &amp; post accepted quantities</UiButton>
                </template>
                <p v-else class="text-sm font-medium text-[#00164d]">
                    Posted {{ formatDate(form.posted_at) }} — accepted quantities are in inventory. This record is read-only.
                </p>
            </div>
        </UiCard>

        <UiCard title="Inspections">
            <div class="mb-3 flex flex-wrap justify-end gap-2">
                <UiButton variant="outline" size="sm" @click="exportList">Export (.xlsx)</UiButton>
            </div>
            <div class="obims-table-wrap">
                <DataTable :value="inspections" :loading="loading" paginator :rows="10" data-key="id" class="rounded-md border border-[#a8b8d4]">
                    <Column field="inspection_number" header="Inspection No." />
                    <Column header="Reference">
                        <template #body="{ data }">{{ referenceLabel(data) }}</template>
                    </Column>
                    <Column field="supplier" header="Supplier" />
                    <Column header="Inspection date">
                        <template #body="{ data }">{{ (data.inspection_date || '').slice(0, 10) }}</template>
                    </Column>
                    <Column field="inspector_name" header="Inspector" />
                    <Column header="Status">
                        <template #body="{ data }">
                            <UiBadge :variant="data.status === 'posted' ? 'default' : 'outline'">{{ data.status === 'posted' ? 'Posted' : 'Draft' }}</UiBadge>
                            <UiBadge v-if="data.is_partial" variant="gold" class="ml-1">Partial</UiBadge>
                            <UiBadge v-if="data.has_rejections" variant="destructive" class="ml-1">Rejections</UiBadge>
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <UiButton variant="ghost" size="sm" @click="edit(data)">{{ data.status === 'posted' ? 'View' : 'Edit' }}</UiButton>
                                <UiButton v-if="data.status !== 'posted'" variant="ghost" size="sm" @click="removeDraft(data)">Delete</UiButton>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>

        <Dialog v-model:visible="confirmVisible" modal header="Post accepted quantities?" :style="{ width: '520px' }" :closable="!saving">
            <div class="space-y-2 pt-2 text-sm text-[#00164d]">
                <p>The following accepted quantities will be added to inventory. Rejected and missing quantities are not posted.</p>
                <ul class="list-disc pl-5">
                    <li v-for="(line, index) in form.lines" :key="index">
                        {{ lineName(line) }}: <strong>{{ line.accepted_quantity }}</strong> accepted
                        <span class="text-[#4a6490]">(delivered {{ line.delivered_quantity }}, rejected {{ line.rejected_quantity }}, expected {{ line.expected_quantity }})</span>
                    </li>
                </ul>
                <p class="text-xs text-[#4a6490]">Posting is done once. Retrying or reviewing this inspection later will not add stock again.</p>
            </div>
            <template #footer>
                <UiButton variant="outline" :disabled="saving" @click="confirmVisible = false">Cancel</UiButton>
                <UiButton :loading="saving" @click="post">Post to inventory</UiButton>
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
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import UiCard from '../../components/ui/UiCard.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import { confirmDelete } from '../../composables/confirm';
import { useNotify } from '../../composables/useNotify';
import { useAuthStore } from '../../stores/auth';
import { downloadFile } from '../../utils/downloads';
import { todayIso } from '../../utils/idempotency';
import api from '../../services/api';

const notify = useNotify();
const auth = useAuthStore();
const today = todayIso();

const typeOptions = [
    { label: 'Supply', value: 'item' },
    { label: 'Equipment', value: 'equipment' },
];
const conditionOptions = [
    { label: 'Good', value: 'good' },
    { label: 'Partial delivery', value: 'partial' },
    { label: 'Damaged', value: 'damaged' },
    { label: 'Mixed (some damaged)', value: 'mixed' },
    { label: 'Wrong / non-conforming', value: 'wrong_item' },
];

const items = ref([]);
const equipments = ref([]);
const inspections = ref([]);
const loading = ref(false);
const saving = ref(false);
const confirmVisible = ref(false);
const form = reactive(blankForm());

const readOnly = computed(() => form.status === 'posted');

function blankLine() {
    return {
        line_type: 'item', item_id: null, equipment_id: null, expected_quantity: 0, delivered_quantity: 0,
        accepted_quantity: 0, rejected_quantity: 0, condition: 'good', discrepancy_remarks: '', serials_text: '',
    };
}

function blankForm() {
    return {
        id: null, inspection_number: '', status: 'draft', posted_at: null,
        delivery_reference: '', purchase_reference: '', supplier: '', delivery_date: today,
        inspection_date: today, inspector_name: '', remarks: '', lines: [blankLine()],
    };
}

function newInspection() {
    Object.assign(form, blankForm(), { inspector_name: auth.user?.name || '' });
}

function addLine() {
    form.lines.push(blankLine());
}

function syncRejected(line) {
    const delivered = Number(line.delivered_quantity || 0);
    const accepted = Number(line.accepted_quantity || 0);
    if (accepted <= delivered) {
        line.rejected_quantity = delivered - accepted;
    }
}

function tracksSerials(line) {
    return Boolean(equipments.value.find((row) => row.id === line.equipment_id)?.tracks_serials);
}

function hasDiscrepancy(line) {
    return Number(line.delivered_quantity) !== Number(line.expected_quantity)
        || Number(line.rejected_quantity) > 0
        || line.condition !== 'good';
}

function serials(line) {
    return String(line.serials_text || '').split(/\r?\n|,/).map((value) => value.trim()).filter(Boolean);
}

function lineName(line) {
    return line.line_type === 'item'
        ? items.value.find((row) => row.id === line.item_id)?.name || 'Item'
        : equipments.value.find((row) => row.id === line.equipment_id)?.name || 'Equipment';
}

function lineError(line) {
    const delivered = Number(line.delivered_quantity || 0);
    const accepted = Number(line.accepted_quantity || 0);
    const rejected = Number(line.rejected_quantity || 0);
    const expected = Number(line.expected_quantity || 0);

    if (!(line.line_type === 'item' ? line.item_id : line.equipment_id)) return 'Select the item or equipment.';
    if (accepted + rejected !== delivered) return `Accepted (${accepted}) + rejected (${rejected}) must equal delivered (${delivered}).`;
    if (expected > 0 && accepted > expected) return `Accepted cannot exceed expected (${expected}).`;
    if (hasDiscrepancy(line) && !String(line.discrepancy_remarks || '').trim()) return 'Enter discrepancy remarks.';
    if (line.line_type === 'equipment' && tracksSerials(line) && accepted > 0 && serials(line).length !== accepted) {
        return `Enter exactly ${accepted} serial number(s).`;
    }
    return '';
}

function lineSummary(line) {
    const shortfall = Math.max(0, Number(line.expected_quantity || 0) - Number(line.delivered_quantity || 0));
    return `${line.accepted_quantity || 0} will be posted to inventory.${shortfall ? ` ${shortfall} still outstanding on this reference (partial delivery).` : ''}`;
}

function referenceLabel(row) {
    return [row.delivery_reference ? `DR ${row.delivery_reference}` : null, row.purchase_reference ? `PO ${row.purchase_reference}` : null]
        .filter(Boolean)
        .join(' / ') || '—';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}

const priorForReference = computed(() => {
    const refs = [form.delivery_reference, form.purchase_reference].map((value) => String(value || '').trim()).filter(Boolean);
    if (!refs.length) return [];

    const totals = {};
    inspections.value
        .filter((row) => row.id !== form.id && row.status === 'posted')
        .filter((row) => refs.includes(row.delivery_reference) || refs.includes(row.purchase_reference))
        .forEach((row) => (row.lines || []).forEach((line) => {
            const key = line.item_id ? `i${line.item_id}` : `e${line.equipment_id}`;
            const label = line.item?.item_name || line.equipment?.name || 'Line';
            totals[key] = totals[key] || { key, label, accepted: 0 };
            totals[key].accepted += Number(line.accepted_quantity || 0);
        }));

    return Object.values(totals);
});

function payload() {
    return {
        delivery_reference: form.delivery_reference || null,
        purchase_reference: form.purchase_reference || null,
        supplier: form.supplier || null,
        delivery_date: form.delivery_date || null,
        inspection_date: form.inspection_date,
        inspector_name: form.inspector_name,
        remarks: form.remarks || null,
        lines: form.lines.map((line) => ({
            line_type: line.line_type,
            item_id: line.line_type === 'item' ? line.item_id : null,
            equipment_id: line.line_type === 'equipment' ? line.equipment_id : null,
            expected_quantity: Number(line.expected_quantity || 0),
            delivered_quantity: Number(line.delivered_quantity || 0),
            accepted_quantity: Number(line.accepted_quantity || 0),
            rejected_quantity: Number(line.rejected_quantity || 0),
            condition: line.condition,
            discrepancy_remarks: line.discrepancy_remarks || null,
            serial_numbers: line.line_type === 'equipment' ? serials(line) : [],
        })),
    };
}

function validate() {
    if (!form.delivery_reference?.trim() && !form.purchase_reference?.trim()) {
        notify.warn('Enter the delivery receipt or purchase order reference.');
        return false;
    }
    if (!form.inspection_date || !form.inspector_name?.trim()) {
        notify.warn('Enter the inspection date and inspector.');
        return false;
    }
    const error = form.lines.map(lineError).find(Boolean);
    if (error) {
        notify.warn(error);
        return false;
    }
    return true;
}

async function save() {
    if (!validate()) return false;
    saving.value = true;
    try {
        const { data } = form.id
            ? await api.put(`/delivery-inspections/${form.id}`, payload())
            : await api.post('/delivery-inspections', payload());
        fill(data);
        notify.success(`Inspection ${data.inspection_number} saved as draft.`);
        await loadInspections();
        return true;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save the inspection.');
        return false;
    } finally {
        saving.value = false;
    }
}

async function saveAndConfirm() {
    if (await save()) {
        confirmVisible.value = true;
    }
}

async function post() {
    saving.value = true;
    try {
        const { data } = await api.post(`/delivery-inspections/${form.id}/post`);
        fill(data.inspection);
        confirmVisible.value = false;
        data.already_posted ? notify.info(data.message, 'Already posted') : notify.success(data.message, 'Posted');
        await Promise.all([loadInspections(), loadLookups()]);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to post the inspection.');
    } finally {
        saving.value = false;
    }
}

function fill(data) {
    Object.assign(form, {
        id: data.id,
        inspection_number: data.inspection_number,
        status: data.status,
        posted_at: data.posted_at,
        delivery_reference: data.delivery_reference || '',
        purchase_reference: data.purchase_reference || '',
        supplier: data.supplier || '',
        delivery_date: (data.delivery_date || '').slice(0, 10),
        inspection_date: (data.inspection_date || '').slice(0, 10),
        inspector_name: data.inspector_name || '',
        remarks: data.remarks || '',
        lines: (data.lines || []).map((line) => ({
            line_type: line.line_type,
            item_id: line.item_id,
            equipment_id: line.equipment_id,
            expected_quantity: line.expected_quantity,
            delivered_quantity: line.delivered_quantity,
            accepted_quantity: line.accepted_quantity,
            rejected_quantity: line.rejected_quantity,
            condition: line.condition,
            discrepancy_remarks: line.discrepancy_remarks || '',
            serials_text: (line.serial_numbers || []).join('\n'),
        })),
    });
}

async function edit(row) {
    try {
        const { data } = await api.get(`/delivery-inspections/${row.id}`);
        fill(data);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the inspection.');
    }
}

function removeDraft(row) {
    confirmDelete({
        title: 'Delete draft inspection?',
        message: `Delete ${row.inspection_number}?`,
        detail: 'Drafts have not changed inventory.',
        onAccept: async () => {
            await api.delete(`/delivery-inspections/${row.id}`);
            if (form.id === row.id) newInspection();
            await loadInspections();
        },
    });
}

async function exportList() {
    try {
        await downloadFile('/reports/delivery-inspections/xlsx', {}, 'Delivery-Inspection-Report.xlsx');
    } catch (error) {
        notify.error(error.message, 'Export failed');
    }
}

async function loadInspections() {
    loading.value = true;
    try {
        const { data } = await api.get('/delivery-inspections/list');
        inspections.value = data;
    } finally {
        loading.value = false;
    }
}

async function loadLookups() {
    const [itemRes, equipmentRes] = await Promise.all([
        api.get('/items/list', { params: { all: 1 } }),
        api.get('/equipments/list'),
    ]);
    const itemRows = Array.isArray(itemRes.data) ? itemRes.data : itemRes.data?.data ?? [];
    items.value = itemRows.map((item) => ({
        id: item.id,
        name: item.item_name,
        label: `${item.item_name} (${item.barcode || item.item_number}) · Stock ${item.current_stock}`,
    }));
    equipments.value = (equipmentRes.data ?? []).map((equipment) => ({
        id: equipment.id,
        name: equipment.name,
        tracks_serials: (equipment.serial_numbers || []).length > 0,
        label: `${equipment.name} (${equipment.property_number}) · Available ${equipment.quantity}`,
    }));
}

onMounted(async () => {
    newInspection();
    try {
        await Promise.all([loadInspections(), loadLookups()]);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load delivery inspections.');
    }
});
</script>

<style scoped>
.line-label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #00164d;
}
</style>
