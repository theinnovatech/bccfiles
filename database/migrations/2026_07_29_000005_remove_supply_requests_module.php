<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('issuances', 'request_id')) {
            Schema::table('issuances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('request_id');
            });
        }

        Schema::dropIfExists('request_details');
        Schema::dropIfExists('supply_requests');
    }

    public function down(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->string('request_type')->default('items');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('request_date')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('issuances', function (Blueprint $table) {
            $table->foreignId('request_id')
                ->nullable()
                ->after('issuance_number')
                ->constrained('supply_requests')
                ->nullOnDelete();
        });
    }
};
