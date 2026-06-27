<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->foreignId('equipment_category_id')
                ->nullable()
                ->after('name')
                ->constrained('equipment_categories')
                ->nullOnDelete();
        });

        $equipments = DB::table('equipments')->get();

        foreach ($equipments as $equipment) {
            $categoryName = trim((string) ($equipment->category ?? ''));

            if ($categoryName === '') {
                continue;
            }

            $categoryId = DB::table('equipment_categories')->where('name', $categoryName)->value('id');

            if (! $categoryId) {
                $categoryId = DB::table('equipment_categories')->insertGetId([
                    'name' => $categoryName,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('equipments')
                ->where('id', $equipment->id)
                ->update(['equipment_category_id' => $categoryId]);
        }

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        $equipments = DB::table('equipments')
            ->leftJoin('equipment_categories', 'equipment_categories.id', '=', 'equipments.equipment_category_id')
            ->select('equipments.id', 'equipment_categories.name as category_name')
            ->get();

        foreach ($equipments as $equipment) {
            DB::table('equipments')
                ->where('id', $equipment->id)
                ->update(['category' => $equipment->category_name]);
        }

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_category_id');
        });

        Schema::dropIfExists('equipment_categories');
    }
};
