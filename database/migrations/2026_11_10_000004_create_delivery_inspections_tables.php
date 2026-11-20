<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 8: delivery inspection + an inventory posting ledger.
 *
 * inventory_postings.posting_key is unique, so posting / retrying / re-reviewing the
 * same inspection line can never add stock twice, even under concurrent requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_number')->unique();
            $table->string('delivery_reference')->nullable();
            $table->string('purchase_reference')->nullable();
            $table->string('supplier')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('inspection_date');
            $table->string('inspector_name');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('delivery_reference');
            $table->index('purchase_reference');
            $table->index('status');
        });

        Schema::create('delivery_inspection_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_inspection_id')->constrained('delivery_inspections')->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('accepted_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);
            $table->string('condition', 30)->default('good');
            $table->text('discrepancy_remarks')->nullable();
            $table->json('serial_numbers')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_postings', function (Blueprint $table) {
            $table->id();
            $table->string('posting_key')->unique();
            $table->foreignId('delivery_inspection_id')->nullable()->constrained('delivery_inspections')->nullOnDelete();
            $table->foreignId('delivery_inspection_line_id')->nullable()->constrained('delivery_inspection_lines')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_postings');
        Schema::dropIfExists('delivery_inspection_lines');
        Schema::dropIfExists('delivery_inspections');
    }
};
