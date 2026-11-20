<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 1 & 7: equipment repair, warranty and disposal.
 *
 * - equipments gets recorded warranty coverage.
 * - equipment_cases tracks one controlled case per unit (or per batch for non-serialised
 *   listings). Disposition (status), condition and custody are separate columns.
 * - equipment_case_events is the immutable transition log (reason, date, user, reference).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            if (! Schema::hasColumn('equipments', 'warranty_provider')) {
                $table->string('warranty_provider')->nullable()->after('lifespan_expires_on');
                $table->string('warranty_reference')->nullable()->after('warranty_provider');
                $table->date('warranty_start_date')->nullable()->after('warranty_reference');
                $table->date('warranty_end_date')->nullable()->after('warranty_start_date');
            }
        });

        Schema::create('equipment_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('equipment_id')->constrained('equipments');
            $table->foreignId('equipment_serial_number_id')->nullable()->constrained('equipment_serial_numbers')->nullOnDelete();
            $table->foreignId('item_return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->string('origin', 30);
            $table->unsignedInteger('quantity')->default(1);

            // Disposition: for_repair | for_disposal | disposed | returned_to_service
            $table->string('status', 30);
            // Condition: serviceable | unserviceable
            $table->string('condition', 30)->default('unserviceable');
            // Custody: supply_office | supplier
            $table->string('custody', 30)->default('supply_office');

            $table->text('reported_issue');
            $table->date('reported_on');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('repair_outcome')->nullable();
            $table->boolean('serviceability_passed')->nullable();
            $table->date('serviceability_checked_on')->nullable();
            $table->foreignId('serviceability_checked_by')->nullable()->constrained('users')->nullOnDelete();

            // Warranty overlay: null | pending_review | decided
            $table->string('warranty_status', 30)->nullable();
            $table->date('warranty_end_date_at_report')->nullable();
            $table->string('warranty_claim_reference')->nullable();
            $table->string('warranty_outcome', 40)->nullable();
            $table->text('warranty_decision_notes')->nullable();
            $table->date('warranty_decided_on')->nullable();
            $table->foreignId('warranty_decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('disposal_assessment')->nullable();
            $table->string('disposal_approval_status', 20)->nullable();
            $table->string('disposal_approved_by_name')->nullable();
            $table->string('disposal_approval_reference')->nullable();
            $table->date('disposal_approval_date')->nullable();
            $table->string('disposal_method')->nullable();
            $table->date('disposal_date')->nullable();
            $table->string('disposal_reference')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('warranty_status');
        });

        Schema::create('equipment_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_case_id')->constrained('equipment_cases')->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason');
            $table->date('event_date');
            $table->string('reference')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['equipment_case_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_case_events');
        Schema::dropIfExists('equipment_cases');

        Schema::table('equipments', function (Blueprint $table) {
            foreach (['warranty_provider', 'warranty_reference', 'warranty_start_date', 'warranty_end_date'] as $column) {
                if (Schema::hasColumn('equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
