<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Http\Middleware\BlockWritesDuringRestore;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Revision 12 verification: runs the complete backup-and-restore flow, including the
 * failure / automatic-rollback path and the write lock, against a disposable copy of the
 * database. The real database is only read (to seed the copy) and is never modified.
 */
class VerifyBackupRestore extends Command
{
    protected $signature = 'obims:verify-restore {--keep : Keep the disposable database for inspection}';

    protected $description = 'Verify backup, restore, rollback and write-lock behaviour in a disposable database.';

    private const DIRECTORY = 'backups/verify';

    private const CONNECTION = 'restore_verify';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $results = [];

    public function handle(DatabaseBackupService $backups, DatabaseRestoreService $restore): int
    {
        $source = (string) config('database.default');
        $base = config("database.connections.{$source}");

        if (! in_array($base['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('This verification requires a MySQL / MariaDB connection.');

            return self::FAILURE;
        }

        if (DatabaseRestoreService::isLocked()) {
            $this->error('A restore lock is active. Resolve it before running the verification.');

            return self::FAILURE;
        }

        $database = 'obims_restore_verify_'.now()->format('YmdHis');
        DB::connection($source)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(["database.connections.".self::CONNECTION => array_merge($base, ['database' => $database])]);
        DB::purge(self::CONNECTION);
        $this->info("Disposable database: {$database}");

        $files = [];
        $ok = true;

        try {
            $admin = User::query()->where('role', UserRole::Admin)->orderBy('id')->firstOrFail();
            $db = DB::connection(self::CONNECTION);

            // 1. Seed the disposable database from a fresh backup of the real one.
            $seed = $backups->create($source, self::DIRECTORY);
            $files[] = $seed['filename'];
            $restore->apply($this->path($seed['filename']), self::CONNECTION);
            $this->check('Backup of live database seeds disposable copy',
                $this->rowCounts($source) === $this->rowCounts(self::CONNECTION, true) ? 'pass' : 'fail',
                'row counts match for every table');
            $baseline = $this->fingerprint();

            // 2. Back up the disposable copy (the backup under test).
            $snapshot = $backups->create(self::CONNECTION, self::DIRECTORY);
            $files[] = $snapshot['filename'];
            $inspection = $restore->inspect($this->path($snapshot['filename']), self::CONNECTION);
            $this->check('Backup validates', $inspection['valid'] ? 'pass' : 'fail',
                ($inspection['format'] ?? '?').', '.($inspection['scope']['restore_table_count'] ?? 0).' tables, timestamp '.($inspection['backup_timestamp_label'] ?? '?'));

            // 3. Change data after the backup (simulates later activity).
            $markerId = $db->table('categories')->insertGetId([
                'name' => 'RESTORE-VERIFY-MARKER-'.uniqid(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $db->statement('CREATE TABLE `zz_restore_verify_extra` (`id` INT PRIMARY KEY)');
            $db->table('items')->increment('current_stock', 7);

            // 4. Write lock blocks inventory writes during a restore.
            $this->verifyWriteLock();

            // 5. Full restore through the service (recovery backup, restore, migrate, log).
            $entry = $restore->restore($snapshot['filename'], $admin, self::CONNECTION, self::DIRECTORY);
            $files[] = $entry['recovery_backup'];
            $after = $this->fingerprint();
            $this->check('Restore returns data to backup state', $after === $baseline ? 'pass' : 'fail', 'table checksums equal the backup');
            $this->check('Changes made after the backup are gone', ! $db->table('categories')->where('id', $markerId)->exists() ? 'pass' : 'fail', 'marker row removed, stock reverted');
            $this->check('Tables not in the backup are removed', ! $this->tableExists('zz_restore_verify_extra') ? 'pass' : 'fail', 'zz_restore_verify_extra dropped');
            $this->check('Recovery backup created first', is_file($this->path($entry['recovery_backup'])) ? 'pass' : 'fail', $entry['recovery_backup']);
            $this->check('Initiator and outcome recorded', $entry['status'] === 'succeeded'
                && $db->table('activity_logs')->where('action', 'Restored')->where('description', 'like', '%'.$snapshot['filename'].'%')->exists() ? 'pass' : 'fail',
                'log file entry + activity log by '.$admin->name);
            $this->check('Write lock released after success', ! DatabaseRestoreService::isLocked() ? 'pass' : 'fail', '');

            // 6. Failure part-way: corrupted backup must roll back automatically.
            $beforeFailure = $this->fingerprint();
            $markerTwo = $db->table('categories')->insertGetId([
                'name' => 'RESTORE-VERIFY-ROLLBACK-'.uniqid(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $expected = $this->fingerprint();
            $broken = $this->corruptCopy($snapshot['filename']);
            $files[] = $broken;

            $failedAsExpected = false;
            try {
                $restore->restore($broken, $admin, self::CONNECTION, self::DIRECTORY);
            } catch (RuntimeException $e) {
                $failedAsExpected = str_contains($e->getMessage(), 'rolled back');
            }
            $last = collect($restore->logEntries(self::DIRECTORY.'/restore-log.json'))->last();
            if (! empty($last['recovery_backup'])) {
                $files[] = $last['recovery_backup'];
            }

            $this->check('Partial failure is detected', $failedAsExpected ? 'pass' : 'fail', $last['status'] ?? 'no log entry');
            $this->check('Automatic rollback restores pre-restore data', $this->fingerprint(['activity_logs']) === $this->withoutTables($expected, ['activity_logs']) && $db->table('categories')->where('id', $markerTwo)->exists() ? 'pass' : 'fail', 'checksums equal the state before the failed restore');
            $this->check('Failed restore recorded', $db->table('activity_logs')->where('action', 'Restore Failed')->exists() ? 'pass' : 'fail', 'activity log + restore log');
            $this->check('Write lock released after rollback', ! DatabaseRestoreService::isLocked() ? 'pass' : 'fail', '');
            unset($beforeFailure);
        } catch (Throwable $e) {
            $this->check('Verification run', 'fail', $e->getMessage());
        } finally {
            if (DatabaseRestoreService::isLocked()) {
                @unlink(DatabaseRestoreService::lockPath());
            }

            if (! $this->option('keep')) {
                DB::connection($source)->statement("DROP DATABASE IF EXISTS `{$database}`");
                foreach (array_unique($files) as $file) {
                    Storage::disk('local')->delete(self::DIRECTORY.'/'.$file);
                }
            }
        }

        $this->table(['Check', 'Result', 'Detail'], $this->results);
        $ok = collect($this->results)->every(fn ($row) => $row[1] === 'pass');
        $this->line($ok ? '<info>All backup / restore checks passed.</info>' : '<error>Some checks failed.</error>');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function verifyWriteLock(): void
    {
        file_put_contents(DatabaseRestoreService::lockPath(), json_encode(['state' => 'running', 'started_at' => now()->toIso8601String()]));

        try {
            $middleware = new BlockWritesDuringRestore();
            $blocked = $middleware->handle(Request::create('/issuances', 'POST'), fn () => response('passed'))->getStatusCode();
            $received = $middleware->handle(Request::create('/stock/receive', 'POST'), fn () => response('passed'))->getStatusCode();
            $read = $middleware->handle(Request::create('/items/list', 'GET'), fn () => response('passed'))->getStatusCode();
            $this->check('Inventory writes blocked during restore', $blocked === 423 && $received === 423 && $read === 200 ? 'pass' : 'fail', "issuance {$blocked}, receive {$received}, read {$read}");
        } finally {
            @unlink(DatabaseRestoreService::lockPath());
        }
    }

    private function corruptCopy(string $filename): string
    {
        $sql = (string) file_get_contents($this->path($filename));
        $position = strpos($sql, 'CREATE TABLE `issuances`') ?: (int) (strlen($sql) / 2);
        $broken = substr($sql, 0, $position)."INSERT INTO `categories` VALUES (this is not valid sql);\n".substr($sql, $position);
        $name = 'obims-'.now()->addMinutes(5)->format('Y-m-d-His').'.sql';
        Storage::disk('local')->put(self::DIRECTORY.'/'.$name, $broken);

        return $name;
    }

    private function path(string $filename): string
    {
        return Storage::disk('local')->path(self::DIRECTORY.'/'.$filename);
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(string $connection, bool $excludePreserved = true): array
    {
        $db = DB::connection($connection);
        $counts = [];
        foreach ($this->tables($connection) as $table) {
            if ($excludePreserved && in_array($table, DatabaseRestoreService::PRESERVED_TABLES, true)) {
                continue;
            }
            $counts[$table] = (int) $db->table($table)->count();
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    private function fingerprint(array $exclude = ['activity_logs']): array
    {
        $db = DB::connection(self::CONNECTION);
        $result = [];
        foreach ($this->tables(self::CONNECTION) as $table) {
            if (in_array($table, DatabaseRestoreService::PRESERVED_TABLES, true) || in_array($table, $exclude, true)) {
                continue;
            }
            $row = (array) $db->selectOne('CHECKSUM TABLE `'.$table.'`');
            $result[$table] = (string) ($row['Checksum'] ?? '');
        }
        ksort($result);

        return $result;
    }

    private function withoutTables(array $fingerprint, array $tables): array
    {
        return array_diff_key($fingerprint, array_flip($tables));
    }

    /**
     * @return list<string>
     */
    private function tables(string $connection): array
    {
        return collect(DB::connection($connection)->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->values()
            ->all();
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->tables(self::CONNECTION), true);
    }

    private function check(string $name, string $result, string $detail): void
    {
        $this->results[] = [$name, $result, $detail];
    }
}
