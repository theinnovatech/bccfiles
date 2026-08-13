<template>
    <div class="global-search" ref="rootRef">
        <div class="global-search-field" :class="{ 'is-open': open }">
            <svg
                class="global-search-icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                />
            </svg>
            <input
                ref="inputRef"
                v-model="query"
                type="search"
                class="global-search-input"
                placeholder="Search pages, items, people…"
                autocomplete="off"
                aria-label="Global search"
                aria-autocomplete="list"
                aria-controls="global-search-results"
                :aria-expanded="open"
                @focus="onFocus"
                @keydown="onKeydown"
                @input="onInput"
            />
            <kbd v-if="!query" class="global-search-kbd">Ctrl K</kbd>
            <button
                v-else
                type="button"
                class="global-search-clear"
                aria-label="Clear search"
                @click="clearQuery"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div
            v-if="open"
            id="global-search-results"
            class="global-search-panel"
            role="listbox"
        >
            <div v-if="loading" class="global-search-empty">Searching…</div>
            <div v-else-if="!query.trim()" class="global-search-empty">
                Type to search pages and records.
            </div>
            <div v-else-if="!flatResults.length" class="global-search-empty">
                No matches for “{{ query.trim() }}”.
            </div>
            <template v-else>
                <div
                    v-for="group in groupedResults"
                    :key="group.label"
                    class="global-search-group"
                >
                    <p class="global-search-group-label">{{ group.label }}</p>
                    <button
                        v-for="item in group.items"
                        :key="item.key"
                        type="button"
                        class="global-search-item"
                        :class="{ 'is-active': flatResults[activeIndex]?.key === item.key }"
                        role="option"
                        :aria-selected="flatResults[activeIndex]?.key === item.key"
                        @mousedown.prevent="goTo(item)"
                        @mouseenter="setActiveByKey(item.key)"
                    >
                        <span class="global-search-item-type">{{ typeLabel(item.type) }}</span>
                        <span class="min-w-0 flex-1 text-left">
                            <span class="global-search-item-label">{{ item.label }}</span>
                            <span v-if="item.subtitle" class="global-search-item-subtitle">
                                {{ item.subtitle }}
                            </span>
                        </span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import api from '../services/api';

const GROUP_ORDER = [
    { type: 'page', label: 'Pages' },
    { type: 'item', label: 'Items' },
    { type: 'equipment', label: 'Equipment' },
    { type: 'person', label: 'People' },
    { type: 'department', label: 'Departments' },
];

const TYPE_LABELS = {
    page: 'Page',
    item: 'Item',
    equipment: 'Equip.',
    person: 'Person',
    department: 'Dept.',
};

const rootRef = ref(null);
const inputRef = ref(null);
const query = ref('');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const activeIndex = ref(0);

let debounceTimer = null;
let abortController = null;

const flatResults = computed(() => results.value);

const groupedResults = computed(() => {
    const buckets = {};
    for (const row of results.value) {
        if (!buckets[row.type]) buckets[row.type] = [];
        buckets[row.type].push(row);
    }

    return GROUP_ORDER
        .filter((group) => buckets[group.type]?.length)
        .map((group) => ({
            label: group.label,
            items: buckets[group.type],
        }));
});

function typeLabel(type) {
    return TYPE_LABELS[type] || type;
}

function setActiveByKey(key) {
    const index = flatResults.value.findIndex((row) => row.key === key);
    if (index >= 0) activeIndex.value = index;
}

function clearQuery() {
    query.value = '';
    results.value = [];
    activeIndex.value = 0;
    open.value = true;
    inputRef.value?.focus();
}

function onFocus() {
    open.value = true;
}

function onInput() {
    open.value = true;
    scheduleSearch();
}

function scheduleSearch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, 220);
}

async function runSearch() {
    const q = query.value.trim();
    if (!q) {
        results.value = [];
        loading.value = false;
        return;
    }

    if (abortController) abortController.abort();
    abortController = new AbortController();
    loading.value = true;

    try {
        const { data } = await api.get('/search', {
            params: { q },
            signal: abortController.signal,
        });
        results.value = Array.isArray(data) ? data : [];
        activeIndex.value = 0;
    } catch (error) {
        if (error.name !== 'CanceledError' && error.code !== 'ERR_CANCELED') {
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
}

function goTo(item) {
    if (!item?.url) return;
    window.location.href = item.url;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
        inputRef.value?.blur();
        return;
    }

    if (!open.value || !flatResults.value.length) {
        if (event.key === 'ArrowDown' && query.value.trim()) {
            open.value = true;
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % flatResults.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + flatResults.value.length) % flatResults.value.length;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const item = flatResults.value[activeIndex.value];
        if (item) goTo(item);
    }
}

function handleDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        open.value = false;
    }
}

function handleGlobalShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = true;
        nextTick(() => inputRef.value?.focus());
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleGlobalShortcut);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleGlobalShortcut);
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortController) abortController.abort();
});
</script>
