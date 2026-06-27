<template>
    <div class="shadcn-card">
        <div v-if="title || description || $slots.header" class="shadcn-card-header">
            <slot name="header">
                <h3 v-if="title" class="shadcn-card-title">{{ title }}</h3>
                <p v-if="description" class="shadcn-card-description">{{ description }}</p>
            </slot>
        </div>
        <div :class="contentClass">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    contentClass: { type: String, default: 'shadcn-card-content' },
    noPadding: { type: Boolean, default: false },
});
</script>
