<template>
    <div class="space-y-6">
        <div class="shadcn-page-header">
            <h1 class="shadcn-page-title">Scan to Issue</h1>
            <p class="shadcn-page-description">
                Scan a QR code or barcode, review the recipient, quantity and item details, then
                confirm. Confirmation runs through the regular issuance workflow, so stock,
                custody and the audit history stay consistent.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <UiCard title="1. Scan or look up">
                <StockOpScanner
                    ref="scanner"
                    v-model="code"
                    title="Scan QR code / barcode"
                    hint="Supply barcode / item no., equipment serial no., property no. or inventory no. Press Enter after typing."
                    placeholder="Scan or type the code, then press Enter"
                    @scan="lookup"
                />

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <UiButton variant="outline" size="sm" @click="toggleCamera">
                        {{ cameraActive ? 'Stop camera' : 'Use camera' }}
                    </UiButton>
                    <span class="text-xs text-[#4a6490]">Camera scanning needs HTTPS or localhost.</span>
                </div>
                <div v-show="cameraActive" id="scan-issue-camera" class="mt-3 overflow-hidden rounded-md border border-[#a8b8d4]" />

                <div class="mt-5 border-t border-[#d7e0ef] pt-4">
                    <p class="mb-1 text-sm font-medium text-[#00164d]">Manual lookup (unreadable code)</p>
                    <AutoComplete
                        v-model="manual"
                        :suggestions="manualSuggestions"
                        optionLabel="label"
                        forceSelection
                        dropdown
                        placeholder="Search supply or equipment by name, code or property no."
                        class="w-full"
                        inputClass="w-full"
                        @complete="searchManual"
                        @option-select="lookup($event.value.code)"
                    />
                </div>

                <div v-if="lookupError" class="mt-4 rounded-md border border-[#ce1126] bg-[#fff5f5] p-3 text-sm text-[#00164d]" role="alert">
                    {{ lookupError }}
                </div>

                <div v-if="result" class="mt-4 space-y-3">
                    <div
                        class="rounded-md border p-4"
                        :class="result.available ? 'border-[#a8b8d4] bg-[#f4f7fb]' : 'border-[#ce1126] bg-[#fff5f5]'"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                                    {{ result.type === 'item' ? 'Supply item' : result.match === 'serial_number' ? 'Equipment unit' : 'Equipment' }}
                                </p>
                                <p class="text-base font-semibold text-[#00164d]">{{ title }}</p>
                            </div>
                            <UiBadge :variant="result.available ? 'default' : 'destructive'">
                                {{ result.available ? 'Available' : 'Not available' }}
                            </UiBadge>
                        </div>
                        <p v-if="!result.available" class="mt-2 text-sm font-medium text-[#ce1126]">{{ result.reason }}</p>

                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <template v-for="row in detailRows" :key="row.label">
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]">{{ row.label }}</dt>
                                    <dd class="text-[#00164d]">{{ row.value ?? '—' }}</dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </div>
            </UiCard>

            <UiCard title="2. Review and confirm">
                <div v-if="!result || !result.available" class="py-10 text-center text-sm text-[#4a6490]">
                    Scan an available supply item or equipment unit to continue.
                </div>
                <form v-else class="space-y-4" @submit.prevent="openConfirm">
                    <RecipientPicker ref="recipientPicker" v-model="recipient" :disabled="submitting" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[#00164d]">Quantity <span class="text-[#ce1126]">*</span></label>
                            <InputNumber
                                v-model="review.quantity"
                                :min="1"
                                :max="maxQuantity"
                                :disabled="quantityLocked"
                                class="w-full"
                                inputClass="w-full"
                            />
                            <p class="mt-1 text-xs text-[#4a6490]">
                                {{ quantityLocked ? 'One unit per scanned serial number.' : `Up to ${maxQuantity} available.` }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-[#00164d]">Date Acquired <span class="text-[#ce1126]">*</span></label>
                            <InputText v-model="review.date_acquired" type="date" :max="today" class="w-full" />
                            <p class="mt-1 text-xs" :class="review.date_acquired ? 'text-[#4a6490]' : 'font-medium text-[#ce1126]'">
                                {{ review.date_acquired ? 'Auto-filled from the acquisition record.' : 'Missing on record — enter it to continue.' }}
                            </p>
                        </div>
                        <template v-if="result.type === 'equipment'">
                            <div v-if="needsSerialChoice" class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-[#00164d]">Serial No. <span class="text-[#ce1126]">*</span></label>
                                <Select
                                    v-model="review.serial_id"
                                    :options="result.equipment.in_stock_serials"
                                    optionLabel="serial_number"
                                    optionValue="id"
                                    filter
                                    placeholder="Select the unit being issued"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#00164d]">Property No. <span class="text-[#ce1126]">*</span></label>
                                <InputText
                                    v-model="review.property_number"
                                    class="w-full"
                                    :readonly="propertyLocked"
                                    placeholder="Type the Property No. from the paper"
                                    @input="review.property_auto = false"
                                />
                                <p class="mt-1 text-xs" :class="usesDefaultProperty ? 'font-medium text-[#ce1126]' : 'text-[#4a6490]'">
                                    {{ propertyHint }}
                                </p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-[#00164d]">Inventory No.</label>
                                <InputText v-model="review.inventory_number" class="w-full" placeholder="Optional" :readonly="Boolean(selectedUnit?.inventory_number)" />
                                <p v-if="selectedUnit?.inventory_number" class="mt-1 text-xs text-[#4a6490]">From the unit's record (read-only).</p>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" :disabled="submitting">Review &amp; confirm</UiButton>
                        <UiButton type="button" variant="outline" :disabled="submitting" @click="clearAll">Clear</UiButton>
                    </div>
                </form>

                <div v-if="lastIssued" class="mt-6 rounded-md border border-[#a8b8d4] p-3 text-sm">
                    <p class="font-semibold text-[#00164d]">
                        Last issued: {{ lastIssued.issuance_number }} → {{ lastIssued.recipient_display_name }} ({{ lastIssued.unit_display_name }})
                    </p>
                    <div class="mt-3">
                        <OfficeFormsPanel :issuance="lastIssued" />
                    </div>
                </div>
            </UiCard>
        </div>

        <Dialog v-model:visible="confirmVisible" modal header="Confirm issuance" :style="{ width: '480px' }" :closable="!submitting">
            <div class="space-y-2 pt-2 text-sm text-[#00164d]">
                <p><span class="text-[#4a6490]">Issue:</span> <strong>{{ review.quantity }} × {{ title }}</strong></p>
                <p v-if="selectedSerialLabel"><span class="text-[#4a6490]">Serial No.:</span> {{ selectedSerialLabel }}</p>
                <p v-if="result?.type === 'equipment'"><span class="text-[#4a6490]">Property No.:</span> {{ review.property_number }}</p>
                <p><span class="text-[#4a6490]">Received by:</span> {{ recipient?.employee?.name }} ({{ recipient?.employee?.unit }})</p>
                <p><span class="text-[#4a6490]">Date acquired:</span> {{ review.date_acquired }}</p>
                <p><span class="text-[#4a6490]">Processed by:</span> {{ auth.user?.name }}</p>
            </div>
            <template #footer>
                <UiButton variant="outline" :disabled="submitting" @click="confirmVisible = false">Back</UiButton>
                <UiButton :loading="submitting" @click="confirmIssue">Confirm issuance</UiButton>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AutoComplete from 'primevue/autocomplete';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import UiCard from '../../components/ui/UiCard.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import StockOpScanner from '../../components/stock/StockOpScanner.vue';
import RecipientPicker from '../../components/RecipientPicker.vue';
import { useNotify } from '../../composables/useNotify';
import { useAuthStore } from '../../stores/auth';
import { newIdempotencyKey, todayIso } from '../../utils/idempotency';
import OfficeFormsPanel from '../../components/OfficeFormsPanel.vue';
import api from '../../services/api';

const notify = useNotify();
const auth = useAuthStore();
const today = todayIso();

const scanner = ref(null);
const recipientPicker = ref(null);
const code = ref('');
const result = ref(null);
const lookupError = ref('');
const recipient = ref(null);
const submitting = ref(false);
const confirmVisible = ref(false);
const lastIssued = ref(null);
const submissionKey = ref(newIdempotencyKey('scan'));
const review = reactive({ quantity: 1, date_acquired: '', property_number: '', inventory_number: '', serial_id: null, property_auto: false });

const manual = ref(null);
const manualOptions = ref([]);
const manualSuggestions = ref([]);

const title = computed(() => {
    if (!result.value) return '';
    return result.value.type === 'item' ? result.value.item?.item_name : result.value.equipment?.name;
});

const detailRows = computed(() => {
    const r = result.value;
    if (!r) return [];
    if (r.type === 'item') {
        const item = r.item;
        return [
            { label: 'Barcode / Item No.', value: item.barcode || item.item_number },
            { label: 'Inventory No.', value: item.inventory_number },
            { label: 'Category', value: item.category },
            { label: 'Location', value: item.location },
            { label: 'Available', value: `${item.current_stock} ${item.unit || ''}`.trim() },
            { label: 'Stock status', value: item.stock_status_label },
        ];
    }
    const equipment = r.equipment;
    return [
        { label: 'Serial No.', value: r.serial?.serial_number },
        { label: 'Brand / Model', value: equipment.brand_model },
        { label: 'Property No. (record)', value: equipment.property_number },
        { label: 'Inventory No.', value: equipment.inventory_number },
        { label: 'Category / Type', value: [equipment.category, equipment.type].filter(Boolean).join(' · ') },
        { label: 'Available in listing', value: equipment.quantity },
        { label: 'Origin', value: equipment.origin === 'returned' ? 'Returned stock' : 'New' },
        { label: 'Life span', value: equipment.life_span },
        { label: 'Unit status', value: r.unit_status_label },
    ];
});

const needsSerialChoice = computed(
    () => result.value?.type === 'equipment' && !result.value.serial && (result.value.equipment?.in_stock_serials?.length ?? 0) > 0,
);
const quantityLocked = computed(() => result.value?.type === 'equipment' && (Boolean(result.value.serial) || needsSerialChoice.value));
const maxQuantity = computed(() => {
    if (!result.value) return 1;
    return result.value.type === 'item' ? Math.max(1, result.value.item.current_stock) : Math.max(1, result.value.equipment.quantity);
});
// The unit being issued (scanned serial or chosen from the list).
const selectedUnit = computed(() => {
    const r = result.value;
    if (r?.type !== 'equipment') return null;
    if (r.serial) return r.serial;
    return r.equipment?.in_stock_serials?.find((serial) => serial.id === review.serial_id) ?? null;
});
// Mirrors the server rule (IssuanceController::resolveLinePropertyNumber).
const propertyRule = computed(() => {
    const equipment = result.value?.equipment;
    if (!equipment) return 'none';
    if (selectedUnit.value?.property_number) return 'unit';
    if (equipment.origin === 'returned') return 'returned';
    if (equipment.property_number_rule === 'record') return 'record';
    return 'typed';
});
const propertyLocked = computed(() => ['unit', 'record'].includes(propertyRule.value));
const usesDefaultProperty = computed(() => {
    const equipment = result.value?.equipment;
    if (!equipment || propertyRule.value !== 'typed') return false;
    return (review.property_number || '').trim().toLowerCase() === String(equipment.property_number || '').toLowerCase();
});
const propertyHint = computed(() => {
    switch (propertyRule.value) {
        case 'unit':
            return "From the unit's record (read-only). Correct it in Supply Master > Equipment if it is wrong.";
        case 'record':
            return 'Single-unit record: its official Property No. is used (read-only).';
        case 'returned':
            return 'Returned stock: keep the listed number unless the paper shows a different one.';
        default:
            if (needsSerialChoice.value && !review.serial_id) {
                return 'Select the serial number first; a number recorded on the unit fills in automatically.';
            }
            return usesDefaultProperty.value
                ? "That is the record's shared number for several units. Type the Property No. of this unit from the paper form."
                : 'Type the Property No. of this unit from the paper form.';
    }
});

// Refresh dependent numbers when the unit changes; clear values that were filled automatically
// and no longer apply.
watch([selectedUnit, propertyRule], () => {
    const equipment = result.value?.equipment;
    if (!equipment) return;
    if (propertyRule.value === 'unit') {
        review.property_number = selectedUnit.value.property_number;
        review.property_auto = true;
    } else if (propertyRule.value === 'record') {
        review.property_number = equipment.property_number;
        review.property_auto = true;
    } else if (review.property_auto) {
        review.property_number = propertyRule.value === 'returned' ? equipment.property_number : '';
        review.property_auto = propertyRule.value === 'returned';
    }
    review.inventory_number = selectedUnit.value?.inventory_number || equipment.inventory_number || '';
});
const selectedSerialLabel = computed(() => {
    if (result.value?.serial) return result.value.serial.serial_number;
    const chosen = result.value?.equipment?.in_stock_serials?.find((serial) => serial.id === review.serial_id);
    return chosen?.serial_number ?? '';
});

async function lookup(value, { force = false } = {}) {
    const term = String(value || '').trim();
    if (!term) return;

    // The scanner input re-emits its code on blur; ignore repeats of the code already under
    // review so moving to the recipient field does not reset the form.
    if (!force && result.value && result.value.code === term) return;

    lookupError.value = '';
    try {
        const { data } = await api.get('/scan-issue/lookup', { params: { code: term } });
        result.value = data;
        submissionKey.value = newIdempotencyKey('scan');
        Object.assign(review, {
            quantity: 1,
            serial_id: null,
            date_acquired: data.type === 'item' ? (data.item?.last_received_on || '') : (data.equipment?.date_acquired || ''),
            property_number: data.type === 'equipment' && data.equipment?.origin === 'returned' ? data.equipment.property_number : '',
            property_auto: data.type === 'equipment' && data.equipment?.origin === 'returned',
            inventory_number: data.type === 'equipment' ? (data.equipment?.inventory_number || '') : '',
        });
    } catch (error) {
        result.value = null;
        lookupError.value = error.response?.data?.message || 'Lookup failed.';
    }
}

function searchManual(event) {
    const term = String(event.query || '').trim().toLowerCase();
    manualSuggestions.value = term
        ? manualOptions.value.filter((option) => option.label.toLowerCase().includes(term)).slice(0, 50)
        : manualOptions.value.slice(0, 50);
}

function openConfirm() {
    if (!recipient.value?.employee) {
        notify.warn('Select the recipient from the employee directory.');
        return;
    }
    if (!review.quantity || review.quantity < 1 || review.quantity > maxQuantity.value) {
        notify.warn(`Quantity must be between 1 and ${maxQuantity.value}.`);
        return;
    }
    if (!review.date_acquired) {
        notify.warn('Date Acquired is required. Resolve it before issuing.');
        return;
    }
    if (result.value.type === 'equipment') {
        if (!review.property_number.trim()) {
            notify.warn('Enter the Property No. from the paper form.');
            return;
        }
        if (usesDefaultProperty.value) {
            notify.warn("This record holds several units: enter the unit's Property No. from the paper form, not the record's shared number.");
            return;
        }
        if (needsSerialChoice.value && !review.serial_id) {
            notify.warn('Select the serial number of the unit being issued.');
            return;
        }
    }
    confirmVisible.value = true;
}

function buildPayload() {
    const r = result.value;
    const line = r.type === 'item'
        ? { item_id: r.item.id, quantity: review.quantity, date_acquired: review.date_acquired }
        : {
              equipment_id: r.equipment.id,
              property_number: review.property_number.trim(),
              inventory_number: review.inventory_number.trim() || null,
              date_acquired: review.date_acquired,
              quantity: r.serial || needsSerialChoice.value ? 1 : review.quantity,
              serial_number_ids: r.serial ? [r.serial.id] : review.serial_id ? [review.serial_id] : [],
          };

    return {
        issuance_type: r.type === 'item' ? 'items' : 'equipments',
        channel: 'scan',
        idempotency_key: submissionKey.value,
        recipient_source: recipient.value.source,
        received_by: recipient.value.source === 'self' ? null : recipient.value.employee.id,
        items: [line],
    };
}

async function confirmIssue() {
    if (submitting.value) return;
    submitting.value = true;
    try {
        const { data } = await api.post('/issuances', buildPayload());
        lastIssued.value = data;
        confirmVisible.value = false;
        if (data.duplicate) {
            notify.info(data.message, 'Already processed');
        } else {
            notify.success(`Issued under ${data.issuance_number}.`, 'Issuance completed');
        }
        clearScan();
        await loadManualOptions();
    } catch (error) {
        // Keep the same key so a retry after a network failure cannot issue twice.
        notify.error(error.response?.data?.message || 'Unable to issue.', 'Issuance failed');
        if (error.response?.status === 422) {
            confirmVisible.value = false;
            await lookup(result.value?.code || code.value, { force: true });
        }
    } finally {
        submitting.value = false;
    }
}

function clearScan() {
    result.value = null;
    code.value = '';
    manual.value = null;
    submissionKey.value = newIdempotencyKey('scan');
    nextTick(() => scanner.value?.focus());
}

function clearAll() {
    clearScan();
    recipient.value = null;
    recipientPicker.value?.reset();
}

// ---- camera
let qr = null;
const cameraActive = ref(false);

async function toggleCamera() {
    if (cameraActive.value) {
        await stopCamera();
        return;
    }
    try {
        const { Html5Qrcode } = await import('html5-qrcode');
        cameraActive.value = true;
        await nextTick();
        qr = new Html5Qrcode('scan-issue-camera');
        await qr.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            async (decoded) => {
                await stopCamera();
                code.value = decoded;
                await lookup(decoded);
            },
            () => {},
        );
    } catch (error) {
        cameraActive.value = false;
        notify.error(error?.message || String(error) || 'Camera unavailable.', 'Camera');
    }
}

async function stopCamera() {
    try {
        if (qr?.isScanning) await qr.stop();
        qr?.clear();
    } catch {
        // ignore
    }
    qr = null;
    cameraActive.value = false;
}

async function loadManualOptions() {
    const [items, equipments] = await Promise.all([
        api.get('/items/list', { params: { all: 1 } }),
        api.get('/equipments/list'),
    ]);
    const itemRows = Array.isArray(items.data) ? items.data : items.data?.data ?? [];
    manualOptions.value = [
        ...itemRows.map((item) => ({
            code: item.item_number || item.barcode || item.inventory_number,
            label: `Supply · ${item.item_name} (${item.barcode || item.item_number}) · Stock ${item.current_stock}`,
        })),
        ...(equipments.data ?? []).map((equipment) => ({
            code: equipment.property_number || equipment.inventory_number || equipment.barcode,
            label: `Equipment · ${equipment.name}${equipment.brand_model ? ` · ${equipment.brand_model}` : ''} (${equipment.property_number}) · Available ${equipment.quantity}`,
        })),
    ].filter((option) => option.code);
}

onMounted(async () => {
    try {
        await loadManualOptions();
    } catch (error) {
        notify.error(error.response?.data?.message || 'Unable to load lookup options.');
    }
});

onBeforeUnmount(stopCamera);
</script>
