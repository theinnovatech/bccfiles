<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('request_date')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('issuances', function (Blueprint $table) {
            $table->id();
            $table->string('issuance_number')->unique();
            $table->foreignId('request_id')->nullable()->constrained('supply_requests')->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('employees');
            $table->timestamp('issued_date')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('issuance_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->string('barcode');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->text('reason')->nullable();
            $table->foreignId('returned_by')->constrained('users');
            $table->timestamp('date_returned')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
        Schema::dropIfExists('issuance_details');
        Schema::dropIfExists('issuances');
        Schema::dropIfExists('request_details');
        Schema::dropIfExists('supply_requests');
    }
};
