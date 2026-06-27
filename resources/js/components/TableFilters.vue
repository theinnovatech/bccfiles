<template>
    <div class="table-filters">
        <div class="table-filters-header">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-[#0038a8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.036a2.5 2.5 0 01-.659 1.591l-5.432 6.198a2.5 2.5 0 00-.659 1.591v2.927a.75.75 0 01-1.085.67l-2.5-1.25a.75.75 0 01-.415-.67v-1.677a2.5 2.5 0 00-.659-1.591L4.659 7.401A2.5 2.5 0 014 5.81V4.774c0-.54.384-1.006.917-1.096A41.033 41.033 0 0112 3z" />
                </svg>
                <p class="text-sm font-medium text-[#002a7a]">Filters</p>
                <span v-if="resultCount !== null" class="table-filters-count">{{ resultCount }} result{{ resultCount === 1 ? '' : 's' }}</span>
            </div>
            <button v-if="hasActiveFilters" type="button" class="table-filters-clear" @click="$emit('reset')">
                Clear all
            </button>
        </div>

        <div class="table-filters-grid">
            <div v-for="filter in filters" :key="filter.key" class="space-y-1.5">
                <label class="text-xs font-medium text-[#5b7fbf]">{{ filter.label }}</label>

                <input
                    v-if="filter.type === 'search'"
                    :value="modelValue[filter.key]"
                    type="search"
                    class="shadcn-input"
                    :placeholder="filter.placeholder || 'Search...'"
                    @input="update(filter.key, $event.target.value)"
                />

                <select
                    v-else-if="filter.type === 'select' || filter.type === 'boolean' || filter.type === 'custom'"
                    :value="modelValue[filter.key]"
                    class="shadcn-input"
                    @change="update(filter.key, $event.target.value)"
                >
                    <option v-for="option in filter.options" :key="String(option.value)" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Object, required: true },
    filters: { type: Array, required: true },
    hasActiveFilters: { type: Boolean, default: false },
    resultCount: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue', 'reset']);

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>
