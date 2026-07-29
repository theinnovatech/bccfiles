<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();

            $table->foreignId('equipment_id')
                ->nullable()
                ->after('item_id')
                ->constrained('equipments')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->after('equipment_id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('borrower_employee_id')
                ->nullable()
                ->after('department_id')
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('borrower_name')
                ->nullable()
                ->after('borrower_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('borrower_employee_id');
            $table->dropColumn('borrower_name');
            $table->dropForeign(['item_id']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
            $table->foreign('item_id')->references('id')->on('items');
        });
    }
};
