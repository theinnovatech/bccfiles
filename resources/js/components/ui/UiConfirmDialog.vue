<template>
    <Teleport to="body">
        <Transition name="obims-confirm">
            <div v-if="confirmState.visible" class="obims-confirm-overlay" @click.self="handleConfirmReject">
                <div class="obims-confirm-dialog" role="alertdialog" aria-modal="true">
                    <div class="obims-confirm-icon" :class="confirmState.variant === 'destructive' ? 'obims-confirm-icon-danger' : 'obims-confirm-icon-default'">
                        <svg v-if="confirmState.variant === 'destructive'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </div>

                    <div class="obims-confirm-body">
                        <h3 class="obims-confirm-title">{{ confirmState.title }}</h3>
                        <p v-if="confirmState.message" class="obims-confirm-message">{{ confirmState.message }}</p>
                        <p v-if="confirmState.detail" class="obims-confirm-detail">{{ confirmState.detail }}</p>
                    </div>

                    <div class="obims-confirm-actions">
                        <UiButton variant="outline" :disabled="confirmState.loading" @click="handleConfirmReject">
                            {{ confirmState.rejectLabel }}
                        </UiButton>
                        <UiButton
                            :variant="confirmState.variant === 'destructive' ? 'destructive' : 'default'"
                            :loading="confirmState.loading"
                            @click="handleConfirmAccept"
                        >
                            {{ confirmState.acceptLabel }}
                        </UiButton>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import UiButton from './UiButton.vue';
import { confirmState, handleConfirmAccept, handleConfirmReject } from '../../composables/confirm';
</script>
