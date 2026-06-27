<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#c8d6ef] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#002a7a]">
                    Master Data
                </h3>
                <p class="mt-1 text-sm text-[#5b7fbf]">
                    Manage item categories, equipment categories, units of
                    measure, and storage locations.
                </p>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
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
                <Categories v-if="activeTab === 'categories'" embedded />
                <EquipmentCategories
                    v-else-if="activeTab === 'equipment-categories'"
                    embedded
                />
                <Units v-else-if="activeTab === 'units'" embedded />
                <Locations v-else-if="activeTab === 'locations'" embedded />
            </div>
        </div>
    </div>
</template>

<script setup>
import { h, onMounted, ref } from "vue";
import Categories from "./Categories.vue";
import EquipmentCategories from "./EquipmentCategories.vue";
import Units from "./Units.vue";
import Locations from "./Locations.vue";

const IconCategories = () =>
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
                d: "M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z",
            }),
        ],
    );

const IconUnits = () =>
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
                d: "M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m0 0v12m0-12v-2.25m0 2.25h10.5M7.5 18h10.5M7.5 18v-2.25m0 2.25H3.75",
            }),
        ],
    );

const IconLocations = () =>
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
                d: "M15 10.5a3 3 0 11-6 0 3 3 0 016 0z",
            }),
            h("path", {
                "stroke-linecap": "round",
                "stroke-linejoin": "round",
                d: "M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z",
            }),
        ],
    );

const IconEquipmentCategories = () =>
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
                d: "M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-4.162a2.25 2.25 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M9 3.75V5.25A2.25 2.25 0 0011.25 7.5h1.5A2.25 2.25 0 0015 5.25V3.75M9 3.75h6",
            }),
        ],
    );

const tabs = [
    {
        key: "categories",
        label: "Item Categories",
        description: "Group inventory items by type",
        icon: IconCategories,
    },
    {
        key: "equipment-categories",
        label: "Equipment Categories",
        description: "Categories used when registering equipment",
        icon: IconEquipmentCategories,
    },
    {
        key: "units",
        label: "Units",
        description: "Define units of measure",
        icon: IconUnits,
    },
    {
        key: "locations",
        label: "Locations",
        description: "Storage areas and shelves",
        icon: IconLocations,
    },
];

const activeTab = ref("categories");

function setTab(key) {
    activeTab.value = key;

    const url = new URL(window.location.href);
    url.searchParams.set("tab", key);
    window.history.replaceState({}, "", url);
}

onMounted(() => {
    const tab = new URLSearchParams(window.location.search).get("tab");

    if (tabs.some((entry) => entry.key === tab)) {
        activeTab.value = tab;
    }
});
</script>
