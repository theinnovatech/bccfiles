<?php

namespace App\Enums;

enum TransactionType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Return = 'RETURN';
    case Adjustment = 'ADJUSTMENT';

    /**
     * Display label used by the SDO Inventory desktop app (stored values are unchanged).
     */
    public function label(): string
    {
        return match ($this) {
            self::In => 'Received',
            self::Out => 'Issued',
            self::Return => 'Returned',
            self::Adjustment => 'Adjustment',
        };
    }
}
