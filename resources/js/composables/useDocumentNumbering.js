import { reactive } from 'vue';
import api from '../services/api';

/**
 * Organization numbering settings (Settings > Document numbering), loaded once per page.
 * Used by forms to explain whether an official number is typed (manual) or generated (auto),
 * and to show the next number the server would assign. The server always decides the final
 * number; this is only a hint.
 */
const state = reactive({ loaded: false, loading: null, types: {} });

export function useDocumentNumbering() {
    async function load(force = false) {
        if (state.loaded && !force) {
            return state.types;
        }
        if (!state.loading || force) {
            state.loading = api
                .get('/document-numbering')
                .then(({ data }) => {
                    state.types = Object.fromEntries((data.types ?? []).map((row) => [row.document_type, row]));
                    state.loaded = true;
                })
                .catch(() => {
                    state.types = {};
                })
                .finally(() => {
                    state.loading = null;
                });
        }
        await state.loading;

        return state.types;
    }

    function setting(type) {
        return state.types[type] ?? null;
    }

    function isAuto(type) {
        return setting(type)?.mode === 'auto';
    }

    /**
     * Placeholder / hint text for a number input.
     */
    function hint(type) {
        const row = setting(type);
        if (!row) {
            return '';
        }
        if (row.mode === 'auto') {
            return `Leave blank to assign the next number automatically (next: ${row.next_preview}). Type a number only to use the one already assigned on paper.`;
        }

        return row.required
            ? 'Manual numbering: type the number assigned by the organization (required).'
            : 'Manual numbering: type the number assigned by the organization, or leave blank if none.';
    }

    function placeholder(type) {
        const row = setting(type);
        if (!row) {
            return '';
        }

        return row.mode === 'auto' ? `Auto: ${row.next_preview}` : row.required ? 'Required' : 'Optional';
    }

    return { state, load, setting, isAuto, hint, placeholder };
}
