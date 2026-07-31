<template>
    <div :class="embedded ? 'space-y-6' : 'mx-auto max-w-3xl space-y-6'">
        <div v-if="!embedded" class="shadcn-card p-6">
            <h3 class="shadcn-card-title mb-4">Register Item</h3>
            <BarcodeScannerInput
                v-model="barcode"
                @scan="lookupBarcode"
                @clear="onBarcodeClear"
            />
            <div class="mt-3">
                <UiButton variant="outline" @click="proceedWithoutBarcode">
                    Item with no barcode
                </UiButton>
            </div>
        </div>

        <template v-else>
            <StockOpScanner
                v-model="barcode"
                title="Scan to register"
                hint="Scan or type a barcode to register an item. If the barcode is new, complete the form below."
                @scan="lookupBarcode"
                @clear="onBarcodeClear"
            />

            <div>
                <UiButton variant="outline" @click="proceedWithoutBarcode">
                    Item with no barcode
                </UiButton>
            </div>
        </template>

        <div v-if="existingItem" class="stock-op-alert stock-op-alert-warn">
            <div class="stock-op-alert-icon" aria-hidden="true">
                <svg
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <div>
                <p class="stock-op-alert-title">Item already registered</p>
                <p class="stock-op-alert-text">
                    {{ existingItem.item_name }}
                    <template v-if="existingItem.inventory_number">
                        · {{ existingItem.inventory_number }}
                    </template>
                    <template v-else-if="existingItem.item_number">
                        · {{ existingItem.item_number }}
                    </template>
                    · Current stock: {{ existingItem.current_stock }}
                </p>
            </div>
        </div>

        <div v-if="showForm" class="stock-op-form-panel">
            <div class="stock-op-form-header">
                <h4 class="stock-op-form-title">New item details</h4>
                <p class="stock-op-form-desc">
                    Complete the form to add this item to inventory.
                </p>
            </div>

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="save">
                <div class="md:col-span-2">
                    <p
                        class="rounded-md border border-[#a8b8d4] bg-[#e2e8f2] px-3 py-2 text-xs text-[#4a6490]"
                    >
                        Inventory number and item number will be generated
                        automatically when you save this item.
                    </p>
                </div>

                <div v-if="form.barcode">
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Barcode</label
                    >
                    <InputText v-model="form.barcode" class="w-full" readonly />
                </div>
                <div v-else-if="noBarcodeMode" class="md:col-span-2">
                    <p
                        class="border border-dashed border-[#a8b8d4] bg-transparent px-3 py-2 text-xs text-[#4a6490]"
                    >
                        No barcode assigned. The generated item number will be
                        used to identify this item.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Item Name</label
                    >
                    <InputText
                        v-model="form.item_name"
                        class="w-full"
                        required
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Brand</label
                    >
                    <InputText v-model="form.brand" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#00164d]"
                        >Category</label
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
                        >Unit</label
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
                        >Storage Location</label
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
                        >Minimum Stock</label
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
                    <Textarea
                        v-model="form.description"
                        class="w-full"
                        rows="3"
                    />
                </div>
                <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row">
                    <UiButton type="submit" :loading="saving"
                        >Save Item</UiButton
                    >
                    <UiButton type="button" variant="outline" @click="resetForm"
                        >Clear form</UiButton
                    >
                </div>
            </form>
        </div>

        <div v-else-if="embedded && !existingItem" class="stock-op-empty">
            <svg
                class="h-10 w-10 text-[#a8b8d4]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#00164d]">
                No barcode scanned yet
            </p>
            <p class="mt-1 text-xs text-[#4a6490]">
                Scan a barcode above or continue without one.
            </p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import UiButton from "../../components/ui/UiButton.vue";
import BarcodeScannerInput from "../../components/BarcodeScannerInput.vue";
import StockOpScanner from "../../components/stock/StockOpScanner.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";

defineProps({
    embedded: { type: Boolean, default: false },
});

const notify = useNotify();
const barcode = ref("");
const existingItem = ref(null);
const showForm = ref(false);
const noBarcodeMode = ref(false);
const saving = ref(false);
const categories = ref([]);
const units = ref([]);
const locations = ref([]);
const form = reactive({
    barcode: "",
    item_name: "",
    brand: "",
    category_id: null,
    unit_id: null,
    location_id: null,
    minimum_stock: 0,
    description: "",
});

function resetForm() {
    showForm.value = false;
    noBarcodeMode.value = false;
    existingItem.value = null;
    barcode.value = "";
    form.barcode = "";
    form.item_name = "";
    form.brand = "";
    form.category_id = null;
    form.unit_id = null;
    form.location_id = null;
    form.minimum_stock = 0;
    form.description = "";
}

function onBarcodeClear() {
    showForm.value = false;
    noBarcodeMode.value = false;
    existingItem.value = null;
    form.barcode = "";
}

function proceedWithoutBarcode() {
    existingItem.value = null;
    noBarcodeMode.value = true;
    form.barcode = "";
    showForm.value = true;
}

watch(barcode, (next) => {
    if (!next.trim()) {
        onBarcodeClear();
    }
});

async function lookupBarcode(code) {
    existingItem.value = null;
    showForm.value = false;
    noBarcodeMode.value = false;

    try {
        const { data } = await api.get(
            `/items/barcode/${encodeURIComponent(code)}`,
        );
        if (data.item) {
            existingItem.value = data.item;
            return;
        }
        form.barcode = code;
        showForm.value = true;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to look up barcode.",
        );
    }
}

async function save() {
    if (!form.item_name?.trim()) {
        notify.warn("Please enter the item name.");
        return;
    }

    if (!form.category_id || !form.unit_id || !form.location_id) {
        notify.warn("Please select category, unit, and storage location.");
        return;
    }

    saving.value = true;
    try {
        const { data } = await api.post("/items", {
            barcode: form.barcode || null,
            item_name: form.item_name,
            brand: form.brand || null,
            category_id: form.category_id,
            unit_id: form.unit_id,
            location_id: form.location_id,
            minimum_stock: form.minimum_stock ?? 0,
            description: form.description || null,
        });
        notify.success(
            `Item registered with inventory no. ${data.inventory_number}.`,
            "Item registered",
        );
        resetForm();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to register item.",
            "Registration failed",
        );
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    const [cat, uni, loc] = await Promise.all([
        api.get("/categories/list"),
        api.get("/units/list"),
        api.get("/locations/list"),
    ]);
    categories.value = cat.data;
    units.value = uni.data;
    locations.value = loc.data;
});
</script>
