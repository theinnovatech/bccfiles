<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->date('lifespan_expires_on')->nullable()->after('date_acquired');
        });

        $equipments = DB::table('equipments')
            ->whereNull('lifespan_expires_on')
            ->whereNotNull('date_acquired')
            ->whereNotNull('life_span_years')
            ->get(['id', 'date_acquired', 'life_span_years']);

        foreach ($equipments as $equipment) {
            DB::table('equipments')->where('id', $equipment->id)->update([
                'lifespan_expires_on' => Carbon::parse($equipment->date_acquired)
                    ->startOfDay()
                    ->addYears((int) $equipment->life_span_years)
                    ->toDateString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn('lifespan_expires_on');
        });
    }
};
