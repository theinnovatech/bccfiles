<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 2 & 5:
 * - idempotency_key: a repeated submission of the same issuance returns the original record
 *   instead of issuing stock twice (unique index enforces it at the database level).
 * - recipient snapshot: name and unit are always written to received_by_name / department_name
 *   at issuance so later directory edits never rewrite history. The employee number and the
 *   way the recipient was resolved are stored alongside. Existing rows are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            if (! Schema::hasColumn('issuances', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('issuance_number');
                $table->unique('idempotency_key');
            }

            if (! Schema::hasColumn('issuances', 'recipient_employee_number')) {
                $table->string('recipient_employee_number')->nullable()->after('received_by_name');
            }

            if (! Schema::hasColumn('issuances', 'recipient_source')) {
                $table->string('recipient_source', 32)->nullable()->after('recipient_employee_number');
            }

            if (! Schema::hasColumn('issuances', 'channel')) {
                $table->string('channel', 32)->nullable()->after('recipient_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            if (Schema::hasColumn('issuances', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }

            foreach (['recipient_employee_number', 'recipient_source', 'channel'] as $column) {
                if (Schema::hasColumn('issuances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
