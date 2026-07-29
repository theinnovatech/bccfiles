<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_count_sessions');
    }

    public function down(): void
    {
        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->foreignId('started_by')->constrained('users');
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('stock_count_sessions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('system_quantity');
            $table->integer('counted_quantity');
            $table->integer('variance');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
