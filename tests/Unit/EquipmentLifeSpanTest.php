<?php

uses(Tests\TestCase::class);

use App\Models\Equipment;
use Carbon\Carbon;

function equipmentWithSpan(string $acquired, int $years): Equipment
{
    $start = Carbon::parse($acquired)->startOfDay();

    return new Equipment([
        'life_span_years' => $years,
        'date_acquired' => $start,
        'lifespan_expires_on' => $start->copy()->addYears($years),
    ]);
}

test('one leftover month is not counted as a remaining year', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2026-12-01')))->toMatchArray([
        'years' => 0,
        'months' => 1,
        'days' => 0,
    ]);
    expect($equipment->remainingLifeSpanYears(Carbon::parse('2026-12-01')))->toBe(0);
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2026-12-01')))->toBe('1 mo remaining');
});

test('complete years keep leftover months instead of rounding up', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2025-06-01')))->toMatchArray([
        'years' => 1,
        'months' => 7,
        'days' => 0,
    ]);
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2025-06-01')))->toBe('1 yr 7 mos remaining');
});

test('full years remaining stay exact on the anniversary', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2025-01-01')))->toMatchArray([
        'years' => 2,
        'months' => 0,
        'days' => 0,
    ]);
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2025-01-01')))->toBe('2 yrs remaining');
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2026-01-01')))->toBe('1 yr remaining');
});

test('leftover days under a month are not rounded up to a month', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2026-12-15')))->toMatchArray([
        'years' => 0,
        'months' => 0,
        'days' => 17,
    ]);
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2026-12-15')))->toBe('17 days remaining');
});

test('life span is zero on and after the expiry date', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2027-01-01')))->toMatchArray([
        'years' => 0,
        'months' => 0,
        'days' => 0,
    ]);
    expect($equipment->hasReachedLifespan(Carbon::parse('2027-01-01')))->toBeTrue();
    expect($equipment->hasReachedLifespan(Carbon::parse('2026-12-31')))->toBeFalse();
});

test('remaining at return is from date returned to expiry not from today', function () {
    $equipment = equipmentWithSpan('2022-01-01', 5);

    expect($equipment->remainingLifeSpan(Carbon::parse('2024-06-01')))->toMatchArray([
        'years' => 2,
        'months' => 7,
        'days' => 0,
    ]);
    expect($equipment->formattedRemainingLifeSpan(Carbon::parse('2024-06-01')))->toBe('2 yrs 7 mos remaining');
});
