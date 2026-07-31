<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'backups' => $this->backupService->list(),
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
}
