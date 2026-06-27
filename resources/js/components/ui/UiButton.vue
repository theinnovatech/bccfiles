<template>
    <button :type="type" :class="classes" :disabled="disabled || loading" @click="$emit('click', $event)">
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'default' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    class: { type: String, default: '' },
});

defineEmits(['click']);

const classes = computed(() => {
    const variants = {
        default: 'shadcn-btn-default',
        outline: 'shadcn-btn-outline',
        secondary: 'shadcn-btn-secondary',
        ghost: 'shadcn-btn-ghost',
        destructive: 'shadcn-btn-destructive',
    };

    const sizes = {
        default: '',
        sm: 'shadcn-btn-sm',
        icon: 'shadcn-btn-icon',
    };

    return ['shadcn-btn', variants[props.variant] || variants.default, sizes[props.size] || '', props.class].join(' ');
});
</script>
