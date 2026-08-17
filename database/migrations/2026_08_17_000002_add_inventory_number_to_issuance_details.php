<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->string('inventory_number')->nullable()->after('property_number');
        });

        $details = DB::table('issuance_details')
            ->whereNull('inventory_number')
            ->get(['id', 'item_id', 'equipment_id']);

        foreach ($details as $detail) {
            $inventoryNumber = null;

            if ($detail->item_id) {
                $inventoryNumber = DB::table('items')
                    ->where('id', $detail->item_id)
                    ->value('inventory_number');
            } elseif ($detail->equipment_id) {
                $inventoryNumber = DB::table('equipments')
                    ->where('id', $detail->equipment_id)
                    ->value('inventory_number');
            }

            if ($inventoryNumber) {
                DB::table('issuance_details')
                    ->where('id', $detail->id)
                    ->update(['inventory_number' => $inventoryNumber]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropColumn('inventory_number');
        });
    }
};
