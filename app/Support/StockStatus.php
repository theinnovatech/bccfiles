<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Once;

/**
 * Single source of truth for supply stock status (Revision 3, feedback item 5).
 *
 *   Low-stock threshold = reference stock quantity x (configured percentage / 100)
 *
 *   remaining = 0                              -> Out of Stock
 *   0 < remaining <= threshold                 -> Low Stock
 *   remaining > threshold                      -> In Stock
 *
 * The reference stock is a persisted quantity set by staff (Supply Master); issuance never
 * changes it. The percentage is the item's own setting or, when blank, the default under
 * Settings > Inventory (50% unless changed). Comparisons are done in integers
 * (remaining x 10000 <= reference x percent x 100), so "exactly at the threshold" is always Low.
 *
 * Legacy items without a reference stock keep the previous rule (remaining <= minimum stock)
 * and are flagged "reference stock not configured"; no reference is invented for them.
 */
class StockStatus
{
    public const OUT = 'out_of_stock';
    public const LOW = 'low_stock';
    public const IN = 'in_stock';

    public const LABELS = [
        self::OUT => 'Out of Stock',
        self::LOW => 'Low Stock',
        self::IN => 'In Stock',
    ];

    public const DEFAULT_PERCENT = 50.0;

    public static function defaultPercent(): float
    {
        return once(function () {
            $value = (float) Setting::getValue('low_stock_percent_default', (string) self::DEFAULT_PERCENT);

            return $value > 0 && $value <= 100 ? round($value, 2) : self::DEFAULT_PERCENT;
        });
    }

    public static function flushDefault(): void
    {
        Once::flush();
    }

    public static function effectivePercent(?float $itemPercent): float
    {
        return $itemPercent !== null && $itemPercent > 0 ? round($itemPercent, 2) : self::defaultPercent();
    }

    public static function hasReference(?int $referenceStock): bool
    {
        return $referenceStock !== null && $referenceStock > 0;
    }

    /**
     * Threshold quantity (may be fractional, e.g. 15 x 50% = 7.5).
     */
    public static function threshold(?int $referenceStock, ?int $minimumStock, ?float $percent = null): float
    {
        if (! self::hasReference($referenceStock)) {
            return (float) max(1, (int) $minimumStock);
        }

        return round($referenceStock * self::effectivePercent($percent) / 100, 2);
    }

    public static function classify(int $available, ?int $referenceStock, ?int $minimumStock, ?float $percent = null): string
    {
        if ($available <= 0) {
            return self::OUT;
        }

        if (! self::hasReference($referenceStock)) {
            return $available <= max(1, (int) $minimumStock) ? self::LOW : self::IN;
        }

        $basisPoints = (int) round(self::effectivePercent($percent) * 100);

        return $available * 10000 <= $referenceStock * $basisPoints ? self::LOW : self::IN;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? 'Unknown';
    }

    public static function isAlerting(string $status): bool
    {
        return $status === self::OUT || $status === self::LOW;
    }

    /**
     * SQL condition that is true when an items row is at or below its threshold (Low or Out).
     */
    public static function alertingSql(string $table = 'items'): string
    {
        $default = number_format(self::defaultPercent(), 2, '.', '');
        $percent = "COALESCE(NULLIF({$table}.low_stock_percent, 0), {$default})";

        return "(({$table}.reference_stock IS NOT NULL AND {$table}.reference_stock > 0 "
            ."AND {$table}.current_stock * 100 <= {$table}.reference_stock * {$percent}) "
            ."OR (({$table}.reference_stock IS NULL OR {$table}.reference_stock = 0) "
            ."AND {$table}.current_stock <= GREATEST({$table}.minimum_stock, 1)))";
    }

    /**
     * Restrict an items query to one status (or to Low + Out with "alerting").
     */
    public static function apply(Builder $query, ?string $status, string $table = 'items'): Builder
    {
        $alerting = self::alertingSql($table);

        return match ($status) {
            self::OUT => $query->where("{$table}.current_stock", '<=', 0),
            self::LOW => $query->where("{$table}.current_stock", '>', 0)->whereRaw($alerting),
            self::IN => $query->where("{$table}.current_stock", '>', 0)->whereRaw("NOT {$alerting}"),
            'alerting' => $query->where(fn ($q) => $q->where("{$table}.current_stock", '<=', 0)->orWhereRaw($alerting)),
            default => $query,
        };
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::IN, self::LOW, self::OUT];
    }
}
