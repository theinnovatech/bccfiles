export function syncSerialSlots(serials, quantity) {
    const count = Math.max(1, Number(quantity) || 1);
    const next = Array.isArray(serials) ? serials.map((value) => String(value ?? "")) : [];

    while (next.length < count) {
        next.push("");
    }

    if (next.length > count) {
        next.length = count;
    }

    return next;
}

export function filledSerialNumbers(serials) {
    return (Array.isArray(serials) ? serials : [])
        .map((value) => String(value ?? "").trim())
        .filter(Boolean);
}

export function formatSerialNumbers(serials) {
    const values = (Array.isArray(serials) ? serials : [])
        .map((row) => (typeof row === "string" ? row : row?.serial_number))
        .map((value) => String(value ?? "").trim())
        .filter(Boolean);

    return values.length ? values.join(", ") : "—";
}

export function inStockSerials(equipment) {
    return (equipment?.serial_numbers ?? []).filter(
        (row) => row.status === "in_stock" && !row.issuance_detail_id,
    );
}

export function toSerialId(value) {
    const id = Number(value);

    return Number.isInteger(id) && id > 0 ? id : null;
}

export function syncSerialIds(ids, quantity) {
    const count = Math.max(1, Number(quantity) || 1);

    return Array.from({ length: count }, (_, index) =>
        toSerialId(Array.isArray(ids) ? ids[index] : null),
    );
}

export function filledSerialIds(ids) {
    return (Array.isArray(ids) ? ids : []).map(toSerialId).filter(Boolean);
}
