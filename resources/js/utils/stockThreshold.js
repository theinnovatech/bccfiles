import { reactive } from 'vue';
import api from '../services/api';

/**
 * Percentage stock monitoring (mirrors App\Support\StockStatus on the server):
 *   threshold = reference stock x (percent / 100)
 *   remaining = 0               -> Out of Stock
 *   0 < remaining <= threshold  -> Low Stock
 *   remaining > threshold       -> In Stock
 * The server decides the stored status; this is only for previews in forms.
 */
const rule = reactive({ defaultPercent: 50, loaded: false });

export async function loadStockRule() {
    if (rule.loaded) {
        return rule;
    }
    try {
        const { data } = await api.get('/stock-rule');
        rule.defaultPercent = Number(data.default_percent) || 50;
    } catch {
        // keep the documented default
    }
    rule.loaded = true;

    return rule;
}

export function stockRule() {
    return rule;
}

export function effectivePercent(itemPercent) {
    const value = Number(itemPercent);

    return value > 0 ? Math.round(value * 100) / 100 : rule.defaultPercent;
}

export function thresholdFor(reference, itemPercent) {
    const ref = Number(reference);
    if (!(ref > 0)) {
        return null;
    }

    return Math.round(ref * effectivePercent(itemPercent)) / 100;
}

export function classifyStock(remaining, reference, itemPercent) {
    const qty = Number(remaining) || 0;
    if (qty <= 0) {
        return 'out_of_stock';
    }
    const ref = Number(reference);
    if (!(ref > 0)) {
        return null;
    }
    // Integer comparison, same as the server: exactly at the threshold is Low.
    const basisPoints = Math.round(effectivePercent(itemPercent) * 100);

    return qty * 10000 <= ref * basisPoints ? 'low_stock' : 'in_stock';
}

export function formatQty(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    const number = Number(value);

    return Number.isInteger(number)
        ? number.toLocaleString()
        : number.toLocaleString(undefined, { maximumFractionDigits: 2 });
}

/**
 * "Threshold = 1,000 x 50% = 500. In Stock above 500; Low Stock from 1 to 500; Out of Stock at 0."
 */
export function thresholdExplanation(reference, itemPercent) {
    const threshold = thresholdFor(reference, itemPercent);
    if (threshold === null) {
        return 'Enter the reference stock to see the low-stock threshold.';
    }
    const percent = effectivePercent(itemPercent);
    const lowTop = Math.floor(threshold);
    const lowRange = lowTop >= 1 ? `Low Stock from 1 to ${formatQty(lowTop)}` : 'never Low Stock (threshold below 1)';

    return `Threshold = ${formatQty(reference)} × ${formatQty(percent)}% = ${formatQty(threshold)}. In Stock above ${formatQty(threshold)}; ${lowRange}; Out of Stock at 0.`;
}
