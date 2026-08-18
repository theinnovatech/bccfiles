<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->date('custom_date_issued')->nullable()->after('custom_equipment_category');
            $table->date('custom_date_acquired')->nullable()->after('custom_date_issued');
            $table->text('custom_specs')->nullable()->after('custom_date_acquired');
            $table->text('custom_details')->nullable()->after('custom_specs');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn([
                'custom_date_issued',
                'custom_date_acquired',
                'custom_specs',
                'custom_details',
            ]);
        });
    }
};
