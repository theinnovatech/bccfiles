<?php

namespace Database\Seeders;

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setValue('organization_name', AppServiceProvider::DEFAULT_ORGANIZATION_NAME, 'general');
        Setting::setValue('allow_negative_stock', 'false', 'inventory');

        $departments = collect([
            ['name' => 'Human Resource', 'code' => 'HR'],
            ['name' => 'Accounting', 'code' => 'ACC'],
            ['name' => 'Registrar', 'code' => 'REG'],
            ['name' => 'Library', 'code' => 'LIB'],
            ['name' => 'Information Technology', 'code' => 'IT'],
            ['name' => 'Guidance Office', 'code' => 'GDO'],
            ['name' => 'Administration', 'code' => 'ADM'],
        ])->map(fn ($d) => Department::create($d));

        $categories = collect([
            'Office Supplies',
            'Writing Instruments',
            'Paper Products',
            'Filing Supplies',
        ])->map(fn ($name) => Category::create(['name' => $name]));

        $units = collect([
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Ream', 'abbreviation' => 'rm'],
            ['name' => 'Box', 'abbreviation' => 'box'],
            ['name' => 'Pack', 'abbreviation' => 'pk'],
        ])->map(fn ($u) => Unit::create($u));

        $locations = collect([
            ['name' => 'Shelf A-03', 'code' => 'A-03'],
            ['name' => 'Shelf B-01', 'code' => 'B-01'],
            ['name' => 'Cabinet C-02', 'code' => 'C-02'],
        ])->map(fn ($l) => StorageLocation::create($l));

        $accounting = $departments->firstWhere('code', 'ACC');

        $adminEmployee = Employee::create([
            'employee_number' => 'EMP-001',
            'name' => 'Maria Santos',
            'department_id' => $departments->firstWhere('code', 'ADM')->id,
            'position' => 'Supply Officer',
        ]);

        $deptEmployee = Employee::create([
            'employee_number' => 'EMP-002',
            'name' => 'John Cruz',
            'department_id' => $accounting->id,
            'position' => 'Staff',
        ]);

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@obims.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'employee_id' => $adminEmployee->id,
            'is_active' => true,
        ]);

        $supplyOfficer = User::create([
            'name' => 'Maria Santos',
            'email' => 'supply@obims.local',
            'password' => Hash::make('password'),
            'role' => UserRole::SupplyOfficer,
            'employee_id' => $adminEmployee->id,
            'is_active' => true,
        ]);

        $departmentUser = User::create([
            'name' => 'John Cruz',
            'email' => 'accounting@obims.local',
            'password' => Hash::make('password'),
            'role' => UserRole::DepartmentUser,
            'department_id' => $accounting->id,
            'employee_id' => $deptEmployee->id,
            'is_active' => true,
        ]);

        $adminEmployee->update(['user_id' => $supplyOfficer->id]);
        $deptEmployee->update(['user_id' => $departmentUser->id]);

        Item::create([
            'barcode' => '4809012345678',
            'item_name' => 'A4 Bond Paper',
            'description' => 'Standard A4 bond paper',
            'brand' => 'Hardcopy',
            'category_id' => $categories[2]->id,
            'unit_id' => $units[1]->id,
            'location_id' => $locations[0]->id,
            'minimum_stock' => 30,
            'current_stock' => 120,
            'status' => ItemStatus::Active,
        ]);

        Item::create([
            'barcode' => '4809012345679',
            'item_name' => 'Ballpen Blue',
            'description' => 'Blue ink ballpen',
            'brand' => 'Pilot',
            'category_id' => $categories[1]->id,
            'unit_id' => $units[2]->id,
            'location_id' => $locations[1]->id,
            'minimum_stock' => 20,
            'current_stock' => 85,
            'status' => ItemStatus::Active,
        ]);

        Item::create([
            'barcode' => '4809012345680',
            'item_name' => 'Folder Long',
            'description' => 'Long size folder',
            'brand' => 'Ariel',
            'category_id' => $categories[3]->id,
            'unit_id' => $units[0]->id,
            'location_id' => $locations[2]->id,
            'minimum_stock' => 15,
            'current_stock' => 5,
            'status' => ItemStatus::Active,
        ]);
    }
}
