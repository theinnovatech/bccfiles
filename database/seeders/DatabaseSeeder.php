<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setValue('organization_name', AppServiceProvider::DEFAULT_ORGANIZATION_NAME, 'general');
        Setting::setValue('allow_negative_stock', 'false', 'inventory');
        Setting::setValue('department_user_pages', json_encode(['dashboard']), 'permissions');

        User::create([
            'name' => 'System Admin',
            'email' => 'admin@obims.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Maria Santos',
            'email' => 'supply@obims.local',
            'password' => Hash::make('password'),
            'role' => UserRole::SupplyOfficer,
            'is_active' => true,
        ]);
    }
}
