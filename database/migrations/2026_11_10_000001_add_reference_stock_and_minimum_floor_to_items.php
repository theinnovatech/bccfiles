<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 3 & 4:
 * - Adds a positive reference (target) stock used by the 50% low-stock rule.
 *   Legacy items keep NULL so they are flagged as "reference stock not configured".
 * - Minimum stock now defaults to 1; legacy values below 1 are raised to 1.
 *   Actual balances (current_stock) are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'reference_stock')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedInteger('reference_stock')->nullable()->after('minimum_stock');
            });
        }

        $legacyIds = DB::table('items')->where('minimum_stock', '<', 1)->pluck('id');

        if ($legacyIds->isNotEmpty()) {
            DB::table('items')->whereIn('id', $legacyIds)->update(['minimum_stock' => 1]);

            DB::table('activity_logs')->insert([
                'user_id' => null,
                'action' => 'Migrated',
                'module' => 'Items',
                'description' => sprintf(
                    'System migration raised minimum stock to 1 for %d item(s) (IDs: %s). Stock balances were not changed.',
                    $legacyIds->count(),
                    $legacyIds->implode(', ')
                ),
                'ip_address' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('minimum_stock')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('minimum_stock')->default(0)->change();
        });

        if (Schema::hasColumn('items', 'reference_stock')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('reference_stock');
            });
        }
    }
};
