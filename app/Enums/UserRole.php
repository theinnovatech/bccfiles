<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case SupplyOfficer = 'supply_officer';
    case DepartmentUser = 'department_user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::SupplyOfficer => 'Supply Officer',
            self::DepartmentUser => 'Department User',
        };
    }
}
