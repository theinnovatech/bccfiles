<template>
    <button
        type="button"
        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[#5b7fbf] transition-colors hover:bg-[#eef2fa] hover:text-[#0038a8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0038a8] focus-visible:ring-offset-1"
        :aria-label="ariaLabel"
        :aria-expanded="open"
        @mouseenter="show"
        @mouseleave="scheduleHide"
        @focus="show"
        @blur="scheduleHide"
    >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>

    <Teleport to="body">
        <Transition name="stock-estimate-info">
            <div
                v-show="open"
                class="stock-estimate-info-popover"
                role="dialog"
                :aria-label="ariaLabel"
                :style="popoverStyle"
                @mouseenter="cancelHide"
                @mouseleave="hide"
            >
                <p v-if="title" class="text-sm font-semibold text-[#002a7a]">{{ title }}</p>
                <div class="text-xs leading-relaxed text-[#5b7fbf]" :class="{ 'mt-2': title }">
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    ariaLabel: { type: String, required: true },
    width: { type: Number, default: 300 },
});

const open = ref(false);
const popoverStyle = ref({ top: '0px', left: '0px', width: `${props.width}px` });
let hideTimer = null;

const EDGE_PADDING = 12;

function show(event) {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }

    const trigger = event?.currentTarget;
    if (trigger instanceof HTMLElement) {
        const rect = trigger.getBoundingClientRect();
        const width = Math.min(props.width, window.innerWidth - EDGE_PADDING * 2);
        let left = rect.left + rect.width / 2 - width / 2;
        left = Math.max(EDGE_PADDING, Math.min(left, window.innerWidth - width - EDGE_PADDING));

        popoverStyle.value = {
            top: `${rect.bottom + 8}px`,
            left: `${left}px`,
            width: `${width}px`,
        };
    }

    open.value = true;
}

function scheduleHide() {
    hideTimer = setTimeout(() => {
        open.value = false;
    }, 120);
}

function cancelHide() {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
}

function hide() {
    open.value = false;
}
</script>
