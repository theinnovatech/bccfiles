<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->string('request_type')->default('items')->after('request_number');
        });

        Schema::table('request_details', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('request_details', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->foreignId('equipment_id')->nullable()->after('item_id')->constrained('equipments')->nullOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('request_details', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropForeign(['item_id']);
        });

        DB::table('request_details')->whereNull('item_id')->delete();

        Schema::table('request_details', function (Blueprint $table) {
            $table->dropColumn('equipment_id');
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
            $table->foreign('item_id')->references('id')->on('items');
        });

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn('request_type');
        });
    }
};
