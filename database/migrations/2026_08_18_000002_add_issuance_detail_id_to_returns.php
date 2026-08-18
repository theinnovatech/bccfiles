<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('issuance_detail_id')
                ->nullable()
                ->after('issuance_id')
                ->constrained('issuance_details')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issuance_detail_id');
        });
    }
};
