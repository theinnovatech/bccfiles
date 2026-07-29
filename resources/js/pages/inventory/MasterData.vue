<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">Settings</h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Manage item categories, equipment categories, units of
                    measure, storage locations, and system configuration.
                </p>

                <div
                    class="mt-4 grid gap-2 sm:grid-cols-2"
                    :class="
                        visibleTabs.length > 4
                            ? 'lg:grid-cols-5'
                            : 'lg:grid-cols-4'
                    "
                >
                    <button
                        v-for="tab in visibleTabs"
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
                <SystemSettings
                    v-else-if="activeTab === 'system' && auth.isAdmin"
                    embedded
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, h, onMounted, ref } from "vue";
import Categories from "./Categories.vue";
import EquipmentCategories from "./EquipmentCategories.vue";
import Units from "./Units.vue";
import Locations from "./Locations.vue";
import SystemSettings from "../Settings.vue";
import { useAuthStore } from "../../stores/auth";

const auth = useAuthStore();

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
                d: "M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3",
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

const IconSystem = () =>
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
                d: "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
            }),
            h("path", {
                "stroke-linecap": "round",
                "stroke-linejoin": "round",
                d: "M15 12a3 3 0 11-6 0 3 3 0 016 0z",
            }),
        ],
    );

const tabs = [
    {
        key: "categories",
        label: "Item Categories",
        description: "Group inventory items by type",
        icon: IconCategories,
        adminOnly: false,
    },
    {
        key: "equipment-categories",
        label: "Equipment Categories",
        description: "Categories used when registering equipment",
        icon: IconEquipmentCategories,
        adminOnly: false,
    },
    {
        key: "units",
        label: "Units",
        description: "Define units of measure",
        icon: IconUnits,
        adminOnly: false,
    },
    {
        key: "locations",
        label: "Locations",
        description: "Storage areas and shelves",
        icon: IconLocations,
        adminOnly: false,
    },
    {
        key: "system",
        label: "System",
        description: "Organization and inventory rules",
        icon: IconSystem,
        adminOnly: true,
    },
];

const visibleTabs = computed(() =>
    tabs.filter((tab) => !tab.adminOnly || auth.isAdmin),
);

const activeTab = ref("categories");

function setTab(key) {
    activeTab.value = key;

    const url = new URL(window.location.href);
    url.searchParams.set("tab", key);
    window.history.replaceState({}, "", url);
}

onMounted(() => {
    const tab = new URLSearchParams(window.location.search).get("tab");

    if (visibleTabs.value.some((entry) => entry.key === tab)) {
        activeTab.value = tab;
    } else if (tab === "system" && !auth.isAdmin) {
        activeTab.value = "categories";
    }
});
</script>
