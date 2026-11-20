<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Command-line recovery path for Revision 12, e.g. when the web UI is unavailable after a
 * failed restore:  php artisan obims:restore obims-2026-11-10-120000.sql --user=admin@obims.local
 */
class RestoreDatabaseBackup extends Command
{
    protected $signature = 'obims:restore
        {filename : Backup file name in storage/app/private/backups}
        {--user= : E-mail of the administrator recorded as initiator}
        {--force : Skip the interactive confirmation}
        {--unlock : Only clear a stale restore lock}';

    protected $description = 'Validate and restore an OBIMS database backup (creates a recovery backup first).';

    public function handle(DatabaseRestoreService $restore, DatabaseBackupService $backups): int
    {
        if ($this->option('unlock')) {
            @unlink(DatabaseRestoreService::lockPath());
            $this->info('Restore lock cleared.');

            return self::SUCCESS;
        }

        $filename = basename((string) $this->argument('filename'));
        $inspection = $restore->inspect($backups->absolutePath($filename));

        $this->table(['Field', 'Value'], [
            ['Backup', $inspection['filename']],
            ['Timestamp', $inspection['backup_timestamp_label'] ?? 'unknown'],
            ['Format', $inspection['format'] ?? 'unknown'],
            ['Tables restored', $inspection['scope']['restore_table_count'] ?? 0],
            ['Preserved', implode(', ', DatabaseRestoreService::PRESERVED_TABLES)],
            ['Removed (re-created by migrations)', implode(', ', $inspection['scope']['tables_removed'] ?? []) ?: '—'],
            ['Valid', $inspection['valid'] ? 'yes' : 'NO'],
        ]);

        if (! $inspection['valid']) {
            foreach ($inspection['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Replace the current database with this backup?')) {
            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::query()->where('email', $this->option('user'))->first()
            : User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->first();

        if (! $user) {
            $this->error('No administrator account found to record as the initiator. Use --user=email.');

            return self::FAILURE;
        }

        try {
            $entry = $restore->restore($filename, $user);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$filename}. Recovery backup: {$entry['recovery_backup']}");

        return self::SUCCESS;
    }
}
