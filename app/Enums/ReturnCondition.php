<?php

namespace App\Enums;

enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Returned well',
            self::Damaged => 'Returned with damage',
        };
    }
}
