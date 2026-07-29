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

    private static function generateFromTable(string $table, string $column, string $prefix): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";

        $latest = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

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
