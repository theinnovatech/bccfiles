<?php

namespace App\Support;

/**
 * Accounting class of an equipment unit, as in the SDO Inventory desktop app. It decides the
 * custody form: semi-expendable property goes on an Inventory Custodian Slip (ICS) and the IIRUP;
 * property, plant and equipment goes on a PAR and the Property Card (Appendix 69).
 */
final class PropertyClass
{
    public const SEMI_EXPENDABLE = 'semi_expendable';
    public const PPE = 'ppe';

    public const LABELS = [
        self::SEMI_EXPENDABLE => 'Semi-expendable property',
        self::PPE => 'Property, plant and equipment',
    ];

    public static function label(?string $class): string
    {
        return self::LABELS[$class] ?? 'Not classified';
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }
}
