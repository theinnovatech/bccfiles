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

    const parts = remainingLifeSpanParts(equipment, asOf);
    if (!parts) {
        return "—";
    }

    const remaining = Boolean(
        equipment.lifespan_expires_on || equipment.date_acquired,
    );

    return formatLifeSpanParts(parts, remaining);
}

export function remainingLifeSpanYearsAsOf(equipment, asOf) {
    const parts = remainingLifeSpanParts(equipment, asOf);
    return parts ? parts.years : null;
}

function returnedStockAsOf(equipment) {
    if (
        equipment?.origin !== "returned" &&
        !equipment?.source_return_id &&
        !equipment?.source_return
    ) {
        return null;
    }

    return equipment?.source_return?.date_returned || null;
}

export function remainingLifeSpanParts(equipment, asOf) {
    const expires = equipmentExpiryDate(equipment);
    const check = asOf
        ? parseDateOnly(asOf)
        : parseDateOnly(returnedStockAsOf(equipment)) || startOfDay(new Date());

    if (!expires || !check) {
        if (equipment?.life_span_years == null) {
            return null;
        }

        return {
            years: Number(equipment.life_span_years),
            months: 0,
            days: 0,
        };
    }

    if (check.getTime() >= expires.getTime()) {
        return { years: 0, months: 0, days: 0 };
    }

    return calendarDiff(check, expires);
}

export function formatLifeSpanParts(parts, remaining = false) {
    const years = Number(parts?.years ?? 0);
    const months = Number(parts?.months ?? 0);
    const days = Number(parts?.days ?? 0);
    const chunks = [];

    if (years > 0) {
        chunks.push(`${years} ${years === 1 ? "yr" : "yrs"}`);
    }

    if (months > 0) {
        chunks.push(`${months} ${months === 1 ? "mo" : "mos"}`);
    }

    if (years === 0 && months === 0 && days > 0) {
        chunks.push(`${days} ${days === 1 ? "day" : "days"}`);
    }

    const label = chunks.length ? chunks.join(" ") : "0 yrs";

    return remaining ? `${label} remaining` : label;
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

function calendarDiff(from, to) {
    let years = to.getFullYear() - from.getFullYear();
    let months = to.getMonth() - from.getMonth();
    let days = to.getDate() - from.getDate();

    if (days < 0) {
        months -= 1;
        const daysInPreviousMonth = new Date(
            to.getFullYear(),
            to.getMonth(),
            0,
        ).getDate();
        days += daysInPreviousMonth;
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    return { years, months, days };
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

export function hasNoRemainingLifeSpan(equipment) {
    if (!equipment) {
        return false;
    }

    if (Number(equipment.life_span_years) === 0) {
        const parts = remainingLifeSpanParts(equipment);
        if (!parts) {
            return true;
        }

        return parts.years === 0 && parts.months === 0 && parts.days === 0;
    }

    const parts = remainingLifeSpanParts(equipment);
    if (!parts) {
        return false;
    }

    return parts.years === 0 && parts.months === 0 && parts.days === 0;
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
