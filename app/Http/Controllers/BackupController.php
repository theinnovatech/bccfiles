<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly DatabaseRestoreService $restoreService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function index(): JsonResponse
    {
        $recoveryFiles = collect($this->restoreService->logEntries())
            ->pluck('recovery_backup')
            ->filter()
            ->flip();

        return response()->json([
            'backups' => collect($this->backupService->list())
                ->map(fn ($backup) => $backup + ['is_recovery' => $recoveryFiles->has($backup['filename'])])
                ->values(),
            'restore_lock' => DatabaseRestoreService::lockState(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $backup = $this->backupService->create();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to create database backup. '.$exception->getMessage(),
            ], 500);
        }

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Backups',
            "Created database backup {$backup['filename']}"
        );

        return response()->json([
            'message' => 'Database backup created successfully.',
            'backup' => $backup,
        ], 201);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backupService->absolutePath($filename);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        $this->backupService->delete($filename);

        $this->activityLogService->log(
            $request->user(),
            'Deleted',
            'Backups',
            "Deleted database backup {$filename}"
        );

        return response()->json([
            'message' => 'Backup file deleted.',
        ]);
    }

    /**
     * Validate a backup and describe its timestamp and restore scope (Revision 12).
     */
    public function inspect(string $filename): JsonResponse
    {
        return response()->json(
            $this->restoreService->inspect($this->backupService->absolutePath($filename))
        );
    }

    /**
     * Restore a backup after explicit confirmation (Revision 12).
     */
    public function restore(Request $request, string $filename): JsonResponse
    {
        $data = $request->validate([
            'confirm_filename' => ['required', 'string'],
            'acknowledge' => ['accepted'],
        ], [
            'acknowledge.accepted' => 'Confirm that you understand the current data will be replaced.',
        ]);

        if (! hash_equals($filename, trim($data['confirm_filename']))) {
            throw ValidationException::withMessages([
                'confirm_filename' => ['Type the backup file name exactly to confirm the restore.'],
            ]);
        }

        try {
            $entry = $this->restoreService->restore($filename, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'restore_lock' => DatabaseRestoreService::lockState(),
                'log' => collect($this->restoreService->logEntries())->last(),
            ], 500);
        }

        return response()->json([
            'message' => "Database restored from {$filename}. A recovery backup ({$entry['recovery_backup']}) was created first.",
            'entry' => $entry,
        ]);
    }

    public function restoreLog(): JsonResponse
    {
        return response()->json([
            'entries' => array_reverse($this->restoreService->logEntries()),
            'restore_lock' => DatabaseRestoreService::lockState(),
        ]);
    }
}
