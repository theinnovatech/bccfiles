<template>
    <div v-if="!rows.length" class="report-empty">
        <svg class="h-12 w-12 text-[#a8b8d4]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="mt-3 text-sm font-medium text-[#00164d]">No data yet</p>
        <p class="mt-1 text-xs text-[#4a6490]">There are no records available for this report at the moment.</p>
    </div>

    <div v-else class="obims-table-wrap">
        <DataTable
            :value="rows"
            paginator
            :rows="10"
            stripedRows
            class="report-preview-table rounded-md border border-[#a8b8d4]"
        >
        <template v-if="type === 'inventory'">
            <Column field="barcode" header="Barcode" />
            <Column field="item_name" header="Item" />
            <Column header="Category"><template #body="{ data }">{{ data.category?.name }}</template></Column>
            <Column field="current_stock" header="Current Stock" />
            <Column field="minimum_stock" header="Minimum Stock" />
            <Column header="Location"><template #body="{ data }">{{ data.location?.name }}</template></Column>
        </template>

        <template v-else-if="type === 'equipment-inventory'">
            <Column header="Barcode"><template #body="{ data }">{{ data.barcode || '—' }}</template></Column>
            <Column field="property_number" header="Property No." />
            <Column field="name" header="Equipment" />
            <Column header="Category"><template #body="{ data }">{{ data.category?.name || '—' }}</template></Column>
            <Column field="type" header="Type" />
            <Column field="quantity" header="Available Qty" />
            <Column header="Life Span">
                <template #body="{ data }">
                    {{ data.life_span_years ? `${data.life_span_years} yr${data.life_span_years === 1 ? '' : 's'}` : '—' }}
                </template>
            </Column>
            <Column header="Description"><template #body="{ data }">{{ data.description || '—' }}</template></Column>
        </template>

        <template v-else-if="type === 'stock-card'">
            <Column header="Date"><template #body="{ data }">{{ formatDateTime(data.created_at) }}</template></Column>
            <Column header="Type"><template #body="{ data }">{{ formatType(data.transaction_type) }}</template></Column>
            <Column field="quantity" header="Qty" />
            <Column field="previous_stock" header="Previous" />
            <Column field="new_stock" header="Balance" />
            <Column field="reference_number" header="Reference" />
            <Column header="Dept/Office"><template #body="{ data }">{{ data.department_office || '—' }}</template></Column>
            <Column header="Issued By"><template #body="{ data }">{{ data.performer?.name || '—' }}</template></Column>
            <Column field="remarks" header="Remarks" />
        </template>

        <template v-else-if="type === 'property-card'">
            <Column header="Date"><template #body="{ data }">{{ formatDateTime(data.movement_date) }}</template></Column>
            <Column field="reference_number" header="Reference / PAR No." />
            <Column header="Receipt Qty."><template #body="{ data }">{{ data.receipt_qty ?? '—' }}</template></Column>
            <Column header="Issue Qty."><template #body="{ data }">{{ data.issue_qty ?? '—' }}</template></Column>
            <Column field="office_officer" header="Office/Officer" />
            <Column field="balance_qty" header="Balance Qty." />
            <Column field="amount" header="Amount" />
            <Column field="remarks" header="Remarks" />
        </template>

        <template v-else-if="type === 'stock-movements'">
            <Column header="Date"><template #body="{ data }">{{ formatDateTime(data.created_at) }}</template></Column>
            <Column header="Barcode"><template #body="{ data }">{{ data.item?.barcode }}</template></Column>
            <Column header="Item"><template #body="{ data }">{{ data.item?.item_name }}</template></Column>
            <Column header="Type"><template #body="{ data }">{{ formatType(data.transaction_type) }}</template></Column>
            <Column field="quantity" header="Qty" />
            <Column field="new_stock" header="Balance" />
        </template>

        <template v-else-if="type === 'issuance'">
            <Column field="issuance_number" header="Issuance No." />
            <Column header="Department"><template #body="{ data }">{{ data.department }}</template></Column>
            <Column field="line_type" header="Type" />
            <Column header="Item / Equipment"><template #body="{ data }">{{ data.item_name }}</template></Column>
            <Column field="identifier" header="Barcode / Property No." />
            <Column field="quantity" header="Qty" />
            <Column header="Date"><template #body="{ data }">{{ formatDate(data.issued_date) }}</template></Column>
        </template>

        <template v-else-if="type === 'returns'">
            <Column header="Date"><template #body="{ data }">{{ formatDate(data.date_returned) }}</template></Column>
            <Column header="Equipment"><template #body="{ data }">{{ data.equipment?.name || '—' }}</template></Column>
            <Column header="Department"><template #body="{ data }">{{ data.department?.name || '—' }}</template></Column>
            <Column field="quantity" header="Qty" />
            <Column header="Returned By"><template #body="{ data }">{{ data.borrower?.name || data.borrower_name || '—' }}</template></Column>
            <Column field="reason" header="Remarks" />
        </template>

        <template v-else-if="type === 'low-stock'">
            <Column field="barcode" header="Barcode" />
            <Column field="item_name" header="Item" />
            <Column field="current_stock" header="Current Stock" />
            <Column field="minimum_stock" header="Minimum Stock" />
            <Column header="Location"><template #body="{ data }">{{ data.location?.name }}</template></Column>
        </template>

        <template v-else-if="type === 'monthly-consumption'">
            <Column field="department" header="Department" />
            <Column field="total" header="Total Issued" />
        </template>
    </DataTable>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const props = defineProps({
    type: { type: String, required: true },
    data: { type: Array, default: () => [] },
});

const rows = computed(() => {
    if (!props.data?.length) {
        return [];
    }

    if (props.type === 'issuance') {
        return props.data.flatMap((issuance) => {
            const department = issuance.department?.name
                || issuance.receiver?.department?.name
                || '-';
            const details = issuance.details || [];

            if (!details.length) {
                return [{
                    issuance_number: issuance.issuance_number,
                    department,
                    line_type: '-',
                    item_name: '-',
                    identifier: '-',
                    quantity: '-',
                    issued_date: issuance.issued_date,
                }];
            }

            return details.map((detail) => ({
                issuance_number: issuance.issuance_number,
                department,
                line_type: detail.equipment_id || detail.equipment ? 'Equipment' : 'Item',
                item_name: detail.equipment?.name || detail.item?.item_name || '-',
                identifier: detail.equipment?.property_number || detail.barcode || detail.item?.barcode || '-',
                quantity: detail.quantity,
                issued_date: issuance.issued_date,
            }));
        });
    }

    return props.data;
});

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '-';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '-';
}

function formatType(value) {
    if (!value) return '-';
    return typeof value === 'string' ? value : value.value || value;
}
</script>

<style scoped>
.report-empty {
    display: flex;
    min-height: 240px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    border: 1px dashed #a8b8d4;
    background: #e8edf5;
    padding: 2rem;
    text-align: center;
}
</style>
