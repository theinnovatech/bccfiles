<?php

use App\Support\ReferenceNumberGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('inventory_number')->nullable()->unique()->after('item_number');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->string('inventory_number')->nullable()->unique()->after('property_number');
        });

        $itemIds = DB::table('items')
            ->whereNull('inventory_number')
            ->orderBy('id')
            ->pluck('id');

        foreach ($itemIds as $id) {
            DB::table('items')
                ->where('id', $id)
                ->update([
                    'inventory_number' => ReferenceNumberGenerator::forInventory(),
                ]);
        }

        $equipmentIds = DB::table('equipments')
            ->whereNull('inventory_number')
            ->orderBy('id')
            ->pluck('id');

        foreach ($equipmentIds as $id) {
            DB::table('equipments')
                ->where('id', $id)
                ->update([
                    'inventory_number' => ReferenceNumberGenerator::forInventory(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['inventory_number']);
            $table->dropColumn('inventory_number');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique(['inventory_number']);
            $table->dropColumn('inventory_number');
        });
    }
};
