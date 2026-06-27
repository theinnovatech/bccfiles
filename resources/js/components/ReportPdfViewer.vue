<template>
    <div class="report-pdf-viewer">
        <div v-if="!type" class="report-pdf-placeholder">
            <svg class="h-12 w-12 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625A2.25 2.25 0 003.75 7.5v12.75A2.25 2.25 0 006 7.5h13.125A2.25 2.25 0 0021 18.75V7.5M8.25 7.5V4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V7.5" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">PDF preview</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">Select a report to view its PDF here before downloading.</p>
        </div>

        <div v-else-if="error" class="report-pdf-placeholder">
            <p class="text-sm font-medium text-[#ce1126]">Unable to load PDF preview</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">{{ error }}</p>
        </div>

        <div v-else-if="empty" class="report-pdf-placeholder">
            <svg class="h-12 w-12 text-[#c8d6ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#002a7a]">No data yet</p>
            <p class="mt-1 text-xs text-[#5b7fbf]">There is no data to include in this PDF report.</p>
        </div>

        <div v-else class="report-pdf-container">
            <div v-if="loading" class="report-pdf-loading-overlay">
                <svg class="h-8 w-8 animate-spin text-[#0038a8]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <p class="mt-3 text-sm text-[#5b7fbf]">Generating PDF preview...</p>
            </div>

            <iframe
                :key="iframeKey"
                :src="pdfUrl"
                class="report-pdf-frame"
                title="Report PDF preview"
                @load="onIframeLoad"
            />
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    type: { type: String, default: '' },
    hasData: { type: Boolean, default: false },
    params: { type: Object, default: () => ({}) },
});

const loading = ref(false);
const error = ref('');
const empty = ref(false);
const pdfUrl = ref('');
const iframeKey = ref(0);
let loadTimeout = null;

function clearLoadTimeout() {
    if (loadTimeout) {
        clearTimeout(loadTimeout);
        loadTimeout = null;
    }
}

function buildPdfUrl() {
    const params = new URLSearchParams({ preview: '1' });

    Object.entries(props.params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    return `/reports/${props.type}/pdf?${params.toString()}`;
}

function loadPdf() {
    clearLoadTimeout();
    error.value = '';
    empty.value = false;
    pdfUrl.value = '';

    if (!props.type) {
        loading.value = false;
        return;
    }

    if (!props.hasData) {
        empty.value = true;
        loading.value = false;
        return;
    }

    loading.value = true;
    pdfUrl.value = buildPdfUrl();
    iframeKey.value += 1;

    loadTimeout = setTimeout(() => {
        loading.value = false;
    }, 15000);
}

function onIframeLoad(event) {
    clearLoadTimeout();
    loading.value = false;

    try {
        const doc = event.target.contentDocument;

        if (doc?.contentType?.includes('text/html')) {
            error.value = 'The PDF could not be generated.';
            pdfUrl.value = '';
        }
    } catch {
        // PDF viewer may block contentDocument access; treat as loaded.
    }
}

watch(
    () => [props.type, props.hasData, JSON.stringify(props.params)],
    () => loadPdf(),
    { immediate: true },
);

onBeforeUnmount(clearLoadTimeout);
</script>

<style scoped>
.report-pdf-viewer {
    overflow: hidden;
    border: 1px solid #c8d6ef;
    border-radius: 0.5rem;
    background: #eef2fa;
}

.report-pdf-container {
    position: relative;
}

.report-pdf-loading-overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
    display: flex;
    min-height: 420px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #eef2fa;
    text-align: center;
}

.report-pdf-frame {
    display: block;
    width: 100%;
    height: min(720px, 70vh);
    border: 0;
    background: white;
}

@media (min-width: 640px) {
    .report-pdf-frame {
        height: 720px;
    }
}

.report-pdf-placeholder {
    display: flex;
    min-height: 420px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
}
</style>
