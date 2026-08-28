<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('serial_number');
            $table->string('status')->default('in_stock');
            $table->foreignId('issuance_detail_id')->nullable()->constrained('issuance_details')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('serial_number');
            $table->index(['equipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_serial_numbers');
    }
};
