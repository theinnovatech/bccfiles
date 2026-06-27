<template>
    <span>{{ formatted }}</span>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    duration: { type: Number, default: 900 },
    active: { type: Boolean, default: true },
});

const display = ref(0);
let frameId = null;

const formatted = computed(() => display.value.toLocaleString());

function animateTo(target) {
    if (frameId) {
        cancelAnimationFrame(frameId);
    }

    const from = display.value;
    const diff = target - from;

    if (diff === 0) {
        return;
    }

    const start = performance.now();

    function step(now) {
        const progress = Math.min((now - start) / props.duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        display.value = Math.round(from + diff * eased);

        if (progress < 1) {
            frameId = requestAnimationFrame(step);
        }
    }

    frameId = requestAnimationFrame(step);
}

watch(
    () => [props.value, props.active],
    ([value, active]) => {
        if (active) {
            animateTo(Number(value) || 0);
        }
    },
);

onMounted(() => {
    if (props.active) {
        display.value = 0;
        animateTo(Number(props.value) || 0);
    }
});
</script>
