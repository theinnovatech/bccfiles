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
                    <Column field="item_number" header="Item No." />
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
                    <Column field="current_stock" header="Stock" />
                    <Column field="minimum_stock" header="Min Stock" />
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
                        >Item No.</label
                    >
                    <InputText
                        :model-value="form.item_number || '—'"
                        class="w-full"
                        readonly
                    />
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
                        :min="0"
                    />
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
    minimum_stock: 0,
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
        ],
        predicate: (row, value) => {
            if (value === "out_of_stock") return row.current_stock <= 0;
            if (value === "low_stock")
                return (
                    row.current_stock > 0 &&
                    row.current_stock <= row.minimum_stock
                );
            if (value === "in_stock")
                return row.current_stock > row.minimum_stock;
            return true;
        },
    },
]);

const { filters, filteredItems, hasActiveFilters, resetFilters } =
    useTableFilters(items, filterConfig);

function stockBadge(item) {
    if (item.current_stock <= 0) {
        return { label: "Out of stock", variant: "destructive" };
    }

    if (item.current_stock <= item.minimum_stock) {
        return { label: "Low stock", variant: "gold" };
    }

    return { label: "In stock", variant: "default" };
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
    form.minimum_stock = item.minimum_stock ?? 0;
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

    saving.value = true;
    try {
        await api.put(`/items/${editingId.value}`, {
            barcode: form.barcode || null,
            item_name: form.item_name,
            brand: form.brand || null,
            description: form.description || null,
            category_id: form.category_id,
            unit_id: form.unit_id,
            location_id: form.location_id,
            minimum_stock: form.minimum_stock ?? 0,
            status: form.status || "active",
        });
        notify.success("Item updated successfully.");
        dialogVisible.value = false;
        await load();
    } catch (error) {
        notify.error(error.response?.data?.message || "Unable to update item.");
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
        const { data } = await api.get("/items/list");
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
