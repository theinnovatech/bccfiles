<template>
    <div class="md:col-span-2 space-y-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-[#00164d]">
                Serial numbers
                <span v-if="required" class="text-[#ce1126]">*</span>
            </label>
            <p class="text-xs text-[#4a6490]">
                Enter one serial number for each unit. Quantity is
                {{ quantity }}.
            </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div v-for="(_, index) in modelValue" :key="index">
                <label class="mb-1 block text-xs font-medium text-[#4a6490]">
                    Unit {{ index + 1 }}
                </label>
                <InputText
                    :model-value="modelValue[index]"
                    class="w-full"
                    :placeholder="`Serial number ${index + 1}`"
                    @update:model-value="update(index, $event)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import InputText from 'primevue/inputtext';

const props = defineProps({
    modelValue: { type: Array, required: true },
    quantity: { type: Number, default: 1 },
    required: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

function update(index, value) {
    const next = [...props.modelValue];
    next[index] = value;
    emit('update:modelValue', next);
}
</script>
