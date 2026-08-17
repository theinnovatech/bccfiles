<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->date('date_acquired')->nullable()->after('specs');
        });

        Schema::table('issuance_details', function (Blueprint $table) {
            $table->date('date_acquired')->nullable()->after('inventory_number');
        });
    }

    public function down(): void
    {
        Schema::table('issuance_details', function (Blueprint $table) {
            $table->dropColumn('date_acquired');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn('date_acquired');
        });
    }
};
