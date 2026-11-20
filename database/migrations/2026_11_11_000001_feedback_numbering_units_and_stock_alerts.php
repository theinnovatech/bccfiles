<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client feedback (feed-back.md). All additions are nullable or defaulted, so existing
 * equipment, items, documents and transactions keep working unchanged.
 *
 * - Equipment brand and model, and a Property Card number.
 * - Per-unit property / inventory numbers on equipment units (serial rows), so the same
 *   physical unit keeps its numbers through issuance, return, repair and re-issue.
 * - Recipient designation snapshot on issuances.
 * - Configurable low-stock percentage per item (default from settings) and persisted
 *   alert state, so alerts are raised once and resolved when stock recovers.
 * - Document numbering settings and atomic sequences per document type.
 * - Office form documents: IIRUP documents (no issuance), and numbers unique per form type.
 * - Return idempotency key (repeat submissions cannot process a return twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            if (! Schema::hasColumn('equipments', 'brand')) {
                $table->string('brand')->nullable()->after('name');
                $table->string('model')->nullable()->after('brand');
            }
            if (! Schema::hasColumn('equipments', 'property_card_number')) {
                $table->string('property_card_number')->nullable()->after('inventory_number');
                $table->unique('property_card_number');
            }
        });

        Schema::table('equipment_serial_numbers', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment_serial_numbers', 'property_number')) {
                $table->string('property_number')->nullable()->after('serial_number');
                $table->string('inventory_number')->nullable()->after('property_number');
                $table->index('property_number');
            }
        });

        Schema::table('issuances', function (Blueprint $table) {
            if (! Schema::hasColumn('issuances', 'recipient_designation')) {
                $table->string('recipient_designation')->nullable()->after('recipient_employee_number');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'low_stock_percent')) {
                $table->decimal('low_stock_percent', 5, 2)->nullable()->after('reference_stock');
            }
            if (! Schema::hasColumn('items', 'stock_alert_level')) {
                $table->string('stock_alert_level', 20)->nullable()->after('status');
                $table->timestamp('stock_alert_since')->nullable()->after('stock_alert_level');
            }
        });

        Schema::table('returns', function (Blueprint $table) {
            if (! Schema::hasColumn('returns', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('reference_number');
                $table->unique('idempotency_key');
            }
        });

        Schema::table('office_form_documents', function (Blueprint $table) {
            $table->dropForeign(['issuance_id']);
        });
        Schema::table('office_form_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('issuance_id')->nullable()->change();
            $table->foreign('issuance_id')->references('id')->on('issuances');
            if (! Schema::hasColumn('office_form_documents', 'as_of_date')) {
                $table->date('as_of_date')->nullable()->after('issuance_id');
            }
            $table->dropUnique(['document_number']);
            $table->unique(['form_type', 'document_number']);
        });

        if (! Schema::hasTable('document_number_settings')) {
            Schema::create('document_number_settings', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50)->unique();
                $table->string('mode', 10)->default('auto');
                $table->string('format', 120);
                $table->unsignedTinyInteger('padding')->default(4);
                $table->string('reset_rule', 10)->default('yearly');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_number_sequences')) {
            Schema::create('document_number_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50);
                $table->string('period_key', 20);
                $table->unsignedBigInteger('last_value')->default(0);
                $table->timestamps();
                $table->unique(['document_type', 'period_key']);
            });
        }

        // Defaults reproduce the formats the system already used, so nothing changes until
        // the organization configures its own formats.
        $defaults = [
            'equipment_property_number' => ['auto', 'PROP-{YYYY}-{SEQ}', 4, 'yearly'],
            'equipment_inventory_number' => ['auto', 'INV-{YYYY}-{SEQ}', 4, 'yearly'],
            'stock_card_number' => ['auto', 'ITEM-{YYYY}-{SEQ}', 4, 'yearly'],
            'property_card_number' => ['manual', 'PC-{YYYY}-{SEQ}', 4, 'yearly'],
            'ics_number' => ['auto', 'ICS-{YYYY}-{SEQ}', 4, 'yearly'],
            'riv_number' => ['auto', 'RIV-{YYYY}-{SEQ}', 4, 'yearly'],
            'iirup_number' => ['auto', 'IIRUP-{YYYY}-{SEQ}', 4, 'yearly'],
        ];
        foreach ($defaults as $type => [$mode, $format, $padding, $reset]) {
            if (! DB::table('document_number_settings')->where('document_type', $type)->exists()) {
                DB::table('document_number_settings')->insert([
                    'document_type' => $type,
                    'mode' => $mode,
                    'format' => $format,
                    'padding' => $padding,
                    'reset_rule' => $reset,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('settings')->where('key', 'low_stock_percent_default')->exists()) {
            DB::table('settings')->insert([
                'key' => 'low_stock_percent_default',
                'value' => '50',
                'group' => 'inventory',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_sequences');
        Schema::dropIfExists('document_number_settings');

        Schema::table('office_form_documents', function (Blueprint $table) {
            $table->dropUnique(['form_type', 'document_number']);
            $table->unique('document_number');
            if (Schema::hasColumn('office_form_documents', 'as_of_date')) {
                $table->dropColumn('as_of_date');
            }
        });

        Schema::table('returns', function (Blueprint $table) {
            if (Schema::hasColumn('returns', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            foreach (['low_stock_percent', 'stock_alert_level', 'stock_alert_since'] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('issuances', function (Blueprint $table) {
            if (Schema::hasColumn('issuances', 'recipient_designation')) {
                $table->dropColumn('recipient_designation');
            }
        });

        Schema::table('equipment_serial_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('equipment_serial_numbers', 'property_number')) {
                $table->dropIndex(['property_number']);
                $table->dropColumn(['property_number', 'inventory_number']);
            }
        });

        Schema::table('equipments', function (Blueprint $table) {
            if (Schema::hasColumn('equipments', 'property_card_number')) {
                $table->dropUnique(['property_card_number']);
                $table->dropColumn('property_card_number');
            }
            foreach (['brand', 'model'] as $column) {
                if (Schema::hasColumn('equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('settings')->where('key', 'low_stock_percent_default')->delete();
    }
};
