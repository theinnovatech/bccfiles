<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'categories',
        'units',
        'storage_locations',
        'departments',
        'employees',
        'settings',
        'items',
        'stock_movements',
        'supply_requests',
        'request_details',
        'issuances',
        'issuance_details',
        'returns',
        'stock_count_sessions',
        'stock_count_items',
        'activity_logs',
        'obims_notifications',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
