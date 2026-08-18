export function formatEquipmentLifeSpan(equipment) {
    if (equipment?.life_span_years == null && !equipment?.lifespan_expires_on) {
        return "—";
    }

    let years =
        equipment?.life_span_years == null
            ? null
            : Number(equipment.life_span_years);

    const expires = parseDateOnly(equipment?.lifespan_expires_on);
    const today = startOfDay(new Date());

    if (expires && expires.getTime() > today.getTime() && (!years || years < 1)) {
        years = 1;
    }

    if (years == null) {
        return "—";
    }

    const unit = years === 1 ? "yr" : "yrs";
    const label = `${years} ${unit}`;

    return equipment.lifespan_expires_on ? `${label} remaining` : label;
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
