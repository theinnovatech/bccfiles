<?php

use App\Support\ReferenceNumberGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('item_number')->nullable()->unique()->after('barcode');
        });

        $items = DB::table('items')
            ->whereNull('item_number')
            ->orderBy('id')
            ->get(['id']);

        foreach ($items as $item) {
            DB::table('items')
                ->where('id', $item->id)
                ->update([
                    'item_number' => ReferenceNumberGenerator::forItem(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['item_number']);
            $table->dropColumn('item_number');
        });
    }
};
