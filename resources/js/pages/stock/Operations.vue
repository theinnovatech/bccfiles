<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#00164d]">
                            Stock Operations
                        </h3>
                        <p class="mt-1 text-sm text-[#4a6490]">
                            Receive stock or equipment, or adjust item
                            quantities.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            href="/stock/registration"
                            class="stock-op-manage-link"
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
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            Registration
                        </a>
                        <a
                            href="/stock/card-management"
                            class="stock-op-manage-link"
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
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                />
                            </svg>
                            Stock Card Management
                        </a>
                        <a
                            href="/stock/property-card-management"
                            class="stock-op-manage-link"
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
                                    d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-4.162a2.25 2.25 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75V5.25A2.25 2.25 0 0011.25 7.5h1.5A2.25 2.25 0 0015 5.25V3.75M9 3.75h6"
                                />
                            </svg>
                            Property Card Management
                        </a>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="stock-op-tab-card"
                        :class="{
                            'stock-op-tab-card-active': activeTab === tab.key,
                        }"
                        @click="setTab(tab.key)"
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

            <div class="stock-op-content p-4 sm:p-6">
                <Receive v-if="activeTab === 'receive'" embedded />
                <ReceiveEquipment
                    v-else-if="activeTab === 'receive-equipment'"
                />
                <Adjust v-else-if="activeTab === 'adjust'" embedded />
            </div>
        </div>

        <IssuedItemsTable />
        <IssuedEquipmentsTable />
    </div>
</template>

<script setup>
import { h, onMounted, ref } from "vue";
import Receive from "./Receive.vue";
import ReceiveEquipment from "./ReceiveEquipment.vue";
import Adjust from "./Adjust.vue";
import IssuedItemsTable from "../../components/stock/IssuedItemsTable.vue";
import IssuedEquipmentsTable from "../../components/stock/IssuedEquipmentsTable.vue";

const IconReceive = () =>
    h(
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
                d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4",
            }),
        ],
    );

const IconAdjust = () =>
    h(
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
                d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z",
            }),
        ],
    );

const tabs = [
    {
        key: "receive",
        label: "Stock Receiving",
        description: "Increase stock for existing items",
        icon: IconReceive,
    },
    {
        key: "receive-equipment",
        label: "Equipment Receiving",
        description: "Increase quantity for registered equipment",
        icon: IconReceive,
    },
    {
        key: "adjust",
        label: "Stock Adjustment",
        description: "Correct counts after physical check",
        icon: IconAdjust,
    },
];

const activeTab = ref("receive");

function setTab(key) {
    activeTab.value = key;

    const url = new URL(window.location.href);
    url.searchParams.set("tab", key);
    window.history.replaceState({}, "", url);
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get("tab");

    if (tab === "register" || tab === "equipments") {
        window.location.replace(
            tab === "equipments"
                ? "/stock/registration#register-equipment"
                : "/stock/registration#register-item",
        );
        return;
    }

    if (params.get("edit")) {
        window.location.replace(
            `/stock/registration?edit=${params.get("edit")}#register-equipment`,
        );
        return;
    }

    if (tabs.some((entry) => entry.key === tab)) {
        activeTab.value = tab;
    }
});
</script>

<style scoped>
.stock-op-manage-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0;
    border: 1px solid #a8b8d4;
    background: transparent;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #001f6b;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.stock-op-manage-link:hover {
    border-color: #001f6b;
    background: #e2e8f2;
}
</style>
