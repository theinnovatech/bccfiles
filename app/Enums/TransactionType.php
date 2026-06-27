<?php

namespace App\Enums;

enum TransactionType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Return = 'RETURN';
    case Adjustment = 'ADJUSTMENT';
}
