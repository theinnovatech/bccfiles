<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 5: unit employee directory. Existing employees stay active.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'is_active')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('role');
                $table->index(['department_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'is_active')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex(['department_id', 'is_active']);
                $table->dropColumn('is_active');
            });
        }
    }
};
