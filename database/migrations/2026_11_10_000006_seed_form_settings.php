<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revision 6: signatory / header settings for the Local RIV, ICS and IIRUP forms.
 * Values start empty; the office fills them in under Settings once the approved
 * templates are confirmed. Existing settings are never overwritten.
 */
return new class extends Migration
{
    private const KEYS = [
        'form_entity_name',
        'form_fund_cluster',
        'form_requested_by_name',
        'form_requested_by_designation',
        'form_approved_by_name',
        'form_approved_by_designation',
        'form_issued_by_name',
        'form_issued_by_designation',
        'form_inspection_officer_name',
        'form_inspection_officer_designation',
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $exists = DB::table('settings')->where('key', $key)->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => '',
                    'group' => 'forms',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', self::KEYS)
            ->where(function ($query) {
                $query->whereNull('value')->orWhere('value', '');
            })
            ->delete();
    }
};
