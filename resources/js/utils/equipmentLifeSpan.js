export function formatEquipmentLifeSpan(equipment, asOf) {
    if (!equipment) {
        return "—";
    }

    if (
        equipment.life_span_years == null &&
        !equipment.lifespan_expires_on &&
        !equipment.date_acquired
    ) {
        return "—";
    }

    const years = remainingLifeSpanYearsAsOf(equipment, asOf);
    if (years == null) {
        return "—";
    }

    const unit = years === 1 ? "yr" : "yrs";
    const label = `${years} ${unit}`;

    return equipment.lifespan_expires_on || equipment.date_acquired
        ? `${label} remaining`
        : label;
}

export function remainingLifeSpanYearsAsOf(equipment, asOf) {
    const expires = equipmentExpiryDate(equipment);
    const check = asOf ? parseDateOnly(asOf) : startOfDay(new Date());

    if (!expires || !check) {
        return equipment?.life_span_years == null
            ? null
            : Number(equipment.life_span_years);
    }

    if (check.getTime() >= expires.getTime()) {
        return 0;
    }

    let years = expires.getFullYear() - check.getFullYear();
    const probe = new Date(check);
    probe.setFullYear(check.getFullYear() + years);
    if (probe.getTime() > expires.getTime()) {
        years -= 1;
    }

    return years === 0 ? 1 : years;
}

export function equipmentExpiryDate(equipment) {
    const stored = parseDateOnly(equipment?.lifespan_expires_on);
    if (stored) {
        return stored;
    }

    const acquired = parseDateOnly(equipment?.date_acquired);
    const span = Number(
        equipment?.original_life_span_years ?? equipment?.life_span_years,
    );
    if (!acquired || !span) {
        return null;
    }

    const expires = new Date(acquired);
    expires.setFullYear(acquired.getFullYear() + span);
    expires.setHours(0, 0, 0, 0);
    return expires;
}

function parseDateOnly(value) {
    if (!value) {
        return null;
    }

    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

function startOfDay(date) {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);
    return copy;
}

export function equipmentOriginLabel(equipment) {
    if (!equipment) {
        return "Fresh";
    }

    if (equipment.origin === "returned" || equipment.source_return_id) {
        return "Returned";
    }

    return "Fresh";
}

export function hasReachedLifespan(equipment, asOf) {
    if (!equipment) {
        return false;
    }

    const check = asOf
        ? new Date(`${String(asOf).slice(0, 10)}T00:00:00`)
        : new Date();
    check.setHours(0, 0, 0, 0);

    if (equipment.lifespan_expires_on) {
        const limit = new Date(
            `${String(equipment.lifespan_expires_on).slice(0, 10)}T00:00:00`,
        );
        if (Number.isNaN(limit.getTime())) {
            return false;
        }

        return check.getTime() >= limit.getTime();
    }

    if (!equipment.life_span_years || !equipment.date_acquired) {
        return false;
    }

    const acquired = new Date(
        `${String(equipment.date_acquired).slice(0, 10)}T00:00:00`,
    );
    if (Number.isNaN(acquired.getTime())) {
        return false;
    }

    const limit = new Date(acquired);
    limit.setFullYear(limit.getFullYear() + Number(equipment.life_span_years));
    limit.setHours(0, 0, 0, 0);

    return check.getTime() >= limit.getTime();
}
