<?php

namespace App\Enums;

enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            // Desktop app condition term.
            self::Good => 'Good',
            // Stored value stays 'damaged'; display label aligned with desktop app terminology.
            self::Damaged => 'Unserviceable',
        };
    }
}
