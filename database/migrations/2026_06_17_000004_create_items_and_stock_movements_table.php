<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('location_id')->constrained('storage_locations');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->unsignedInteger('current_stock')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'current_stock']);
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');
            $table->integer('quantity');
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('new_stock');
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
            $table->index('transaction_type');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('items');
    }
};
