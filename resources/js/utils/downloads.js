import api from '../services/api';

function filenameFrom(headers, fallback) {
    const disposition = headers?.['content-disposition'] || '';
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);

    return match ? decodeURIComponent(match[1]) : fallback;
}

async function readBlobError(error) {
    const data = error?.response?.data;

    if (data instanceof Blob) {
        try {
            const parsed = JSON.parse(await data.text());
            return parsed.message || 'Download failed.';
        } catch {
            return 'Download failed.';
        }
    }

    return data?.message || error?.message || 'Download failed.';
}

/**
 * Download a file (xlsx / pdf) through axios so permission or validation errors
 * are shown as a message instead of a raw JSON page.
 */
export async function downloadFile(url, params = {}, fallbackName = 'download') {
    try {
        const response = await api.get(url, { params, responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filenameFrom(response.headers, fallbackName);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        throw new Error(await readBlobError(error));
    }
}

export function openInNewTab(url, params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });
    const suffix = query.toString();
    window.open(suffix ? `${url}?${suffix}` : url, '_blank');
}
