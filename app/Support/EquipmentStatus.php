<?php

namespace App\Support;

use App\Models\EquipmentSerialNumber;

/**
 * Shared vocabulary for equipment disposition (Revisions 1 & 7).
 *
 * Disposition (where the unit is in its life cycle) is kept separate from
 *   - condition (serviceable / unserviceable) and
 *   - custody (supply office / employee / supplier),
 * so e.g. a unit can be "For Repair" while a warranty review is active.
 */
class EquipmentStatus
{
    public const AVAILABLE = 'available';
    public const ISSUED = 'issued';
    public const FOR_REPAIR = 'for_repair';
    public const FOR_DISPOSAL = 'for_disposal';
    public const DISPOSED = 'disposed';
    public const RETURNED_TO_SERVICE = 'returned_to_service';

    /** Dashboard buckets (in display order). */
    public const DASHBOARD = [
        self::AVAILABLE,
        self::ISSUED,
        self::FOR_REPAIR,
        self::FOR_DISPOSAL,
        self::DISPOSED,
    ];

    /** Case states that block issuance. */
    public const RESTRICTED = [
        self::FOR_REPAIR,
        self::FOR_DISPOSAL,
        self::DISPOSED,
    ];

    public const CONDITION_SERVICEABLE = 'serviceable';
    public const CONDITION_UNSERVICEABLE = 'unserviceable';

    public const CUSTODY_SUPPLY_OFFICE = 'supply_office';
    public const CUSTODY_SUPPLIER = 'supplier';
    public const CUSTODY_EMPLOYEE = 'employee';

    public const WARRANTY_PENDING = 'pending_review';
    public const WARRANTY_DECIDED = 'decided';

    public const WARRANTY_OUTCOMES = [
        'repaired_by_supplier' => 'Repaired by supplier under warranty',
        'replaced_by_supplier' => 'Replaced by supplier under warranty',
        'claim_denied' => 'Claim denied by supplier',
        'not_covered' => 'Issue not covered by warranty',
    ];

    public static function label(?string $status): string
    {
        return match ($status) {
            self::AVAILABLE => 'Available',
            self::ISSUED => 'Issued',
            self::FOR_REPAIR => 'For Repair',
            self::FOR_DISPOSAL => 'For Disposal',
            self::DISPOSED => 'Disposed',
            self::RETURNED_TO_SERVICE => 'Returned to Service',
            default => 'Unknown',
        };
    }

    /**
     * Label for a serial-number (unit) status.
     */
    public static function unitStatusLabel(?string $status): string
    {
        return match ($status) {
            EquipmentSerialNumber::STATUS_IN_STOCK => 'Available',
            EquipmentSerialNumber::STATUS_ISSUED => 'Issued',
            EquipmentSerialNumber::STATUS_UNAVAILABLE => 'Unserviceable',
            EquipmentSerialNumber::STATUS_FOR_REPAIR => 'For Repair',
            EquipmentSerialNumber::STATUS_FOR_DISPOSAL => 'For Disposal',
            EquipmentSerialNumber::STATUS_DISPOSED => 'Disposed',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    public static function conditionLabel(?string $condition): string
    {
        return match ($condition) {
            self::CONDITION_SERVICEABLE => 'Serviceable',
            self::CONDITION_UNSERVICEABLE => 'Unserviceable',
            default => '—',
        };
    }

    public static function custodyLabel(?string $custody): string
    {
        return match ($custody) {
            self::CUSTODY_SUPPLY_OFFICE => 'Supply Office',
            self::CUSTODY_SUPPLIER => 'Supplier',
            self::CUSTODY_EMPLOYEE => 'Employee',
            default => '—',
        };
    }
}
