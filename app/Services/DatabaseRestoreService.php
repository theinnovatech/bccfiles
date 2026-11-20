<?php

namespace App\Services;

use App\Models\User;
use App\Support\SqlStatementSplitter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Revision 12: restore a database backup from the Backup Files page.
 *
 * Flow: validate -> show timestamp & scope -> explicit confirmation -> write lock ->
 * fresh recovery backup -> restore -> run pending migrations -> unlock.
 * If anything fails part-way, the recovery backup is restored automatically. If that also
 * fails, the write lock stays on (inventory writes remain blocked) and the log names the
 * recovery file to restore from the Backup Files page or with `php artisan obims:restore`.
 *
 * The restore log lives on disk (not in the database) so it survives the restore itself.
 */
class DatabaseRestoreService
{
    public const LOCK_FILE = 'framework/obims-restore.lock';
    public const LOG_FILE = 'backups/restore-log.json';

    /** Session / cache / queue tables are left as they are (keeps the admin signed in). */
    public const PRESERVED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** A backup must contain these tables to be accepted as an OBIMS backup. */
    public const REQUIRED_TABLES = ['migrations', 'users', 'items', 'equipments', 'issuances', 'stock_movements'];

    private string $logFile = self::LOG_FILE;

    public function __construct(
        private readonly DatabaseBackupService $backupService,
    ) {}

    /**
     * Validate a backup file and describe what a restore would do.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $absolutePath, ?string $connection = null): array
    {
        $connection ??= (string) config('database.default');
        $filename = basename($absolutePath);
        $errors = [];

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['valid' => false, 'filename' => $filename, 'errors' => ['Backup file not found or not readable.']];
        }

        $sql = (string) file_get_contents($absolutePath);
        $head = substr($sql, 0, 2048);
        $format = match (true) {
            str_contains($head, '-- OBIMS MySQL backup') => 'OBIMS PHP export',
            (bool) preg_match('/--\s+(MySQL|MariaDB) dump/i', $head) => 'mysqldump',
            str_contains($head, 'phpMyAdmin SQL Dump') => 'phpMyAdmin export',
            default => null,
        };

        if ($format === null) {
            $errors[] = 'Unrecognised file format. Only SQL backups created by OBIMS (or mysqldump) can be restored.';
        }

        $created = [];
        $inserts = [];
        $statementCount = 0;
        $incomplete = false;

        try {
            foreach (SqlStatementSplitter::split($sql) as $statement) {
                $statementCount++;
                $table = SqlStatementSplitter::targetTable($statement);
                $kind = SqlStatementSplitter::kind($statement);

                if ($kind === 'database') {
                    $errors[] = 'The backup switches or creates databases (USE / CREATE DATABASE); it cannot be restored here.';
                }

                if ($table && $kind === 'create') {
                    $created[$table] = true;
                }

                if ($table && $kind === 'insert') {
                    $inserts[$table] = ($inserts[$table] ?? 0) + 1;
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'The SQL file could not be parsed: '.$e->getMessage();
        }

        if ($format === 'mysqldump' && ! preg_match('/--\s+Dump completed/i', substr($sql, -512))) {
            $incomplete = true;
            $errors[] = 'The mysqldump file is incomplete (no "Dump completed" footer).';
        }

        $missing = array_values(array_diff(self::REQUIRED_TABLES, array_keys($created)));
        if ($missing !== []) {
            $errors[] = 'The backup is missing required OBIMS tables: '.implode(', ', $missing).'.';
        }

        $backupTables = array_keys($created);
        $currentTables = $this->currentTables($connection);
        $restoreTables = array_values(array_diff($backupTables, self::PRESERVED_TABLES));
        $dropTables = array_values(array_diff($currentTables, $backupTables, self::PRESERVED_TABLES));

        $timestamp = $this->timestampFor($filename, $head);

        return [
            'valid' => $errors === [],
            'filename' => $filename,
            'format' => $format,
            'size' => filesize($absolutePath),
            'backup_timestamp' => $timestamp?->toIso8601String(),
            'backup_timestamp_label' => $timestamp?->format('Y-m-d H:i:s'),
            'statement_count' => $statementCount,
            'scope' => [
                'restore_tables' => $restoreTables,
                'restore_table_count' => count($restoreTables),
                'tables_with_data' => count(array_intersect(array_keys($inserts), $restoreTables)),
                'preserved_tables' => self::PRESERVED_TABLES,
                'tables_removed' => $dropTables,
                'description' => 'Replaces every OBIMS data table with the contents of this backup (inventory, transactions, users, settings, logs). '
                    .'Session, cache and queue tables are kept. Tables created after this backup are removed and re-created empty by pending migrations.',
            ],
            'incomplete' => $incomplete,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array<string, mixed> log entry
     */
    public function restore(string $filename, User $user, ?string $connection = null, string $directory = DatabaseBackupService::DIRECTORY): array
    {
        $connection ??= (string) config('database.default');
        $absolutePath = $directory === DatabaseBackupService::DIRECTORY
            ? $this->backupService->absolutePath($filename)
            : Storage::disk('local')->path($directory.'/'.basename($filename));

        $this->logFile = $directory === DatabaseBackupService::DIRECTORY ? self::LOG_FILE : $directory.'/restore-log.json';
        $this->acquireLock($user, $filename);
        @set_time_limit(0);
        ignore_user_abort(true);

        $entry = [
            'id' => (string) Str::uuid(),
            'backup' => $filename,
            'initiated_by' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'status' => 'running',
            'recovery_backup' => null,
            'error' => null,
            'rollback_error' => null,
        ];

        try {
            $inspection = $this->inspect($absolutePath, $connection);
            $entry['backup_timestamp'] = $inspection['backup_timestamp'] ?? null;
            $entry['tables_restored'] = $inspection['scope']['restore_table_count'] ?? 0;

            if (! $inspection['valid']) {
                throw new RuntimeException('Backup validation failed: '.implode(' ', $inspection['errors']));
            }

            $recovery = $this->backupService->create($connection, $directory);
            $entry['recovery_backup'] = $recovery['filename'];
            $this->appendLog($entry);
        } catch (Throwable $e) {
            $entry['status'] = 'failed_before_restore';
            $entry['error'] = $e->getMessage();
            $entry['finished_at'] = now()->toIso8601String();
            $this->appendLog($entry);
            $this->releaseLock();

            throw new RuntimeException($entry['error'], 0, $e);
        }

        try {
            $this->apply($absolutePath, $connection);
            $entry['migrations'] = $this->migrate($connection);
            $entry['status'] = 'succeeded';
        } catch (Throwable $e) {
            $entry['error'] = $e->getMessage();
            Log::error('OBIMS restore failed; rolling back to recovery backup.', ['backup' => $filename, 'error' => $e->getMessage()]);

            try {
                $this->apply(Storage::disk('local')->path($directory.'/'.$entry['recovery_backup']), $connection);
                $this->migrate($connection);
                $entry['status'] = 'failed_rolled_back';
            } catch (Throwable $rollback) {
                $entry['status'] = 'failed_recovery_required';
                $entry['rollback_error'] = $rollback->getMessage();
                Log::critical('OBIMS restore rollback failed; writes stay locked.', ['recovery' => $entry['recovery_backup'], 'error' => $rollback->getMessage()]);
            }
        }

        $entry['finished_at'] = now()->toIso8601String();
        $this->appendLog($entry);
        $this->recordActivity($entry, $connection);

        if ($entry['status'] === 'failed_recovery_required') {
            $this->markLockFailed($entry);
        } else {
            $this->releaseLock();
        }

        if ($entry['status'] !== 'succeeded') {
            throw new RuntimeException(match ($entry['status']) {
                'failed_rolled_back' => 'Restore failed and the database was rolled back to the recovery backup '.$entry['recovery_backup'].'. Error: '.$entry['error'],
                default => 'Restore failed and the automatic rollback also failed. Inventory writes remain blocked. Restore the recovery backup '.$entry['recovery_backup'].' to recover. Error: '.$entry['error'],
            });
        }

        return $entry;
    }

    /**
     * Execute the backup against the connection (no write lock / logging).
     */
    public function apply(string $absolutePath, string $connection): void
    {
        $db = DB::connection($connection);
        $pdo = $db->getPdo();
        $sql = (string) file_get_contents($absolutePath);

        $backupTables = [];
        foreach (SqlStatementSplitter::split($sql) as $statement) {
            if (SqlStatementSplitter::kind($statement) === 'create' && ($table = SqlStatementSplitter::targetTable($statement))) {
                $backupTables[$table] = true;
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->currentTables($connection) as $table) {
                if (! isset($backupTables[$table]) && ! in_array($table, self::PRESERVED_TABLES, true)) {
                    $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
                }
            }

            foreach (SqlStatementSplitter::split($sql) as $statement) {
                $kind = SqlStatementSplitter::kind($statement);
                if (in_array($kind, ['lock', 'database', 'transaction'], true)) {
                    continue;
                }

                $table = SqlStatementSplitter::targetTable($statement);
                if ($table && in_array($table, self::PRESERVED_TABLES, true)) {
                    continue;
                }

                if ($kind === 'create' && $table) {
                    $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
                }

                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return list<string>
     */
    public function currentTables(string $connection): array
    {
        return collect(DB::connection($connection)->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->filter()
            ->values()
            ->all();
    }

    public static function lockPath(): string
    {
        return storage_path(self::LOCK_FILE);
    }

    public static function isLocked(): bool
    {
        return is_file(self::lockPath());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function lockState(): ?array
    {
        if (! self::isLocked()) {
            return null;
        }

        $state = json_decode((string) @file_get_contents(self::lockPath()), true);

        return is_array($state) ? $state : ['state' => 'running'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logEntries(?string $file = null): array
    {
        $file ??= $this->logFile;
        $raw = Storage::disk('local')->exists($file)
            ? Storage::disk('local')->get($file)
            : '[]';
        $entries = json_decode((string) $raw, true);

        return is_array($entries) ? array_values($entries) : [];
    }

    private function acquireLock(User $user, string $filename): void
    {
        $state = self::lockState();
        if ($state && ($state['state'] ?? 'running') === 'running') {
            $started = isset($state['started_at']) ? Carbon::parse($state['started_at']) : null;
            if (! $started || $started->gt(now()->subHour())) {
                throw new RuntimeException('Another restore is already in progress.');
            }
        }

        if (! is_dir(dirname(self::lockPath()))) {
            mkdir(dirname(self::lockPath()), 0775, true);
        }

        file_put_contents(self::lockPath(), json_encode([
            'state' => 'running',
            'backup' => $filename,
            'user' => $user->name,
            'user_id' => $user->id,
            'started_at' => now()->toIso8601String(),
        ]), LOCK_EX);
    }

    private function markLockFailed(array $entry): void
    {
        file_put_contents(self::lockPath(), json_encode([
            'state' => 'failed',
            'backup' => $entry['backup'],
            'recovery_backup' => $entry['recovery_backup'],
            'user' => $entry['initiated_by']['name'] ?? null,
            'started_at' => $entry['started_at'],
            'error' => $entry['error'],
        ]), LOCK_EX);
    }

    private function releaseLock(): void
    {
        if (self::isLocked()) {
            @unlink(self::lockPath());
        }
    }

    private function appendLog(array $entry): void
    {
        $entries = collect($this->logEntries())
            ->reject(fn ($row) => ($row['id'] ?? null) === $entry['id'])
            ->push($entry)
            ->take(-200)
            ->values()
            ->all();

        Storage::disk('local')->put($this->logFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return list<string>
     */
    private function migrate(string $connection): array
    {
        Artisan::call('migrate', ['--force' => true, '--database' => $connection]);

        return collect(preg_split('/\R/', Artisan::output()))
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line) ?? ''))
            ->filter(fn ($line) => str_contains($line, 'DONE') || str_contains($line, 'Nothing to migrate'))
            ->values()
            ->all();
    }

    private function recordActivity(array $entry, string $connection): void
    {
        try {
            DB::connection($connection)->table('activity_logs')->insert([
                'user_id' => DB::connection($connection)->table('users')->where('id', $entry['initiated_by']['id'])->exists()
                    ? $entry['initiated_by']['id']
                    : null,
                'action' => $entry['status'] === 'succeeded' ? 'Restored' : 'Restore Failed',
                'module' => 'Backups',
                'description' => sprintf(
                    '%s restore of %s initiated by %s (%s). Recovery backup: %s.%s',
                    $entry['status'] === 'succeeded' ? 'Successful' : 'Failed ('.$entry['status'].')',
                    $entry['backup'],
                    $entry['initiated_by']['name'],
                    $entry['initiated_by']['email'],
                    $entry['recovery_backup'] ?? 'none',
                    $entry['error'] ? ' Error: '.Str::limit($entry['error'], 300) : ''
                ),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Unable to write restore activity log entry.', ['error' => $e->getMessage()]);
        }
    }

    private function timestampFor(string $filename, string $head): ?Carbon
    {
        if (preg_match('/obims-(\d{4}-\d{2}-\d{2})-(\d{2})(\d{2})(\d{2})\.sql$/i', $filename, $m)) {
            return Carbon::parse("{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}");
        }

        if (preg_match('/Generated at:\s*(.+)/', $head, $m)) {
            return Carbon::parse(trim($m[1]));
        }

        return null;
    }
}
