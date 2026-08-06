<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('custom_equipment_name')->nullable()->after('equipment_id');
            $table->string('custom_property_number')->nullable()->after('custom_equipment_name');
            $table->string('custom_inventory_number')->nullable()->after('custom_property_number');
            $table->string('custom_equipment_type')->nullable()->after('custom_inventory_number');
            $table->string('custom_equipment_category')->nullable()->after('custom_equipment_type');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn([
                'custom_equipment_name',
                'custom_property_number',
                'custom_inventory_number',
                'custom_equipment_type',
                'custom_equipment_category',
            ]);
        });
    }
};
