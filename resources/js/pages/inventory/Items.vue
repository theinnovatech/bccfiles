<template>
    <div class="space-y-6">
        <UiCard>
            <template #header>
                <div>
                    <h3 class="shadcn-card-title">Registered Items</h3>
                    <p class="shadcn-card-description">
                        Commonly used supplies.
                    </p>
                </div>
            </template>

            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="filteredItems.length"
                @reset="resetFilters"
            />

            <div class="obims-table-wrap">
                <DataTable
                    :value="filteredItems"
                    :loading="loading"
                    paginator
                    :rows="10"
                    class="rounded-md border border-[#a8b8d4]"
                >
                    <Column field="barcode" header="Barcode">
                        <template #body="{ data }">{{
                            data.barcode || "—"
                        }}</template>
                    </Column>
                    <Column field="inventory_number" header="Inventory No.">
                        <template #body="{ data }">{{
                            data.inventory_number || "—"
                        }}</template>
                    </Column>
                    <Column field="item_number" header="Stock No." />
                    <Column field="item_name" header="Item Name" />
                    <Column field="brand" header="Brand" />
                    <Column header="Category">
                        <template #body="{ data }">{{
                            data.category?.name
                        }}</template>
                    </Column>
                    <Column header="Unit">
                        <template #body="{ data }">{{
                            data.unit?.abbreviation
                        }}</template>
                    </Column>
                    <Column field="current_stock" header="Remaining" />
                    <Column header="Reference">
                        <template #body="{ data }">
                            <span v-if="!data.reference_stock_missing">{{ formatQty(data.reference_stock) }}</span>
                            <button
                                v-else
                                type="button"
                                class="text-xs font-semibold text-[#ce1126] underline-offset-2 hover:underline"
                                title="Reference stock is not configured. Status uses 2 × minimum stock until it is set."
                                @click="openEdit(data)"
                            >
                                Not set
                            </button>
                        </template>
                    </Column>
                    <Column header="Low-stock %">
                        <template #body="{ data }">{{
                            data.effective_low_stock_percent != null
                                ? `${formatQty(data.effective_low_stock_percent)}%`
                                : "—"
                        }}</template>
                    </Column>
                    <Column header="Threshold">
                        <template #body="{ data }">{{
                            data.reference_stock_missing
                                ? `Min. ${data.minimum_stock}`
                                : formatQty(data.low_stock_threshold)
                        }}</template>
                    </Column>
                    <Column header="Location">
                        <template #body="{ data }">{{
                            data.location?.name
                        }}</template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <UiBadge :variant="stockBadge(data).variant">{{
                                stockBadge(data).label
                            }}</UiBadge>
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="item-actions">
                                <UiButton
                                    variant="ghost"
                                    size="icon"
                                    title="Edit item"
                                    @click="openEdit(data)"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"
                                        />
                                    </svg>
                                </UiButton>
                                <UiButton
                                    variant="ghost"
                                    size="icon"
                                    title="Delete item"
                                    @click="confirmRemove(data)"
                                >
                                    <svg
                                        class="h-4 w-4 text-[#ce1126]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                        />
                                    </svg>
                                </UiButton>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>

        <EquipmentsTable v-if="canViewEquipments" />

        <Dialog
            v-model:visible="dialogVisible"
            modal
            header="Edit Item"
            :style="{ width: '640px' }"
        >
            <form class="grid gap-4 pt-2 md:grid-cols-2" @submit.prevent="saveItem">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Inventory No.</label
                    >
                    <InputText
                        :model-value="form.inventory_number || '—'"
                        class="w-full"
                        readonly
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Stock No.</label
                    >
                    <InputText
                        v-model="form.item_number"
                        class="w-full"
                        :readonly="!(auth.isAdmin || auth.isSupplyOfficer)"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Stock Card number. Saving it unchanged never reports a
                        duplicate; leading zeros are kept.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Barcode</label
                    >
                    <InputText
                        v-model="form.barcode"
                        class="w-full"
                        placeholder="Optional"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Item Name <span class="text-[#ce1126]">*</span></label
                    >
                    <InputText v-model="form.item_name" class="w-full" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Brand</label
                    >
                    <InputText v-model="form.brand" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Status <span class="text-[#ce1126]">*</span></label
                    >
                    <Select
                        v-model="form.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Category <span class="text-[#ce1126]">*</span></label
                    >
                    <Select
                        v-model="form.category_id"
                        :options="categories"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Unit <span class="text-[#ce1126]">*</span></label
                    >
                    <Select
                        v-model="form.unit_id"
                        :options="units"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Storage Location
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <Select
                        v-model="form.location_id"
                        :options="locations"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Minimum Stock
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputNumber
                        v-model="form.minimum_stock"
                        class="w-full"
                        :min="1"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">Must be at least 1.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Reference (Target) Stock
                        <span class="text-[#ce1126]">*</span></label
                    >
                    <InputNumber
                        v-model="form.reference_stock"
                        class="w-full"
                        :min="1"
                        placeholder="e.g. 20"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        {{ thresholdExplanation(form.reference_stock, form.low_stock_percent) }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Low-stock percentage</label
                    >
                    <InputNumber
                        v-model="form.low_stock_percent"
                        class="w-full"
                        :min="1"
                        :max="100"
                        :maxFractionDigits="2"
                        suffix="%"
                        :placeholder="`Default: ${stockRuleState.defaultPercent}%`"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Between 1 and 100. Leave blank to use the default
                        ({{ stockRuleState.defaultPercent }}%, Settings ›
                        Inventory).
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Unit cost</label
                    >
                    <InputNumber
                        v-model="form.unit_cost"
                        class="w-full"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        :min="0"
                        placeholder="Optional"
                    />
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Printed on the Local RIV. Leave blank if unknown.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Description</label
                    >
                    <Textarea v-model="form.description" class="w-full" rows="3" />
                </div>
            </form>
            <template #footer>
                <UiButton variant="outline" @click="dialogVisible = false"
                    >Cancel</UiButton
                >
                <UiButton :loading="saving" @click="saveItem"
                    >Save Changes</UiButton
                >
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Select from "primevue/select";
import Textarea from "primevue/textarea";
import UiCard from "../../components/ui/UiCard.vue";
import UiBadge from "../../components/ui/UiBadge.vue";
import UiButton from "../../components/ui/UiButton.vue";
import TableFilters from "../../components/TableFilters.vue";
import EquipmentsTable from "../../components/stock/EquipmentsTable.vue";
import { confirmDelete } from "../../composables/confirm";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import { useAuthStore } from "../../stores/auth";
import api from "../../services/api";
import {
    formatQty,
    loadStockRule,
    stockRule,
    thresholdExplanation,
} from "../../utils/stockThreshold";

const stockRuleState = stockRule();
loadStockRule();

const notify = useNotify();
const auth = useAuthStore();
const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const categories = ref([]);
const units = ref([]);
const locations = ref([]);
const canViewEquipments = computed(() => auth.isAdmin || auth.isSupplyOfficer);

const statusOptions = [
    { label: "Active", value: "active" },
    { label: "Inactive", value: "inactive" },
    { label: "Discontinued", value: "discontinued" },
];

const form = reactive({
    inventory_number: "",
    item_number: "",
    barcode: "",
    item_name: "",
    brand: "",
    description: "",
    category_id: null,
    unit_id: null,
    location_id: null,
    minimum_stock: 1,
    reference_stock: null,
    low_stock_percent: null,
    unit_cost: null,
    status: "active",
});

const categoryOptions = computed(() => {
    const names = [
        ...new Set(
            items.value.map((item) => item.category?.name).filter(Boolean),
        ),
    ].sort();
    return [
        { label: "All categories", value: "" },
        ...names.map((name) => ({ label: name, value: name })),
    ];
});

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Barcode, inventory no., item no., name, brand...",
        fields: [
            "barcode",
            "inventory_number",
            "item_number",
            "item_name",
            "brand",
            "category.name",
            "location.name",
        ],
    },
    {
        key: "category",
        type: "select",
        label: "Category",
        field: "category.name",
        match: (row) => row.category?.name,
        options: categoryOptions.value,
    },
    {
        key: "stockStatus",
        type: "custom",
        label: "Stock Status",
        options: [
            { label: "All stock levels", value: "" },
            { label: "In stock", value: "in_stock" },
            { label: "Low stock", value: "low_stock" },
            { label: "Out of stock", value: "out_of_stock" },
            { label: "Reference stock not set", value: "reference_missing" },
        ],
        // Status is computed on the server with the shared 50% rule.
        predicate: (row, value) => {
            if (value === "reference_missing") return row.reference_stock_missing;
            return row.stock_status === value;
        },
    },
]);

const { filters, filteredItems, hasActiveFilters, resetFilters } =
    useTableFilters(items, filterConfig);

function stockBadge(item) {
    const variants = {
        out_of_stock: "destructive",
        low_stock: "gold",
        in_stock: "default",
    };

    return {
        label: item.stock_status_label || "—",
        variant: variants[item.stock_status] || "outline",
    };
}

function openEdit(item) {
    editingId.value = item.id;
    form.inventory_number = item.inventory_number || "";
    form.item_number = item.item_number || "";
    form.barcode = item.barcode || "";
    form.item_name = item.item_name || "";
    form.brand = item.brand || "";
    form.description = item.description || "";
    form.category_id = item.category_id;
    form.unit_id = item.unit_id;
    form.location_id = item.location_id;
    form.minimum_stock = Math.max(1, item.minimum_stock ?? 1);
    form.reference_stock = item.reference_stock ?? null;
    form.low_stock_percent = item.low_stock_percent ?? null;
    form.unit_cost = item.unit_cost != null ? Number(item.unit_cost) : null;
    form.status = item.status || "active";
    dialogVisible.value = true;
}

async function saveItem() {
    if (!editingId.value) {
        return;
    }

    if (
        !form.item_name?.trim() ||
        !form.category_id ||
        !form.unit_id ||
        !form.location_id
    ) {
        notify.warn("Please fill in all required fields.");
        return;
    }

    if (!form.minimum_stock || form.minimum_stock < 1) {
        notify.warn("Minimum stock must be at least 1.");
        return;
    }

    if (!form.reference_stock || form.reference_stock < 1) {
        notify.warn("Enter the reference (target) stock. It must be at least 1.");
        return;
    }

    if (
        form.low_stock_percent !== null &&
        form.low_stock_percent !== undefined &&
        (form.low_stock_percent < 1 || form.low_stock_percent > 100)
    ) {
        notify.warn("The low-stock percentage must be between 1 and 100.");
        return;
    }

    if (!form.item_number?.trim()) {
        notify.warn("The Stock No. cannot be blank.");
        return;
    }

    saving.value = true;
    try {
        await api.put(`/items/${editingId.value}`, {
            item_number: form.item_number.trim(),
            low_stock_percent: form.low_stock_percent ?? null,
            barcode: form.barcode || null,
            item_name: form.item_name,
            brand: form.brand || null,
            description: form.description || null,
            category_id: form.category_id,
            unit_id: form.unit_id,
            location_id: form.location_id,
            minimum_stock: form.minimum_stock,
            reference_stock: form.reference_stock,
            unit_cost: form.unit_cost ?? null,
            status: form.status || "active",
        });
        notify.success("Item updated successfully.");
        dialogVisible.value = false;
        await load();
    } catch (error) {
        const errors = error.response?.data?.errors;
        const first = errors ? Object.values(errors)[0] : null;
        notify.error((Array.isArray(first) ? first[0] : first) || error.response?.data?.message || "Unable to update item.");
    } finally {
        saving.value = false;
    }
}

function confirmRemove(item) {
    confirmDelete({
        title: "Delete item?",
        message: `Remove "${item.item_name}" from the system?`,
        detail: "The item will be moved to Deleted Data and can be restored later.",
        onAccept: async () => {
            try {
                await api.delete(`/items/${item.id}`);
                notify.success("Item deleted.");
                await load();
            } catch (error) {
                notify.error(
                    error.response?.data?.message || "Unable to delete item.",
                );
                throw error;
            }
        },
    });
}

async function loadLookups() {
    try {
        const [cat, uni, loc] = await Promise.all([
            api.get("/categories/list"),
            api.get("/units/list"),
            api.get("/locations/list"),
        ]);
        categories.value = cat.data;
        units.value = uni.data;
        locations.value = loc.data;
    } catch {
        notify.error("Unable to load item form options.");
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get("/items/list", { params: { all: 1 } });
        items.value = data.data ?? data;

        const stockStatus = new URLSearchParams(window.location.search).get(
            "stockStatus",
        );
        if (stockStatus) {
            filters.value.stockStatus = stockStatus;
        }
    } catch (error) {
        notify.error(error.response?.data?.message || "Unable to load items.");
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await Promise.all([load(), loadLookups()]);
});
</script>

<style scoped>
.item-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
</style>
