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
            $table->string('property_number')->nullable()->after('barcode');
        });

        $details = DB::table('issuance_details')
            ->whereNotNull('equipment_id')
            ->whereNull('property_number')
            ->get(['id', 'equipment_id']);

        foreach ($details as $detail) {
            $propertyNumber = DB::table('equipments')
                ->where('id', $detail->equipment_id)
                ->value('property_number');

            if ($propertyNumber) {
                DB::table('issuance_details')
                    ->where('id', $detail->id)
                    ->update(['property_number' => $propertyNumber]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropColumn('property_number');
        });
    }
};
