<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Office forms from SDO_Reports_Reference_Pack (revision 2), aligned with the SDO Inventory
 * desktop app:
 * - optional documented unit cost on supplies and equipment (blank stays blank, never 0);
 * - equipment property class: semi-expendable (ICS / IIRUP) or PPE (PAR / Property Card);
 * - IIRUP values on disposal cases, entered as Accounting and the inspection committee supply them;
 * - office_form_documents: the locked copy saved when a Local RIV or ICS is issued, so reprints
 *   keep the original names, costs and signatories;
 * - settings for the Stock Card agency, IIRUP station and witness.
 * Existing rows are left unchanged (all new columns are nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->nullable()->after('reference_stock');
            }
        });

        Schema::table('equipments', function (Blueprint $table) {
            if (! Schema::hasColumn('equipments', 'property_class')) {
                $table->string('property_class', 30)->nullable()->after('type');
            }
            if (! Schema::hasColumn('equipments', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->nullable()->after('life_span_years');
            }
        });

        Schema::table('equipment_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment_cases', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->nullable()->after('disposal_reference');
                $table->decimal('accumulated_impairment', 15, 2)->nullable()->after('unit_cost');
                $table->decimal('carrying_amount', 15, 2)->nullable()->after('accumulated_impairment');
                $table->unsignedInteger('disposal_sale_qty')->nullable()->after('carrying_amount');
                $table->unsignedInteger('disposal_transfer_qty')->nullable()->after('disposal_sale_qty');
                $table->unsignedInteger('disposal_destruction_qty')->nullable()->after('disposal_transfer_qty');
                $table->unsignedInteger('disposal_others_qty')->nullable()->after('disposal_destruction_qty');
                $table->string('disposal_others_specify')->nullable()->after('disposal_others_qty');
                $table->decimal('appraised_value', 15, 2)->nullable()->after('disposal_others_specify');
                $table->string('or_number')->nullable()->after('appraised_value');
                $table->decimal('sale_amount', 15, 2)->nullable()->after('or_number');
            }
        });

        if (! Schema::hasTable('office_form_documents')) {
            Schema::create('office_form_documents', function (Blueprint $table) {
                $table->id();
                $table->string('form_type', 20);
                $table->string('document_number')->unique();
                $table->foreignId('issuance_id')->constrained('issuances');
                $table->unsignedSmallInteger('template_revision');
                $table->longText('payload');
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at');
                $table->timestamps();

                $table->unique(['form_type', 'issuance_id']);
            });
        }

        foreach (['form_agency', 'form_station', 'form_witness_name', 'form_witness_designation'] as $key) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => '',
                    'group' => 'forms',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Requested by is the requesting employee on the Local RIV (as in the desktop app);
        // the unused empty placeholders seeded earlier are removed.
        DB::table('settings')
            ->whereIn('key', ['form_requested_by_name', 'form_requested_by_designation'])
            ->where(fn ($query) => $query->whereNull('value')->orWhere('value', ''))
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('office_form_documents');

        Schema::table('equipment_cases', function (Blueprint $table) {
            foreach (['unit_cost', 'accumulated_impairment', 'carrying_amount', 'disposal_sale_qty', 'disposal_transfer_qty',
                'disposal_destruction_qty', 'disposal_others_qty', 'disposal_others_specify', 'appraised_value', 'or_number', 'sale_amount'] as $column) {
                if (Schema::hasColumn('equipment_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('equipments', function (Blueprint $table) {
            foreach (['property_class', 'unit_cost'] as $column) {
                if (Schema::hasColumn('equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });

        DB::table('settings')
            ->whereIn('key', ['form_agency', 'form_station', 'form_witness_name', 'form_witness_designation'])
            ->where(fn ($query) => $query->whereNull('value')->orWhere('value', ''))
            ->delete();
    }
};
