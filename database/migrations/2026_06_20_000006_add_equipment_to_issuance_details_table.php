<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('issuance_details', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->string('barcode')->nullable()->change();
            $table->foreignId('equipment_id')->nullable()->after('item_id')->constrained('equipments')->nullOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropForeign(['item_id']);
        });

        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropColumn('equipment_id');
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
            $table->string('barcode')->nullable(false)->change();
            $table->foreign('item_id')->references('id')->on('items');
        });
    }
};
