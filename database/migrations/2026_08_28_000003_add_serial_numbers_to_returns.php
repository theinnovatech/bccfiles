<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->json('serial_numbers')->nullable()->after('quantity');
        });

        Schema::table('equipment_serial_numbers', function (Blueprint $table) {
            $table->foreignId('item_return_id')
                ->nullable()
                ->after('issuance_detail_id')
                ->constrained('returns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_serial_numbers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_return_id');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });
    }
};
