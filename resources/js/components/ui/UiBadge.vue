<template>
    <span :class="classes"><slot /></span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'default' },
});

const classes = computed(() => {
    const variants = {
        default: 'shadcn-badge shadcn-badge-default',
        secondary: 'shadcn-badge shadcn-badge-secondary',
        outline: 'shadcn-badge shadcn-badge-outline',
        destructive: 'shadcn-badge shadcn-badge-destructive',
        gold: 'shadcn-badge shadcn-badge-gold',
    };

    return variants[props.variant] || variants.default;
});
</script>
