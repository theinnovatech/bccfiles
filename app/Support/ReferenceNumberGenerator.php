<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ReferenceNumberGenerator
{
    public static function generate(string $prefix): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";

        $latest = DB::table(self::tableForPrefix($prefix))
            ->where('reference_column', 'like', $pattern)
            ->lockForUpdate()
            ->orderByDesc('reference_column')
            ->value('reference_column');

        $sequence = 1;

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    public static function forIssuance(): string
    {
        return self::generateFromTable('issuances', 'issuance_number', 'ISS');
    }

    public static function forEmployee(): string
    {
        return self::generateFromTable('employees', 'employee_number', 'EMP');
    }

    public static function forMovement(): string
    {
        return self::generateFromTable('stock_movements', 'reference_number', 'MOV');
    }

    public static function forEquipment(): string
    {
        return self::generateFromTable('equipments', 'property_number', 'PROP');
    }

    public static function forItem(): string
    {
        return self::generateFromTable('items', 'item_number', 'ITEM');
    }

    /**
     * Shared inventory number across items and equipments (INV-YYYY-####).
     */
    public static function forInventory(): string
    {
        $year = now()->year;
        $pattern = "INV-{$year}-%";

        $latestItem = DB::table('items')
            ->where('inventory_number', 'like', $pattern)
            ->orderByDesc('inventory_number')
            ->value('inventory_number');

        $latestEquipment = DB::table('equipments')
            ->where('inventory_number', 'like', $pattern)
            ->orderByDesc('inventory_number')
            ->value('inventory_number');

        $sequence = max(
            self::sequenceFromReference($latestItem),
            self::sequenceFromReference($latestEquipment)
        ) + 1;

        return sprintf('INV-%d-%04d', $year, $sequence);
    }

    private static function sequenceFromReference(?string $reference): int
    {
        if ($reference && preg_match('/-(\d+)$/', $reference, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private static function generateFromTable(string $table, string $column, string $prefix): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";

        $latest = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderByDesc($column)
            ->value($column);

        $sequence = self::sequenceFromReference($latest) + 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    private static function tableForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'ISS' => 'issuances',
            default => 'stock_movements',
        };
    }
}
