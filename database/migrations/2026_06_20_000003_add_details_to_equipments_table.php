<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('property_number')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('equipment_category_id');
            $table->unsignedInteger('quantity')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['property_number', 'description', 'quantity']);
        });
    }
};
