<template>
    <div class="stock-op-summary">
        <div class="stock-op-summary-header">
            <div class="stock-op-summary-icon" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <div>
                <p class="stock-op-summary-name">{{ item.item_name }}</p>
                <p v-if="item.barcode" class="stock-op-summary-meta">{{ item.barcode }}</p>
            </div>
        </div>
        <dl class="stock-op-summary-grid">
            <div v-for="field in fields" :key="field.label" class="stock-op-summary-field">
                <dt>{{ field.label }}</dt>
                <dd>{{ field.value }}</dd>
            </div>
        </dl>
    </div>
</template>

<script setup>
defineProps({
    item: { type: Object, required: true },
    fields: { type: Array, default: () => [] },
});
</script>
