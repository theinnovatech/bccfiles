<template>
    <div class="space-y-6">
        <div id="register-item" class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Register Item
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Add new supply items to the catalog.
                </p>
            </div>
            <div class="stock-op-content p-4 sm:p-6">
                <RegisterItem embedded />
            </div>
        </div>

        <div id="register-equipment" class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <h3 class="text-lg font-semibold text-[#00164d]">
                    Register Equipment
                </h3>
                <p class="mt-1 text-sm text-[#4a6490]">
                    Add new equipment records with property numbers.
                </p>
            </div>
            <div class="stock-op-content p-4 sm:p-6">
                <Equipments embedded />
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import RegisterItem from "../inventory/RegisterItem.vue";
import Equipments from "./Equipments.vue";

onMounted(() => {
    const hash = window.location.hash.replace("#", "");
    const editId = new URLSearchParams(window.location.search).get("edit");
    const targetId =
        hash === "register-equipment" || editId
            ? "register-equipment"
            : hash === "register-item"
              ? "register-item"
              : null;

    if (!targetId) {
        return;
    }

    requestAnimationFrame(() => {
        document
            .getElementById(targetId)
            ?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
});
</script>
