// Display labels aligned with the SDO Inventory desktop app. Stored values are unchanged.
export const MOVEMENT_LABELS = {
    IN: 'Received',
    OUT: 'Issued',
    RETURN: 'Returned',
    ADJUSTMENT: 'Adjustment',
};

export function movementLabel(value) {
    const code = typeof value === 'string' ? value : value?.value;
    return MOVEMENT_LABELS[code] || code || '-';
}
