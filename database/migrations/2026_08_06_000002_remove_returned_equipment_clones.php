<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Purge any equipment rows that were created as clones from returns.
        // Returned equipments are now tracked only in the returns table.
        DB::table('equipments')
            ->whereNotNull('source_return_id')
            ->delete();
    }

    public function down(): void
    {
        // No-op: cannot restore purged clones.
    }
};
