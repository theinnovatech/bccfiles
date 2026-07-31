<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public const DIRECTORY = 'backups';

    /**
     * @return array{filename: string, path: string, size: int, created_at: string}
     */
    public function create(): array
    {
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        $filename = 'obims-'.now()->format('Y-m-d-His').'.sql';
        $relativePath = self::DIRECTORY.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $this->exportMysql($absolutePath);
        } elseif ($driver === 'sqlite') {
            $this->exportSqlite($absolutePath);
        } else {
            throw new RuntimeException("Database backups are not supported for the [{$driver}] driver.");
        }

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            throw new RuntimeException('Backup file was not created or is empty.');
        }

        return $this->formatBackup($filename);
    }

    /**
     * @return list<array{filename: string, path: string, size: int, created_at: string}>
     */
    public function list(): array
    {
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.sql'))
            ->map(fn (string $path) => $this->formatBackup(basename($path)))
            ->filter()
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function absolutePath(string $filename): string
    {
        $filename = $this->assertSafeFilename($filename);
        $relativePath = self::DIRECTORY.'/'.$filename;

        if (! Storage::disk('local')->exists($relativePath)) {
            abort(404, 'Backup file not found.');
        }

        return Storage::disk('local')->path($relativePath);
    }

    public function delete(string $filename): void
    {
        $filename = $this->assertSafeFilename($filename);
        $relativePath = self::DIRECTORY.'/'.$filename;

        if (! Storage::disk('local')->exists($relativePath)) {
            abort(404, 'Backup file not found.');
        }

        Storage::disk('local')->delete($relativePath);
    }

    private function exportMysql(string $absolutePath): void
    {
        if ($this->tryMysqldump($absolutePath)) {
            return;
        }

        $this->exportMysqlWithPhp($absolutePath);
    }

    private function tryMysqldump(string $absolutePath): bool
    {
        $connection = config('database.connections.mysql');
        $binary = $this->findMysqldumpBinary();

        if (! $binary) {
            return false;
        }

        $command = [
            $binary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.($connection['username'] ?? ''),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$absolutePath,
            $connection['database'] ?? '',
        ];

        $process = new Process($command);
        $process->setTimeout(300);

        if (! empty($connection['password'])) {
            $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        }

        try {
            $process->run();
        } catch (Throwable) {
            return false;
        }

        return $process->isSuccessful() && is_file($absolutePath) && filesize($absolutePath) > 0;
    }

    private function findMysqldumpBinary(): ?string
    {
        $candidates = [
            'mysqldump',
            'mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump' || $candidate === 'mysqldump.exe') {
                $process = Process::fromShellCommandline(
                    PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'command -v mysqldump'
                );
                $process->run();

                if ($process->isSuccessful()) {
                    $path = trim(explode("\n", str_replace("\r", '', $process->getOutput()))[0] ?? '');

                    if ($path !== '' && is_file($path)) {
                        return $path;
                    }
                }

                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function exportMysqlWithPhp(string $absolutePath): void
    {
        $pdo = DB::connection()->getPdo();
        $database = (string) config('database.connections.mysql.database');
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for writing.');
        }

        try {
            fwrite($handle, "-- OBIMS MySQL backup\n");
            fwrite($handle, '-- Generated at: '.now()->toDateTimeString()."\n");
            fwrite($handle, "-- Database: {$database}\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\''))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->filter()
                ->values();

            foreach ($tables as $table) {
                $create = DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`');
                $createSql = array_values((array) $create)[1] ?? null;

                if (! is_string($createSql) || $createSql === '') {
                    continue;
                }

                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $createSql.";\n\n");

                $rows = DB::table($table)->orderByRaw('1')->get();

                if ($rows->isEmpty()) {
                    fwrite($handle, "\n");
                    continue;
                }

                foreach ($rows->chunk(100) as $chunk) {
                    $values = $chunk->map(function ($row) use ($pdo) {
                        $cells = collect((array) $row)->map(function ($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }

                            if (is_bool($value)) {
                                return $value ? '1' : '0';
                            }

                            if (is_int($value) || is_float($value)) {
                                return (string) $value;
                            }

                            return $pdo->quote((string) $value);
                        })->implode(', ');

                        return "({$cells})";
                    })->implode(",\n");

                    fwrite($handle, "INSERT INTO `{$table}` VALUES\n{$values};\n");
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    private function exportSqlite(string $absolutePath): void
    {
        $databasePath = (string) config('database.connections.sqlite.database');

        if (! is_file($databasePath)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        if (! File::copy($databasePath, $absolutePath)) {
            throw new RuntimeException('Unable to copy SQLite database for backup.');
        }
    }

    /**
     * @return array{filename: string, path: string, size: int, created_at: string}|null
     */
    private function formatBackup(string $filename): ?array
    {
        if (! $this->isSafeFilename($filename)) {
            return null;
        }

        $relativePath = self::DIRECTORY.'/'.$filename;

        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        return [
            'filename' => $filename,
            'path' => $relativePath,
            'size' => (int) filesize($absolutePath),
            'created_at' => date('c', (int) filemtime($absolutePath)),
        ];
    }

    private function assertSafeFilename(string $filename): string
    {
        $filename = basename($filename);

        if (! $this->isSafeFilename($filename)) {
            abort(404, 'Backup file not found.');
        }

        return $filename;
    }

    private function isSafeFilename(string $filename): bool
    {
        return (bool) preg_match('/^obims-\d{4}-\d{2}-\d{2}-\d{6}\.sql$/i', $filename);
    }
}
