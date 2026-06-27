<template>
    <div class="mx-auto max-w-4xl min-w-0">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#002a7a]">
                            Create Supply Request
                        </h3>
                        <p class="mt-1 text-sm text-[#5b7fbf]">
                            Submit a request for supplies or equipment from your
                            department.
                        </p>
                    </div>
                    <a href="/requests" class="request-back-link">
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
                                d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
                            />
                        </svg>
                        Back to Requests
                    </a>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="tab in requestTypeTabs"
                        :key="tab.key"
                        type="button"
                        class="stock-op-tab-card"
                        :class="{
                            'stock-op-tab-card-active':
                                form.request_type === tab.key,
                        }"
                        @click="setRequestType(tab.key)"
                    >
                        <span class="stock-op-tab-icon" aria-hidden="true">
                            <component :is="tab.icon" />
                        </span>
                        <span class="stock-op-tab-label">{{ tab.label }}</span>
                        <span class="stock-op-tab-desc">{{
                            tab.description
                        }}</span>
                    </button>
                </div>
            </div>

            <form
                class="request-form-content min-w-0 space-y-5 p-4 sm:p-6"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Department</label
                        >
                        <Select
                            v-model="form.department_id"
                            :options="departments"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select department"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Request Type</label
                        >
                        <div
                            class="flex h-[42px] items-center rounded-md border border-[#c8d6ef] bg-[#eef2fa] px-3 text-sm font-medium text-[#0038a8]"
                        >
                            {{
                                form.request_type === "items"
                                    ? "Items / Supplies"
                                    : "Equipments"
                            }}
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#002a7a]"
                        >Remarks</label
                    >
                    <Textarea
                        v-model="form.remarks"
                        class="w-full"
                        rows="2"
                        placeholder="Optional notes for the supply officer"
                    />
                </div>

                <div
                    class="request-lines-panel rounded-xl border border-[#c8d6ef] bg-[#f8fafc] p-4"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h4 class="font-semibold text-[#002a7a]">
                                {{
                                    form.request_type === "items"
                                        ? "Requested Items"
                                        : "Requested Equipments"
                                }}
                            </h4>
                            <p class="mt-0.5 text-sm text-[#5b7fbf]">
                                Add one or more
                                {{
                                    form.request_type === "items"
                                        ? "items"
                                        : "equipments"
                                }}
                                to include in this request.
                            </p>
                        </div>
                        <UiButton
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addLine"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Line
                        </UiButton>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(line, index) in form.items"
                            :key="index"
                            class="request-line-card grid gap-3 rounded-lg border border-[#eef2fa] bg-white p-3 md:grid-cols-[minmax(0,1fr)_160px_auto] md:items-center"
                        >
                            <Select
                                v-if="form.request_type === 'items'"
                                v-model="line.item_id"
                                :options="items"
                                optionLabel="item_name"
                                optionValue="id"
                                placeholder="Select item"
                                class="min-w-0 w-full"
                                filter
                            />
                            <Select
                                v-else
                                v-model="line.equipment_id"
                                :options="equipments"
                                optionLabel="label"
                                optionValue="id"
                                placeholder="Select equipment"
                                class="min-w-0 w-full"
                                filter
                            />
                            <InputNumber
                                v-model="line.quantity_requested"
                                :min="1"
                                placeholder="Qty"
                                class="min-w-0 w-full"
                                inputClass="w-full"
                            />
                            <UiButton
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-[#ce1126] hover:bg-[#fff1f2] hover:text-[#ce1126]"
                                :disabled="form.items.length === 1"
                                @click="removeLine(index)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                    />
                                </svg>
                            </UiButton>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <UiButton type="submit" :loading="loading">
                        Submit Request
                    </UiButton>
                    <UiButton
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="goBack"
                    >
                        Cancel
                    </UiButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { h, onMounted, reactive, ref } from "vue";
import Select from "primevue/select";
import Textarea from "primevue/textarea";
import InputNumber from "primevue/inputnumber";
import UiButton from "../../components/ui/UiButton.vue";
import { useNotify } from "../../composables/useNotify";
import api from "../../services/api";
import { useAuthStore } from "../../stores/auth";

const IconItems = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
                }),
            ],
        );
    },
};

const IconEquipments = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75h6M9 3.75v1.5m6-1.5v1.5m-9 4.5h10.5",
                }),
            ],
        );
    },
};

const requestTypeTabs = [
    {
        key: "items",
        label: "Items",
        description: "Commonly used supplies and consumables",
        icon: IconItems,
    },
    {
        key: "equipments",
        label: "Equipments",
        description: "Property-tagged equipment and assets",
        icon: IconEquipments,
    },
];

const auth = useAuthStore();
const notify = useNotify();
const loading = ref(false);
const departments = ref([]);
const items = ref([]);
const equipments = ref([]);
const form = reactive({
    department_id: auth.user?.department_id ?? null,
    request_type: "items",
    remarks: "",
    items: [{ item_id: null, equipment_id: null, quantity_requested: 1 }],
});

function emptyLine() {
    return { item_id: null, equipment_id: null, quantity_requested: 1 };
}

function setRequestType(type) {
    if (form.request_type === type) {
        return;
    }

    form.request_type = type;
    form.items = [emptyLine()];
}

function addLine() {
    form.items.push(emptyLine());
}

function removeLine(index) {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
}

function goBack() {
    window.location.href = "/requests";
}

function buildPayload() {
    return {
        department_id: form.department_id,
        request_type: form.request_type,
        remarks: form.remarks,
        items: form.items.map((line) => {
            if (form.request_type === "items") {
                return {
                    item_id: line.item_id,
                    quantity_requested: line.quantity_requested,
                };
            }

            return {
                equipment_id: line.equipment_id,
                quantity_requested: line.quantity_requested,
            };
        }),
    };
}

async function submit() {
    loading.value = true;
    try {
        const { data } = await api.post("/requests", buildPayload());
        notify.success(
            "Your supply request was submitted successfully.",
            "Request submitted",
        );
        setTimeout(() => {
            window.location.href = `/requests/${data.id}`;
        }, 600);
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to submit supply request.",
            "Submit failed",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    const [deptRes, itemRes, equipmentRes] = await Promise.all([
        api.get("/departments/list"),
        api.get("/items/list", { params: { per_page: 100 } }),
        api.get("/equipments/list"),
    ]);

    departments.value = deptRes.data;
    items.value = itemRes.data.data ?? itemRes.data;
    equipments.value = (equipmentRes.data ?? []).map((equipment) => ({
        ...equipment,
        label: equipment.property_number
            ? `${equipment.name} (${equipment.property_number})`
            : equipment.name,
    }));
});
</script>

<style scoped>
.request-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid #c8d6ef;
    background: white;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #0038a8;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.request-back-link:hover {
    border-color: #0038a8;
    background: #eef2fa;
}

.request-line-card {
    box-shadow: 0 1px 2px rgb(0 42 122 / 0.04);
}
</style>
