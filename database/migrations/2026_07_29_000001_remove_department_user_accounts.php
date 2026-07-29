<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $departmentUserIds = DB::table('users')
            ->where('role', 'department_user')
            ->pluck('id');

        if ($departmentUserIds->isNotEmpty()) {
            DB::table('employees')
                ->whereIn('user_id', $departmentUserIds)
                ->update(['user_id' => null, 'updated_at' => now()]);

            DB::table('users')
                ->whereIn('id', $departmentUserIds)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        DB::table('employees')
            ->where('role', 'department_user')
            ->update(['role' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Department user accounts are not restored.
    }
};
