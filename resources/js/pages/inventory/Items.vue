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
                    class="rounded-md border border-[#c8d6ef]"
                >
                    <Column field="barcode" header="Barcode" />
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
                </DataTable>
            </div>
        </UiCard>

        <EquipmentsTable v-if="canViewEquipments" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import UiCard from "../../components/ui/UiCard.vue";
import UiBadge from "../../components/ui/UiBadge.vue";
import TableFilters from "../../components/TableFilters.vue";
import EquipmentsTable from "../../components/stock/EquipmentsTable.vue";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import { useAuthStore } from "../../stores/auth";
import api from "../../services/api";

const notify = useNotify();
const auth = useAuthStore();
const items = ref([]);
const loading = ref(false);
const canViewEquipments = computed(() => auth.isAdmin || auth.isSupplyOfficer);

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
        placeholder: "Barcode, item name, brand...",
        fields: [
            "barcode",
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

onMounted(load);
</script>
