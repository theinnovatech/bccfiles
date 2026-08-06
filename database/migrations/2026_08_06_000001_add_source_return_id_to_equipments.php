<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->foreignId('source_return_id')
                ->nullable()
                ->after('inventory_number')
                ->constrained('returns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_return_id');
        });
    }
};
