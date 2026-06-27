<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Lost = 'lost';
    case Miscount = 'miscount';
    case Correction = 'correction';
}
