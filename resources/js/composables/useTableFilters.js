import { computed, ref, unref, watch } from 'vue';

function getValue(obj, path) {
    return String(path).split('.').reduce((acc, key) => acc?.[key], obj);
}

export function useTableFilters(items, config) {
    const resolvedConfig = computed(() => unref(config));

    const defaults = computed(() => {
        const next = {};
        resolvedConfig.value.forEach((filter) => {
            next[filter.key] = filter.default ?? '';
        });
        return next;
    });

    const filters = ref({});

    watch(
        defaults,
        (next) => {
            filters.value = { ...next, ...filters.value };
        },
        { immediate: true },
    );

    const filteredItems = computed(() =>
        items.value.filter((row) =>
            resolvedConfig.value.every((filter) => {
                const value = filters.value[filter.key];

                if (value === '' || value === null || value === undefined) {
                    return true;
                }

                if (filter.type === 'search') {
                    const haystack = (filter.fields || [])
                        .map((field) => String(getValue(row, field) ?? ''))
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(String(value).toLowerCase());
                }

                if (filter.type === 'select') {
                    const fieldValue = filter.match ? filter.match(row) : getValue(row, filter.field);
                    return String(fieldValue ?? '') === String(value);
                }

                if (filter.type === 'boolean') {
                    const fieldValue = filter.match ? filter.match(row) : getValue(row, filter.field);
                    const boolValue = value === true || value === 'true';
                    return Boolean(fieldValue) === boolValue;
                }

                if (filter.type === 'custom') {
                    return filter.predicate ? filter.predicate(row, value) : true;
                }

                return true;
            }),
        ),
    );

    const hasActiveFilters = computed(() =>
        resolvedConfig.value.some((filter) => {
            const value = filters.value[filter.key];
            return value !== '' && value !== null && value !== undefined && value !== filter.default;
        }),
    );

    function resetFilters() {
        filters.value = { ...defaults.value };
    }

    return { filters, filteredItems, hasActiveFilters, resetFilters };
}
