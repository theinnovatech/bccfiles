<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('department_user')->after('password');
            $table->unsignedBigInteger('department_id')->nullable()->after('role');
            $table->unsignedBigInteger('employee_id')->nullable()->after('department_id');
            $table->boolean('is_active')->default(true)->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'department_id', 'employee_id', 'is_active']);
        });
    }
};
