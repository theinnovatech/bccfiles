<template>
    <div class="space-y-6">
        <div class="shadcn-page-header">
            <h1 class="shadcn-page-title">Equipment Dashboard</h1>
            <p class="shadcn-page-description">
                Units by disposition. Select a count to open the matching list. Condition,
                custody and disposition are tracked separately; warranty reviews are shown on
                their own because they overlap with a status.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <button
                v-for="card in statusCards"
                :key="card.key"
                type="button"
                class="equipment-stat"
                :class="{ 'equipment-stat-active': status === card.key }"
                :aria-pressed="status === card.key"
                @click="setStatus(card.key)"
            >
                <span class="equipment-stat-label">{{ card.label }}</span>
                <span class="equipment-stat-value">{{ counts[card.key] ?? 0 }}</span>
                <span class="equipment-stat-hint">{{ card.hint }}</span>
            </button>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <button
                type="button"
                class="equipment-side-panel"
                :class="{ 'equipment-stat-active': status === 'warranty' }"
                @click="setStatus('warranty')"
            >
                <div>
                    <p class="text-sm font-semibold text-[#00164d]">Active warranty cases</p>
                    <p class="text-xs text-[#4a6490]">
                        Cases opened while recorded coverage was active. Ordinary repair or
                        disposal waits for the supplier / warranty decision.
                    </p>
                </div>
                <span class="equipment-side-value">{{ counts.warranty_active ?? 0 }}</span>
            </button>
            <button
                type="button"
                class="equipment-side-panel"
                :class="{ 'equipment-stat-active': status === 'awaiting_assessment' }"
                @click="setStatus('awaiting_assessment')"
            >
                <div>
                    <p class="text-sm font-semibold text-[#00164d]">Unserviceable</p>
                    <p class="text-xs text-[#4a6490]">
                        Returned Unserviceable and not yet assessed. Open a For Repair or For Disposal case.
                    </p>
                </div>
                <span class="equipment-side-value">{{ counts.awaiting_assessment ?? 0 }}</span>
            </button>
        </div>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">{{ listTitle }}</h3>
                        <p class="shadcn-card-description">{{ listDescription }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="outline" size="sm" :loading="exporting" @click="exportList">
                            Export (.xlsx)
                        </UiButton>
                        <UiButton variant="outline" size="sm" @click="openIirup('pdf')">IIRUP (PDF)</UiButton>
                        <UiButton variant="outline" size="sm" @click="downloadIirup">IIRUP (.xlsx)</UiButton>
                    </div>
                </div>
            </template>

            <div class="mb-3 max-w-md">
                <label class="mb-1 block text-xs font-medium text-[#4a6490]">Search</label>
                <input v-model="search" type="search" class="shadcn-input" placeholder="Name, property no., serial, case no., holder..." />
            </div>

            <div class="obims-table-wrap">
                <DataTable
                    :value="visibleRows"
                    :loading="loading"
                    paginator
                    :rows="15"
                    data-key="key"
                    class="rounded-md border border-[#a8b8d4]"
                >
                    <Column v-if="isCaseList" field="case_number" header="Case No." />
                    <Column field="name" header="Equipment">
                        <template #body="{ data }">
                            <div class="font-medium text-[#00164d]">{{ data.name || '—' }}</div>
                            <div v-if="data.brand_model" class="text-xs text-[#00164d]">{{ data.brand_model }}</div>
                            <div class="text-xs text-[#4a6490]">{{ data.category || '' }}</div>
                            <div v-if="data.property_class_label" class="text-xs text-[#4a6490]">{{ data.property_class_label }}</div>
                        </template>
                    </Column>
                    <Column header="Property / Inventory No.">
                        <template #body="{ data }">
                            <div>{{ data.property_number || '—' }}</div>
                            <div class="text-xs text-[#4a6490]">{{ data.inventory_number || '' }}</div>
                        </template>
                    </Column>
                    <Column header="Serial No(s).">
                        <template #body="{ data }"><span class="text-sm">{{ data.serial_number || '—' }}</span></template>
                    </Column>
                    <Column field="quantity" header="Qty" />
                    <Column header="Status">
                        <template #body="{ data }">
                            <UiBadge :variant="statusVariant(data.status)">{{ data.status_label }}</UiBadge>
                            <div v-if="data.warranty_active" class="mt-1">
                                <UiBadge variant="gold">Warranty review</UiBadge>
                            </div>
                        </template>
                    </Column>
                    <Column header="Condition / Custody">
                        <template #body="{ data }">
                            <div class="text-sm">{{ data.condition_label || '—' }}</div>
                            <div class="text-xs text-[#4a6490]">{{ data.custody_label || '' }}<template v-if="data.holder"> · {{ data.holder }}</template></div>
                            <div v-if="data.unit" class="text-xs text-[#4a6490]">{{ data.unit }}</div>
                        </template>
                    </Column>
                    <Column header="Date">
                        <template #body="{ data }">{{ data.date || data.date_acquired || '—' }}</template>
                    </Column>
                    <Column v-if="canAct" header="Actions" style="width: 11rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <template v-if="data.row_type === 'listing'">
                                    <UiButton variant="ghost" size="sm" @click="openNewCase(data, 'stock')">Report issue</UiButton>
                                    <UiButton variant="ghost" size="sm" @click="openWarranty(data)">Warranty</UiButton>
                                </template>
                                <UiButton v-else-if="data.row_type === 'return'" variant="ghost" size="sm" @click="openNewCase(data, 'return')">Assess</UiButton>
                                <UiButton v-else-if="data.row_type === 'case'" variant="ghost" size="sm" @click="openCase(data)">Open case</UiButton>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>

        <!-- Open a case -->
        <Dialog v-model:visible="newCaseVisible" modal header="Report issue / open case" :style="{ width: '560px' }">
            <div v-if="newCaseRow" class="space-y-4 pt-2 text-sm">
                <div class="rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3">
                    <p class="font-semibold text-[#00164d]">{{ newCaseRow.name }}<span v-if="newCaseRow.brand_model" class="font-normal text-[#4a6490]"> · {{ newCaseRow.brand_model }}</span></p>
                    <p class="text-xs text-[#4a6490]">
                        {{ newCaseRow.property_number }} · {{ newCase.origin === 'stock' ? 'Supply Office stock' : 'Unserviceable return' }}
                    </p>
                    <p v-if="newCaseRow.warranty_covered" class="mt-2 text-xs font-medium text-[#8a6a00]">
                        Recorded warranty coverage is active. The case will be routed for supplier /
                        warranty review first.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block font-medium text-[#00164d]">Open as <span class="text-[#ce1126]">*</span></label>
                        <Select v-model="newCase.status" :options="openOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div v-if="newCaseRow.serial_options?.length">
                        <label class="mb-1 block font-medium text-[#00164d]">Serial No. <span class="text-[#ce1126]">*</span></label>
                        <Select v-model="newCase.serial_number_id" :options="newCaseRow.serial_options" optionLabel="serial_number" optionValue="id" filter class="w-full" placeholder="Select unit" />
                    </div>
                    <div v-else>
                        <label class="mb-1 block font-medium text-[#00164d]">Quantity <span class="text-[#ce1126]">*</span></label>
                        <InputNumber v-model="newCase.quantity" :min="1" :max="newCaseRow.quantity" class="w-full" inputClass="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block font-medium text-[#00164d]">Date reported <span class="text-[#ce1126]">*</span></label>
                        <InputText v-model="newCase.event_date" type="date" :max="today" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block font-medium text-[#00164d]">Supporting reference <span class="text-[#ce1126]">*</span></label>
                        <InputText v-model="newCase.reference" class="w-full" placeholder="Job order, memo, IIRUP no." />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block font-medium text-[#00164d]">Issue / reason <span class="text-[#ce1126]">*</span></label>
                    <Textarea v-model="newCase.reason" rows="3" class="w-full" />
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="newCaseVisible = false">Cancel</UiButton>
                <UiButton :loading="saving" @click="submitNewCase">Open case</UiButton>
            </template>
        </Dialog>

        <!-- Case detail and transitions -->
        <Dialog v-model:visible="caseVisible" modal :header="activeCase ? `Case ${activeCase.case_number}` : 'Case'" :style="{ width: '760px' }">
            <div v-if="activeCase" class="space-y-4 pt-2 text-sm text-[#00164d]">
                <div class="grid gap-3 rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3 sm:grid-cols-3">
                    <div><p class="case-label">Equipment</p><p class="font-medium">{{ activeCase.name }}<span v-if="activeCase.brand_model" class="font-normal text-[#4a6490]"> · {{ activeCase.brand_model }}</span></p><p class="text-xs text-[#4a6490]">{{ activeCase.property_number }}<template v-if="activeCase.serial_number"> · SN {{ activeCase.serial_number }}</template></p></div>
                    <div><p class="case-label">Disposition</p><UiBadge :variant="statusVariant(activeCase.status)">{{ activeCase.status_label }}</UiBadge></div>
                    <div><p class="case-label">Condition / Custody</p><p>{{ activeCase.condition_label }} · {{ activeCase.custody_label }}</p></div>
                    <div><p class="case-label">Reported</p><p>{{ activeCase.reported_on }} by {{ activeCase.reported_by || '—' }}</p></div>
                    <div class="sm:col-span-2"><p class="case-label">Issue</p><p>{{ activeCase.reported_issue }}</p></div>
                    <div v-if="activeCase.warranty_status"><p class="case-label">Warranty</p>
                        <p v-if="activeCase.warranty_active" class="font-medium text-[#8a6a00]">Pending supplier / warranty review (coverage until {{ activeCase.warranty_end_date }})</p>
                        <p v-else>{{ activeCase.warranty_outcome_label }} · Claim {{ activeCase.warranty_claim_reference }}</p>
                    </div>
                    <div v-if="activeCase.repair_outcome"><p class="case-label">Repair outcome</p><p>{{ activeCase.repair_outcome }}</p></div>
                    <div v-if="activeCase.serviceability_passed !== null && activeCase.serviceability_passed !== undefined"><p class="case-label">Serviceability check</p><p>{{ activeCase.serviceability_passed ? 'Passed' : 'Failed' }} ({{ activeCase.serviceability_checked_on }})</p></div>
                    <div v-if="activeCase.disposal_assessment"><p class="case-label">Disposal assessment</p><p>{{ activeCase.disposal_assessment }}</p></div>
                    <div v-if="activeCase.disposal_approval_status"><p class="case-label">Disposal approval</p><p class="capitalize">{{ activeCase.disposal_approval_status }}<template v-if="activeCase.disposal_approved_by_name"> · {{ activeCase.disposal_approved_by_name }} ({{ activeCase.disposal_approval_reference }})</template></p></div>
                    <div v-if="activeCase.disposal_date"><p class="case-label">Disposed</p><p>{{ activeCase.disposal_date }} · {{ activeCase.disposal_method || '—' }} · {{ activeCase.disposal_reference }}</p></div>
                    <div><p class="case-label">Property class</p><p>{{ activeCase.property_class_label || 'Not classified' }}</p></div>
                    <div v-if="hasIirupValues" class="sm:col-span-3">
                        <p class="case-label">IIRUP values</p>
                        <p class="text-xs">
                            Unit cost {{ money(activeCase.iirup.unit_cost) }} · Impairment {{ money(activeCase.iirup.accumulated_impairment) }}
                            · Carrying {{ money(activeCase.iirup.carrying_amount) }} · Appraised {{ money(activeCase.iirup.appraised_value) }}
                            · Disposal: {{ disposalSummary || '—' }}
                            <template v-if="activeCase.iirup.or_number"> · OR {{ activeCase.iirup.or_number }} ({{ money(activeCase.iirup.sale_amount) }})</template>
                        </p>
                    </div>
                </div>
                <p
                    v-if="['for_disposal', 'disposed'].includes(activeCase.status) && activeCase.property_class !== 'semi_expendable'"
                    class="text-xs text-[#8a6a00]"
                >
                    Not listed on the IIRUP: set this equipment's property class to "Semi-expendable property" (Supply Master > Equipment > Edit).
                </p>

                <div v-if="availableActions.length && canAct" class="space-y-3 rounded-md border border-[#a8b8d4] p-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="action in availableActions"
                            :key="action.key"
                            type="button"
                            class="case-action"
                            :class="{ 'case-action-active': transition.action === action.key }"
                            @click="selectAction(action.key)"
                        >
                            {{ action.label }}
                        </button>
                    </div>

                    <template v-if="transition.action">
                        <p class="text-xs text-[#4a6490]">{{ actionHelp }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div v-if="transition.action === 'warranty-decision'">
                                <label class="mb-1 block font-medium">Outcome <span class="text-[#ce1126]">*</span></label>
                                <Select v-model="transition.outcome" :options="warrantyOutcomes" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <div v-if="transition.action === 'serviceability'">
                                <label class="mb-1 block font-medium">Result <span class="text-[#ce1126]">*</span></label>
                                <Select v-model="transition.passed" :options="[{ label: 'Passed — clear for issuance', value: true }, { label: 'Failed', value: false }]" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <template v-if="transition.action === 'disposal-approval'">
                                <div>
                                    <label class="mb-1 block font-medium">Decision <span class="text-[#ce1126]">*</span></label>
                                    <Select v-model="transition.approval_status" :options="[{ label: 'Approved', value: 'approved' }, { label: 'Rejected', value: 'rejected' }]" optionLabel="label" optionValue="value" class="w-full" />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium">Approved / decided by <span class="text-[#ce1126]">*</span></label>
                                    <InputText v-model="transition.approved_by_name" class="w-full" />
                                </div>
                            </template>
                            <template v-if="transition.action === 'iirup-values'">
                                <div v-for="field in iirupMoneyFields" :key="field.key">
                                    <label class="mb-1 block font-medium">{{ field.label }}</label>
                                    <InputNumber v-model="transition[field.key]" mode="decimal" :minFractionDigits="2" :maxFractionDigits="2" :min="0" class="w-full" inputClass="w-full" placeholder="Blank if not supplied" />
                                </div>
                                <div v-for="field in iirupQtyFields" :key="field.key">
                                    <label class="mb-1 block font-medium">{{ field.label }}</label>
                                    <InputNumber v-model="transition[field.key]" :min="0" :max="activeCase.quantity" class="w-full" inputClass="w-full" placeholder="—" />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium">Others (Specify)</label>
                                    <InputText v-model="transition.disposal_others_specify" class="w-full" placeholder="e.g. Donation" />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium">Record of sale — OR No.</label>
                                    <InputText v-model="transition.or_number" class="w-full" />
                                </div>
                            </template>
                            <div v-if="transition.action === 'dispose'">
                                <label class="mb-1 block font-medium">Disposal method</label>
                                <InputText v-model="transition.method" class="w-full" placeholder="Sale, transfer, destruction..." />
                            </div>
                            <div>
                                <label class="mb-1 block font-medium">Date <span class="text-[#ce1126]">*</span></label>
                                <InputText v-model="transition.event_date" type="date" :max="today" :min="activeCase.reported_on" class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block font-medium">{{ referenceLabel }} <span class="text-[#ce1126]">*</span></label>
                                <InputText v-model="transition.reference" class="w-full" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block font-medium">{{ reasonLabel }} <span class="text-[#ce1126]">*</span></label>
                            <Textarea v-model="transition.reason" rows="2" class="w-full" />
                        </div>
                        <div class="flex justify-end">
                            <UiButton :loading="saving" @click="submitTransition">Record</UiButton>
                        </div>
                    </template>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#4a6490]">History</p>
                    <ol class="space-y-2">
                        <li v-for="event in activeCase.events" :key="event.id" class="rounded-md border border-[#d7e0ef] p-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">{{ event.action_label }}</span>
                                <span class="text-xs text-[#4a6490]">{{ event.event_date }} · {{ event.performed_by || '—' }}</span>
                            </div>
                            <p class="text-xs text-[#4a6490]">
                                <template v-if="event.from_status || event.to_status">{{ event.from_status || '—' }} → {{ event.to_status || '—' }} · </template>
                                Ref: {{ event.reference || '—' }}
                            </p>
                            <p class="mt-1">{{ event.reason }}</p>
                        </li>
                    </ol>
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="caseVisible = false">Close</UiButton>
            </template>
        </Dialog>

        <!-- Warranty coverage -->
        <Dialog v-model:visible="warrantyVisible" modal header="Recorded warranty coverage" :style="{ width: '460px' }">
            <div class="grid gap-3 pt-2 text-sm">
                <div>
                    <label class="mb-1 block font-medium text-[#00164d]">Supplier / warranty provider</label>
                    <InputText v-model="warranty.warranty_provider" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block font-medium text-[#00164d]">Warranty reference</label>
                    <InputText v-model="warranty.warranty_reference" class="w-full" placeholder="Certificate / PO no." />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block font-medium text-[#00164d]">Start</label>
                        <InputText v-model="warranty.warranty_start_date" type="date" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block font-medium text-[#00164d]">End</label>
                        <InputText v-model="warranty.warranty_end_date" type="date" class="w-full" />
                    </div>
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="warrantyVisible = false">Cancel</UiButton>
                <UiButton :loading="saving" @click="saveWarranty">Save</UiButton>
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
import { useNotify } from '../../composables/useNotify';
import { useAuthStore } from '../../stores/auth';
import { downloadFile, openInNewTab } from '../../utils/downloads';
import { todayIso } from '../../utils/idempotency';
import api from '../../services/api';

const notify = useNotify();
const auth = useAuthStore();
const today = todayIso();

const statusCards = [
    { key: 'available', label: 'Available', hint: 'In Supply Office, serviceable' },
    { key: 'issued', label: 'Issued', hint: 'In employee custody' },
    { key: 'for_repair', label: 'For Repair', hint: 'Blocked from issuance' },
    { key: 'for_disposal', label: 'For Disposal', hint: 'Awaiting approval / disposal' },
    { key: 'disposed', label: 'Disposed', hint: 'History preserved' },
];

const listMeta = {
    available: ['Available equipment', 'Serviceable units in Supply Office custody that can be issued.'],
    issued: ['Issued equipment', 'Outstanding issued units by holder.'],
    for_repair: ['For Repair', 'Units under repair. They return to Available only after passing a serviceability check.'],
    for_disposal: ['For Disposal', 'Units assessed for disposal. Disposed requires approval, date and disposal reference.'],
    disposed: ['Disposed', 'Disposed units with their full case history.'],
    warranty: ['Active warranty cases', 'Record the supplier / warranty decision (claim reference and outcome) before ordinary repair or disposal.'],
    awaiting_assessment: ['Unserviceable', 'Units returned Unserviceable. Open a For Repair or For Disposal case for each unit.'],
};

const counts = ref({});
const rows = ref([]);
const loading = ref(false);
const saving = ref(false);
const exporting = ref(false);
const search = ref('');
const status = ref(initialStatus());
const warrantyOutcomesMap = ref({});

const canAct = computed(() => auth.isAdmin || auth.isSupplyOfficer);
const isCaseList = computed(() => ['for_repair', 'for_disposal', 'disposed', 'warranty'].includes(status.value));
const listTitle = computed(() => listMeta[status.value]?.[0] ?? 'Equipment');
const listDescription = computed(() => listMeta[status.value]?.[1] ?? '');
const warrantyOutcomes = computed(() => Object.entries(warrantyOutcomesMap.value).map(([value, label]) => ({ value, label })));

const visibleRows = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return rows.value;
    return rows.value.filter((row) =>
        [row.name, row.brand, row.model, row.property_number, row.inventory_number, row.serial_number, row.case_number, row.holder, row.unit, row.category]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

function initialStatus() {
    const value = new URLSearchParams(window.location.search).get('status');
    return value && listMeta[value] ? value : 'available';
}

function statusVariant(value) {
    return {
        available: 'default',
        issued: 'secondary',
        for_repair: 'gold',
        for_disposal: 'destructive',
        disposed: 'outline',
        awaiting_assessment: 'gold',
        returned_to_service: 'default',
    }[value] || 'outline';
}

function setStatus(value) {
    status.value = value;
    const url = new URL(window.location.href);
    url.searchParams.set('status', value);
    window.history.replaceState({}, '', url);
    loadList();
}

async function loadSummary() {
    const { data } = await api.get('/equipment-status/summary');
    counts.value = data.counts;
    warrantyOutcomesMap.value = data.warranty_outcomes || {};
}

async function loadList() {
    loading.value = true;
    try {
        const { data } = await api.get('/equipment-status/list', { params: { status: status.value } });
        rows.value = data.rows;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the equipment list.');
    } finally {
        loading.value = false;
    }
}

// ---- open a case
const newCaseVisible = ref(false);
const newCaseRow = ref(null);
const newCase = reactive({});
const openOptions = [
    { label: 'For Repair', value: 'for_repair' },
    { label: 'For Disposal', value: 'for_disposal' },
];

function openNewCase(row, origin) {
    newCaseRow.value = row;
    Object.assign(newCase, {
        origin,
        equipment_id: row.equipment_id,
        item_return_id: row.item_return_id ?? null,
        serial_number_id: null,
        quantity: 1,
        status: 'for_repair',
        event_date: today,
        reference: row.return_reference || '',
        reason: row.reason || '',
    });
    newCaseVisible.value = true;
}

async function submitNewCase() {
    if (!newCase.reason?.trim() || !newCase.reference?.trim() || !newCase.event_date) {
        notify.warn('Enter the issue, date and supporting reference.');
        return;
    }
    if (newCaseRow.value?.serial_options?.length && !newCase.serial_number_id) {
        notify.warn('Select the serial number of the unit.');
        return;
    }

    saving.value = true;
    try {
        const { data } = await api.post('/equipment-status/cases', { ...newCase });
        counts.value = data.counts;
        newCaseVisible.value = false;
        notify.success(`Case ${data.case.case_number} opened as ${data.case.status_label}.${data.case.warranty_active ? ' Routed for warranty review.' : ''}`);
        await loadList();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to open the case.');
    } finally {
        saving.value = false;
    }
}

// ---- case transitions
const caseVisible = ref(false);
const activeCase = ref(null);
const transition = reactive({ action: '' });

const ACTIONS = {
    'warranty-decision': { label: 'Record warranty decision', help: 'Document the supplier / warranty decision. Required before ordinary repair or disposal.', reference: 'Claim reference', reason: 'Decision notes' },
    repair: { label: 'Record repair outcome', help: 'Describe what was repaired or replaced.', reference: 'Job order / repair reference', reason: 'Repair outcome' },
    serviceability: { label: 'Serviceability check', help: 'The unit returns to Available only after passing this check.', reference: 'Inspection reference', reason: 'Findings' },
    'return-to-available': { label: 'Return to Available', help: 'Moves the unit back to Supply Office stock, clear for issuance.', reference: 'Reference', reason: 'Remarks' },
    'move-to-disposal': { label: 'Move to For Disposal', help: 'Record the assessment that the unit should be disposed.', reference: 'Assessment reference', reason: 'Assessment' },
    'back-to-repair': { label: 'Back to For Repair', help: 'Disposal not approved; return the unit to repair.', reference: 'Reference', reason: 'Reason' },
    'disposal-assessment': { label: 'Record assessment', help: 'Assessment of the unit for disposal (required before approval).', reference: 'Assessment / IIRUP reference', reason: 'Assessment' },
    'disposal-approval': { label: 'Record approval', help: 'Administrator only. Record the approving authority and reference.', reference: 'Approval reference', reason: 'Remarks' },
    dispose: { label: 'Mark Disposed', help: 'Requires approved disposal. Enter the disposal date and reference.', reference: 'Disposal reference / OR no.', reason: 'Remarks' },
    'iirup-values': { label: 'IIRUP values', help: 'Values printed on the IIRUP, as supplied by Accounting and the inspection committee. Leave unknown values blank; nothing is calculated. Status does not change.', reference: 'Source document (e.g. Accounting memo)', reason: 'Remarks' },
};

const iirupMoneyFields = [
    { key: 'unit_cost', label: 'Unit cost' },
    { key: 'accumulated_impairment', label: 'Accumulated impairment losses' },
    { key: 'carrying_amount', label: 'Carrying amount' },
    { key: 'appraised_value', label: 'Appraised value' },
    { key: 'sale_amount', label: 'Record of sale — Amount' },
];

const iirupQtyFields = [
    { key: 'disposal_sale_qty', label: 'Disposal — Sale (qty)' },
    { key: 'disposal_transfer_qty', label: 'Disposal — Transfer (qty)' },
    { key: 'disposal_destruction_qty', label: 'Disposal — Destruction (qty)' },
    { key: 'disposal_others_qty', label: 'Disposal — Others (qty)' },
];

const hasIirupValues = computed(() => Object.values(activeCase.value?.iirup || {}).some((value) => value !== null && value !== ''));

const disposalSummary = computed(() => {
    const v = activeCase.value?.iirup || {};
    return [
        v.disposal_sale_qty ? `Sale ${v.disposal_sale_qty}` : null,
        v.disposal_transfer_qty ? `Transfer ${v.disposal_transfer_qty}` : null,
        v.disposal_destruction_qty ? `Destruction ${v.disposal_destruction_qty}` : null,
        v.disposal_others_qty ? `Others ${v.disposal_others_qty} (${v.disposal_others_specify})` : null,
    ].filter(Boolean).join(', ');
});

function money(value) {
    return value === null || value === undefined || value === ''
        ? '—'
        : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const availableActions = computed(() => {
    const c = activeCase.value;
    if (!c) return [];
    if (c.status === 'disposed') return [{ key: 'iirup-values', label: ACTIONS['iirup-values'].label }];
    if (c.closed_at) return [];
    if (c.warranty_active) return [{ key: 'warranty-decision', label: ACTIONS['warranty-decision'].label }];

    const keys = [];
    if (c.status === 'for_repair') {
        keys.push('repair', 'serviceability');
        if (c.serviceability_passed === true) keys.push('return-to-available');
        keys.push('move-to-disposal');
    }
    if (c.status === 'for_disposal') {
        keys.push('disposal-assessment');
        if (auth.isAdmin) keys.push('disposal-approval');
        if (c.disposal_approval_status === 'approved') keys.push('dispose');
        if (c.disposal_approval_status !== 'approved') keys.push('back-to-repair');
        keys.push('iirup-values');
    }

    return keys.map((key) => ({ key, label: ACTIONS[key].label }));
});

const actionHelp = computed(() => ACTIONS[transition.action]?.help ?? '');
const referenceLabel = computed(() => ACTIONS[transition.action]?.reference ?? 'Reference');
const reasonLabel = computed(() => ACTIONS[transition.action]?.reason ?? 'Reason');

function selectAction(key) {
    Object.keys(transition).forEach((field) => delete transition[field]);
    Object.assign(transition, { action: key, event_date: today, reference: '', reason: '' });

    if (key === 'iirup-values') {
        const v = activeCase.value?.iirup || {};
        const num = (value) => (value === null || value === undefined || value === '' ? null : Number(value));
        Object.assign(transition, {
            unit_cost: num(v.unit_cost ?? activeCase.value?.equipment_unit_cost),
            accumulated_impairment: num(v.accumulated_impairment),
            carrying_amount: num(v.carrying_amount),
            appraised_value: num(v.appraised_value),
            sale_amount: num(v.sale_amount),
            disposal_sale_qty: num(v.disposal_sale_qty),
            disposal_transfer_qty: num(v.disposal_transfer_qty),
            disposal_destruction_qty: num(v.disposal_destruction_qty),
            disposal_others_qty: num(v.disposal_others_qty),
            disposal_others_specify: v.disposal_others_specify || '',
            or_number: v.or_number || '',
        });
    }
}

async function openCase(row) {
    try {
        const { data } = await api.get(`/equipment-status/cases/${row.case_id}`);
        activeCase.value = data;
        Object.keys(transition).forEach((field) => delete transition[field]);
        transition.action = '';
        caseVisible.value = true;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to open the case.');
    }
}

async function submitTransition() {
    if (!transition.reason?.trim() || !transition.reference?.trim() || !transition.event_date) {
        notify.warn('Enter the date, supporting reference and remarks.');
        return;
    }

    const { action, ...payload } = transition;
    saving.value = true;
    try {
        const { data } = await api.post(`/equipment-status/cases/${activeCase.value.case_id}/${action}`, payload);
        activeCase.value = data.case;
        counts.value = data.counts;
        transition.action = '';
        notify.success(`${ACTIONS[action].label} recorded.`);
        await loadList();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to record this action.');
    } finally {
        saving.value = false;
    }
}

// ---- warranty coverage
const warrantyVisible = ref(false);
const warranty = reactive({});
let warrantyEquipmentId = null;

async function openWarranty(row) {
    warrantyEquipmentId = row.equipment_id;
    try {
        const { data } = await api.get(`/equipments/${row.equipment_id}`);
        Object.assign(warranty, {
            warranty_provider: data.warranty_provider || '',
            warranty_reference: data.warranty_reference || '',
            warranty_start_date: (data.warranty_start_date || '').slice(0, 10),
            warranty_end_date: (data.warranty_end_date || '').slice(0, 10),
        });
        warrantyVisible.value = true;
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the equipment.');
    }
}

async function saveWarranty() {
    saving.value = true;
    try {
        const payload = Object.fromEntries(Object.entries(warranty).map(([key, value]) => [key, value || null]));
        await api.put(`/equipments/${warrantyEquipmentId}/warranty`, payload);
        warrantyVisible.value = false;
        notify.success('Warranty coverage saved.');
        await loadList();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to save warranty coverage.');
    } finally {
        saving.value = false;
    }
}

// ---- exports
async function exportList() {
    exporting.value = true;
    try {
        await downloadFile('/reports/equipment-status/xlsx', { status: status.value, search: search.value || undefined }, 'Equipment-Status-Report.xlsx');
    } catch (error) {
        notify.error(error.message, 'Export failed');
    } finally {
        exporting.value = false;
    }
}

function openIirup(format) {
    openInNewTab(`/forms/iirup/${format}`, { preview: 1 });
}

async function downloadIirup() {
    try {
        await downloadFile('/forms/iirup/xlsx', {}, 'IIRUP.xlsx');
    } catch (error) {
        notify.error(error.message, 'Download failed');
    }
}

onMounted(async () => {
    try {
        await Promise.all([loadSummary(), loadList()]);
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load the equipment dashboard.');
    }
});
</script>

<style scoped>
.equipment-stat {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    border: 1px solid #a8b8d4;
    background: #fff;
    padding: 1rem;
    text-align: left;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.equipment-stat:hover,
.equipment-side-panel:hover {
    border-color: #001f6b;
}

.equipment-stat:focus-visible,
.equipment-side-panel:focus-visible {
    outline: 2px solid #001f6b;
    outline-offset: 2px;
}

.equipment-stat-active {
    border-color: #001f6b;
    box-shadow: inset 4px 0 0 #001f6b;
}

.equipment-stat-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #4a6490;
}

.equipment-stat-value {
    font-size: 1.875rem;
    font-weight: 700;
    color: #00164d;
    font-variant-numeric: tabular-nums;
}

.equipment-stat-hint {
    font-size: 0.75rem;
    color: #4a6490;
}

.equipment-side-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border: 1px solid #a8b8d4;
    background: #fff;
    padding: 0.875rem 1rem;
    text-align: left;
}

.equipment-side-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #00164d;
    font-variant-numeric: tabular-nums;
}

.case-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4a6490;
}

.case-action {
    border: 1px solid #a8b8d4;
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #00164d;
}

.case-action-active {
    border-color: #001f6b;
    background: #001f6b;
    color: #fff;
}
</style>
